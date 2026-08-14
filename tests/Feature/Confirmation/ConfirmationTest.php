<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\IdentityConfirmationNotification;
use Webteractive\Passwordless\Support\ConfirmationLockedException;
use Webteractive\Passwordless\Support\ConfirmationResendCooldownException;
use Workbench\App\Models\User;

function confirmationCode(User $user): string
{
    Notification::fake();
    Passwordless::confirmation()->send($user);

    $code = null;
    Notification::assertSentTo($user, IdentityConfirmationNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    return $code;
}

it('mints a confirm challenge and emails the code', function () {
    $user = User::create(['email' => 'cf1@example.com']);

    $code = confirmationCode($user);

    expect($code)->toHaveLength(6);
    expect(Challenge::where('type', 'confirm')->where('user_id', $user->getKey())->count())->toBe(1);
});

it('verifies a valid code', function () {
    $user = User::create(['email' => 'cf2@example.com']);
    $code = confirmationCode($user);

    expect(Passwordless::confirmation()->verify($user, $code))->toBeTrue();
});

it('consumes the challenge so a code cannot be replayed', function () {
    $user = User::create(['email' => 'cf3@example.com']);
    $code = confirmationCode($user);

    expect(Passwordless::confirmation()->verify($user, $code))->toBeTrue();
    expect(Passwordless::confirmation()->verify($user, $code))->toBeFalse();
});

it('rejects a wrong code', function () {
    $user = User::create(['email' => 'cf4@example.com']);
    confirmationCode($user);

    expect(Passwordless::confirmation()->verify($user, '000000'))->toBeFalse();
});

it('rejects an expired code', function () {
    $user = User::create(['email' => 'cf5@example.com']);
    $code = confirmationCode($user);

    $this->travel(11)->minutes();

    expect(Passwordless::confirmation()->verify($user, $code))->toBeFalse();
});

it('replaces a prior unconsumed confirm challenge on resend', function () {
    $user = User::create(['email' => 'cf6@example.com']);
    confirmationCode($user);

    // Past the 30s resend cooldown, which is checked before anything else.
    $this->travel(31)->seconds();

    confirmationCode($user);

    expect(Challenge::where('type', 'confirm')->where('user_id', $user->getKey())->active()->count())->toBe(1);
});

it('enforces its own resend cooldown', function () {
    $user = User::create(['email' => 'cf7@example.com']);
    confirmationCode($user);

    Passwordless::confirmation()->send($user);
})->throws(ConfirmationResendCooldownException::class);

it('keeps its cooldown independent of the login-code cooldown', function () {
    $user = User::create(['email' => 'cf8@example.com']);

    // A login-code send starts the login_code cooldown...
    Notification::fake();
    $this->postJson('/auth/login-code', ['email' => 'cf8@example.com'])->assertStatus(202);

    // ...which must not block a confirmation send.
    Passwordless::confirmation()->send($user);

    expect(Challenge::where('type', 'confirm')->count())->toBe(1);
});

it('locks out after repeated failures without locking the user out of login', function () {
    $user = User::create(['email' => 'cf9@example.com']);
    confirmationCode($user);

    for ($i = 0; $i < 5; $i++) {
        Passwordless::confirmation()->verify($user, '000000');
    }

    // Past the resend cooldown so the lockout is what we're actually asserting;
    // the lockout window is 15 minutes, so it is still in force.
    $this->travel(31)->seconds();

    // Confirmation is locked...
    expect(fn () => Passwordless::confirmation()->send($user))
        ->toThrow(ConfirmationLockedException::class);

    // ...but login is unaffected.
    Notification::fake();
    $this->postJson('/auth/login-code', ['email' => 'cf9@example.com'])->assertStatus(202);
});

it('refuses to verify while locked out', function () {
    $user = User::create(['email' => 'cf9b@example.com']);
    $code = confirmationCode($user);

    for ($i = 0; $i < 5; $i++) {
        Passwordless::confirmation()->verify($user, '000000');
    }

    // Even the correct code is refused during the lockout window.
    expect(Passwordless::confirmation()->verify($user, $code))->toBeFalse();
});

it('exposes a send route that requires authentication', function () {
    $this->postJson(route('passwordless.confirm.send'))->assertStatus(401);
});

it('sends a code for the authenticated user via the route', function () {
    $user = User::create(['email' => 'cf10@example.com']);
    Notification::fake();

    $this->actingAs($user)->postJson(route('passwordless.confirm.send'))->assertStatus(202);

    Notification::assertSentTo($user, IdentityConfirmationNotification::class);
});

it('404s the send route when confirmation is disabled', function () {
    config()->set('passwordless.confirmation.enabled', false);
    $user = User::create(['email' => 'cf11@example.com']);

    $this->actingAs($user)->postJson(route('passwordless.confirm.send'))->assertStatus(404);
});

it('is pruned like any other challenge', function () {
    $user = User::create(['email' => 'cf12@example.com']);
    confirmationCode($user);

    Challenge::where('type', 'confirm')->update(['expires_at' => now()->subDay()]);

    $this->artisan('passwordless:prune')->assertExitCode(0);

    expect(Challenge::where('type', 'confirm')->count())->toBe(0);
});
