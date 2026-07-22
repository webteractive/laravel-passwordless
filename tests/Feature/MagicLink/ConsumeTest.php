<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\MagicLinkNotification;
use Workbench\App\Models\User;

beforeEach(function () {
    config()->set('passwordless.strategies.magic_link.same_browser', false);
});

function captureMagicLinkUrl(): string
{
    Notification::fake();
    User::firstOrCreate(['email' => 'm@example.com']);
    test()->postJson('/auth/magic-link', ['email' => 'm@example.com'])->assertStatus(202);

    $url = null;
    Notification::assertSentTo(User::where('email', 'm@example.com')->first(), MagicLinkNotification::class, function ($n) use (&$url) {
        $url = $n->url;

        return true;
    });

    return $url;
}

it('logs in on valid token (session mode)', function () {
    $url = captureMagicLinkUrl();
    $this->get($url)->assertNoContent();
    expect(auth()->check())->toBeTrue();
});

it('returns 401 for an invalid token', function () {
    $url = captureMagicLinkUrl();
    $broken = preg_replace('#/magic-link/[^?]+#', '/magic-link/'.str_repeat('a', 43), $url);
    $this->get($broken)->assertStatus(401);
});

it('rejects reused token', function () {
    $url = captureMagicLinkUrl();
    $this->get($url)->assertNoContent();

    auth()->logout();
    $this->get($url)->assertStatus(401);
});

it('rejects expired challenge', function () {
    $url = captureMagicLinkUrl();
    Challenge::query()->where('type', 'link')->update(['expires_at' => now()->subMinute()]);
    $this->get($url)->assertStatus(401);
});

it('rejects bad signature', function () {
    $url = captureMagicLinkUrl();
    $this->get($url.'tampered')->assertStatus(401);
});
