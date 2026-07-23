<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;
use Workbench\App\Models\User;

function codeFor(string $email): string
{
    Notification::fake();
    test()->postJson('/auth/login-code', ['email' => $email])->assertStatus(202);

    $code = null;
    Notification::assertSentTo(
        User::where('email', $email)->first(),
        LoginCodeNotification::class,
        function ($n) use (&$code) {
            $code = $n->code;

            return true;
        }
    );

    return $code;
}

it('does not auto-create a user whose domain is not allowed (register enforced)', function () {
    config()->set('passwordless.auto_create_users', true);
    config()->set('passwordless.domains.allowed', ['acme.com']);
    config()->set('passwordless.domains.enforce.passwordless.register', true);

    Notification::fake();
    $this->postJson('/auth/login-code', ['email' => 'new@other.com'])->assertStatus(202);

    expect(User::where('email', 'new@other.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('denies login for an existing user on a disallowed domain (login enforced)', function () {
    User::create(['email' => 'bob@other.com', 'name' => 'Bob', 'password' => bcrypt('x')]);

    config()->set('passwordless.domains.allowed', ['acme.com']);
    config()->set('passwordless.domains.enforce.passwordless.login', true);

    $code = codeFor('bob@other.com');

    $this->postJson('/auth/login-code/verify', ['email' => 'bob@other.com', 'code' => $code])
        ->assertStatus(403);

    expect(auth()->check())->toBeFalse();
});

it('allows an allowed-domain user through', function () {
    User::create(['email' => 'ada@acme.com', 'name' => 'Ada', 'password' => bcrypt('x')]);

    config()->set('passwordless.domains.allowed', ['acme.com']);
    config()->set('passwordless.domains.enforce.passwordless.login', true);

    $code = codeFor('ada@acme.com');

    $this->postJson('/auth/login-code/verify', ['email' => 'ada@acme.com', 'code' => $code])
        ->assertNoContent();

    expect(auth()->check())->toBeTrue();
});
