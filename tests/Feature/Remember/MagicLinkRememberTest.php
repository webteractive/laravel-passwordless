<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\MagicLinkNotification;
use Workbench\App\Models\User;

// The same-browser cookie is orthogonal to remember-me and is covered by the
// magic-link suite; disabling it here keeps these assertions on the flag alone.
beforeEach(function () {
    config()->set('passwordless.strategies.magic_link.same_browser', false);
});

function captureRememberLink(string $email, array $payload = []): string
{
    Notification::fake();
    User::firstOrCreate(['email' => $email]);
    test()->postJson('/auth/magic-link', array_merge(['email' => $email], $payload))
        ->assertStatus(202);

    $url = null;
    Notification::assertSentTo(
        User::where('email', $email)->first(),
        MagicLinkNotification::class,
        function ($n) use (&$url) {
            $url = $n->url;

            return true;
        }
    );

    return $url;
}

it('persists remember into the challenge metadata at send time', function () {
    captureRememberLink('mlr1@example.com', ['remember' => true]);

    $challenge = Challenge::where('type', 'link')->latest('id')->first();

    expect($challenge->metadata['remember'])->toBeTrue();
});

it('stores remember false when the flag is absent', function () {
    captureRememberLink('mlr2@example.com');

    $challenge = Challenge::where('type', 'link')->latest('id')->first();

    expect($challenge->metadata['remember'])->toBeFalse();
});

it('issues a remember token when the link is consumed', function () {
    $url = captureRememberLink('mlr3@example.com', ['remember' => true]);

    $this->get($url)->assertNoContent();

    expect(auth()->check())->toBeTrue();
    expect(User::where('email', 'mlr3@example.com')->first()->remember_token)->not->toBeNull();
});

it('does not issue a remember token when remember was not requested', function () {
    $url = captureRememberLink('mlr4@example.com');

    $this->get($url)->assertNoContent();

    expect(User::where('email', 'mlr4@example.com')->first()->remember_token)->toBeNull();
});

it('ignores remember entirely when the capability is disabled', function () {
    config()->set('passwordless.remember.enabled', false);

    $url = captureRememberLink('mlr5@example.com', ['remember' => true]);

    $challenge = Challenge::where('type', 'link')->latest('id')->first();
    expect($challenge->metadata['remember'])->toBeFalse();

    $this->get($url)->assertNoContent();
    expect(User::where('email', 'mlr5@example.com')->first()->remember_token)->toBeNull();
});
