<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;
use Webteractive\Passwordless\Notifications\MagicCodeNotification;
use Webteractive\Passwordless\Notifications\MagicLinkNotification;
use Workbench\App\Models\TwoFactorUser;

beforeEach(function () {
    config()->set('passwordless.user_model', TwoFactorUser::class);
    // Fortify resolves the challenged user through the AUTH provider's model, so
    // the two must agree — as they do in a real starter kit.
    config()->set('auth.providers.users.model', TwoFactorUser::class);
    config()->set('passwordless.strategies.magic_link.same_browser', false);
});

function enrolTwoFactor(string $email): TwoFactorUser
{
    return TwoFactorUser::create([
        'email' => $email,
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);
}

function twoFactorCodeFor(string $email, array $payload = []): string
{
    Notification::fake();
    test()->postJson('/auth/login-code', array_merge(['email' => $email], $payload))
        ->assertStatus(202);

    $code = null;
    Notification::assertSentTo(
        TwoFactorUser::where('email', $email)->first(),
        LoginCodeNotification::class,
        function ($n) use (&$code) {
            $code = $n->code;

            return true;
        }
    );

    return $code;
}

it('challenges instead of logging in on the login-code flow', function () {
    $user = enrolTwoFactor('c1@example.com');
    $code = twoFactorCodeFor('c1@example.com');

    $this->post('/auth/login-code/verify', ['email' => 'c1@example.com', 'code' => $code])
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse();
    expect(session('login.id'))->toBe($user->getKey());
});

it('returns the json two_factor flag for json login-code requests', function () {
    enrolTwoFactor('c2@example.com');
    $code = twoFactorCodeFor('c2@example.com');

    $this->postJson('/auth/login-code/verify', ['email' => 'c2@example.com', 'code' => $code])
        ->assertOk()
        ->assertExactJson(['two_factor' => true]);

    expect(auth()->check())->toBeFalse();
});

it('carries remember into the challenge session', function () {
    enrolTwoFactor('c3@example.com');
    $code = twoFactorCodeFor('c3@example.com', ['remember' => true]);

    $this->postJson('/auth/login-code/verify', [
        'email' => 'c3@example.com', 'code' => $code,
    ])->assertOk();

    expect(session('login.remember'))->toBeTrue();
});

it('challenges instead of logging in on the magic-link flow', function () {
    enrolTwoFactor('c4@example.com');

    Notification::fake();
    $this->postJson('/auth/magic-link', ['email' => 'c4@example.com'])->assertStatus(202);

    $url = null;
    Notification::assertSentTo(
        TwoFactorUser::where('email', 'c4@example.com')->first(),
        MagicLinkNotification::class,
        function ($n) use (&$url) {
            $url = $n->url;

            return true;
        }
    );

    $this->get($url)->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse();
});

it('withholds the api token and reports 409 in api_mode', function () {
    config()->set('passwordless.api_mode', true);

    enrolTwoFactor('c5@example.com');
    $code = twoFactorCodeFor('c5@example.com');

    $response = $this->postJson('/auth/login-code/verify', ['email' => 'c5@example.com', 'code' => $code])
        ->assertStatus(409)
        ->assertExactJson(['two_factor' => true]);

    expect($response->json())->not->toHaveKey('token');
});

it('logs in normally when the user has no two factor enrolment', function () {
    TwoFactorUser::create(['email' => 'c6@example.com']);
    $code = twoFactorCodeFor('c6@example.com');

    $this->postJson('/auth/login-code/verify', ['email' => 'c6@example.com', 'code' => $code])
        ->assertNoContent();

    expect(auth()->check())->toBeTrue();
});

it('challenges instead of logging in on the magicCode code path', function () {
    config()->set('passwordless.strategies.magic_code.enabled', true);
    config()->set('passwordless.strategies.magic_code.same_browser', false);

    enrolTwoFactor('c7@example.com');

    Notification::fake();
    $this->postJson('/auth/magic-code', ['email' => 'c7@example.com'])->assertStatus(202);

    $code = null;
    Notification::assertSentTo(
        TwoFactorUser::where('email', 'c7@example.com')->first(),
        MagicCodeNotification::class,
        function ($n) use (&$code) {
            $code = $n->code;

            return true;
        }
    );

    $this->postJson('/auth/magic-code/verify', ['email' => 'c7@example.com', 'code' => $code])
        ->assertOk()
        ->assertExactJson(['two_factor' => true]);

    expect(auth()->check())->toBeFalse();
});
