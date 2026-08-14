<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;
use Workbench\App\Models\User;

function captureRememberCode(string $email, array $payload = []): string
{
    Notification::fake();
    User::firstOrCreate(['email' => $email]);
    test()->postJson('/auth/login-code', array_merge(['email' => $email], $payload))
        ->assertStatus(202);

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

it('persists remember into the challenge metadata at send time', function () {
    captureRememberCode('lcr1@example.com', ['remember' => true]);

    expect(Challenge::where('type', 'code')->latest('id')->first()->metadata['remember'])->toBeTrue();
});

it('issues a remember token using the flag stored at send time', function () {
    $code = captureRememberCode('lcr2@example.com', ['remember' => true]);

    $this->postJson('/auth/login-code/verify', ['email' => 'lcr2@example.com', 'code' => $code])
        ->assertNoContent();

    expect(User::where('email', 'lcr2@example.com')->first()->remember_token)->not->toBeNull();
});

it('lets the verify request override the stored flag', function () {
    $code = captureRememberCode('lcr3@example.com', ['remember' => true]);

    $this->postJson('/auth/login-code/verify', [
        'email' => 'lcr3@example.com',
        'code' => $code,
        'remember' => false,
    ])->assertNoContent();

    expect(User::where('email', 'lcr3@example.com')->first()->remember_token)->toBeNull();
});

it('lets the verify request opt in when send did not', function () {
    $code = captureRememberCode('lcr4@example.com');

    $this->postJson('/auth/login-code/verify', [
        'email' => 'lcr4@example.com',
        'code' => $code,
        'remember' => true,
    ])->assertNoContent();

    expect(User::where('email', 'lcr4@example.com')->first()->remember_token)->not->toBeNull();
});

it('ignores remember when the capability is disabled', function () {
    config()->set('passwordless.remember.enabled', false);

    $code = captureRememberCode('lcr5@example.com', ['remember' => true]);

    $this->postJson('/auth/login-code/verify', [
        'email' => 'lcr5@example.com',
        'code' => $code,
        'remember' => true,
    ])->assertNoContent();

    expect(User::where('email', 'lcr5@example.com')->first()->remember_token)->toBeNull();
});
