<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;
use Workbench\App\Models\User;

function captureLoginCode(string $email): string
{
    Notification::fake();
    User::firstOrCreate(['email' => $email]);
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

it('logs in on valid code', function () {
    $code = captureLoginCode('v1@example.com');

    $this->postJson('/auth/login-code/verify', ['email' => 'v1@example.com', 'code' => $code])
        ->assertNoContent();

    expect(auth()->check())->toBeTrue();
});

it('rejects wrong code', function () {
    captureLoginCode('v2@example.com');

    $this->postJson('/auth/login-code/verify', ['email' => 'v2@example.com', 'code' => '000000'])
        ->assertStatus(401);
});

it('rejects expired code', function () {
    $code = captureLoginCode('v3@example.com');
    Challenge::query()->where('type', 'code')->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/auth/login-code/verify', ['email' => 'v3@example.com', 'code' => $code])
        ->assertStatus(401);
});

it('rejects reused code', function () {
    $code = captureLoginCode('v4@example.com');

    $this->postJson('/auth/login-code/verify', ['email' => 'v4@example.com', 'code' => $code])
        ->assertNoContent();

    auth()->logout();

    $this->postJson('/auth/login-code/verify', ['email' => 'v4@example.com', 'code' => $code])
        ->assertStatus(401);
});
