<?php

use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Models\Challenge;

beforeEach(function () {
    config()->set('passwordless.strategies.magic_code.enabled', true);
    config()->set('passwordless.strategies.magic_code.same_browser', false);
});

it('logs in via the link and redirects', function () {
    $url = captureMagicCode('link@example.com')['url'];

    $this->get($url)->assertRedirect();
    expect(auth()->check())->toBeTrue();
});

it('invalidates the sibling code once the link is used', function () {
    $captured = captureMagicCode('sib@example.com');

    $this->get($captured['url'])->assertRedirect();

    // The code sibling must now be dead.
    expect(Challenge::query()->where('type', 'mc_code')->whereNull('consumed_at')->count())->toBe(0);

    auth()->logout();
    $this->postJson('/auth/magic-code/verify', ['email' => 'sib@example.com', 'code' => $captured['code']])
        ->assertStatus(401);
});

it('rejects a reused link', function () {
    $url = captureMagicCode('reuse@example.com')['url'];

    $this->get($url)->assertRedirect();
    auth()->logout();
    $this->get($url)->assertStatus(401);
});

it('rejects an expired link', function () {
    $url = captureMagicCode('exp@example.com')['url'];
    Challenge::query()->where('type', 'mc_link')->update(['expires_at' => now()->subMinute()]);

    $this->get($url)->assertStatus(401);
});

it('rejects a tampered signature', function () {
    $url = captureMagicCode('sig@example.com')['url'];

    $this->get($url.'tampered')->assertStatus(401);
});

it('honors the redirectUsing closure on link consume', function () {
    Passwordless::redirectUsing(fn ($user, $request) => '/welcome');
    $url = captureMagicCode('redir@example.com')['url'];

    $this->get($url)->assertRedirect('/welcome');
});

it('rejects the link from a different browser when same-browser is enabled', function () {
    config()->set('passwordless.strategies.magic_code.same_browser', true);

    // The send stores a browser_hash; consuming the link without the matching
    // browser cookie (a different device/browser) must be rejected.
    $url = captureMagicCode('sb@example.com')['url'];

    $this->get($url)->assertStatus(401);
    expect(auth()->check())->toBeFalse();
});
