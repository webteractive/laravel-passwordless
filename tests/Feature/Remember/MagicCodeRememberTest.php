<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\MagicCodeNotification;
use Workbench\App\Models\User;

beforeEach(function () {
    config()->set('passwordless.strategies.magic_code.enabled', true);
    // Same-browser enforcement is orthogonal to remember-me and covered by the
    // magicCode suite; disabling it keeps these assertions on the flag alone.
    config()->set('passwordless.strategies.magic_code.same_browser', false);
});

function captureRememberPair(string $email, array $payload = []): array
{
    Notification::fake();
    User::firstOrCreate(['email' => $email]);
    test()->postJson('/auth/magic-code', array_merge(['email' => $email], $payload))
        ->assertStatus(202);

    $captured = ['url' => '', 'code' => ''];
    Notification::assertSentTo(
        User::where('email', $email)->first(),
        MagicCodeNotification::class,
        function ($n) use (&$captured) {
            $captured = ['url' => $n->url, 'code' => $n->code];

            return true;
        }
    );

    return $captured;
}

it('writes remember into both sibling rows', function () {
    captureRememberPair('mcr1@example.com', ['remember' => true]);

    expect(Challenge::where('type', 'mc_link')->latest('id')->first()->metadata['remember'])->toBeTrue();
    expect(Challenge::where('type', 'mc_code')->latest('id')->first()->metadata['remember'])->toBeTrue();
});

it('issues a remember token on the link path', function () {
    $pair = captureRememberPair('mcr2@example.com', ['remember' => true]);

    $this->get($pair['url'])->assertRedirect();

    expect(User::where('email', 'mcr2@example.com')->first()->remember_token)->not->toBeNull();
});

it('issues a remember token on the code path', function () {
    $pair = captureRememberPair('mcr3@example.com', ['remember' => true]);

    $this->postJson('/auth/magic-code/verify', ['email' => 'mcr3@example.com', 'code' => $pair['code']])
        ->assertNoContent();

    expect(User::where('email', 'mcr3@example.com')->first()->remember_token)->not->toBeNull();
});

it('lets the code verify request override the stored flag', function () {
    $pair = captureRememberPair('mcr4@example.com', ['remember' => true]);

    $this->postJson('/auth/magic-code/verify', [
        'email' => 'mcr4@example.com',
        'code' => $pair['code'],
        'remember' => false,
    ])->assertNoContent();

    expect(User::where('email', 'mcr4@example.com')->first()->remember_token)->toBeNull();
});
