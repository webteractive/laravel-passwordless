<?php

use Webteractive\Passwordless\Models\Challenge;

beforeEach(function () {
    config()->set('passwordless.strategies.magic_code.enabled', true);
    config()->set('passwordless.strategies.magic_code.same_browser', false);
});

it('404s when the strategy is disabled', function () {
    config()->set('passwordless.strategies.magic_code.enabled', false);

    $this->postJson('/auth/magic-code/verify', ['email' => 'x@example.com', 'code' => '000000'])
        ->assertNotFound();
});

it('logs in via the code', function () {
    $code = captureMagicCode('c1@example.com')['code'];

    $this->postJson('/auth/magic-code/verify', ['email' => 'c1@example.com', 'code' => $code])
        ->assertNoContent();

    expect(auth()->check())->toBeTrue();
});

it('invalidates the sibling link once the code is used', function () {
    $captured = captureMagicCode('c2@example.com');

    $this->postJson('/auth/magic-code/verify', ['email' => 'c2@example.com', 'code' => $captured['code']])
        ->assertNoContent();

    expect(Challenge::query()->where('type', 'mc_link')->whereNull('consumed_at')->count())->toBe(0);

    auth()->logout();
    $this->get($captured['url'])->assertStatus(401);
});

it('works from a different browser (code path is device-agnostic)', function () {
    // same_browser applies only to the link; the code must work with no cookie.
    config()->set('passwordless.strategies.magic_code.same_browser', true);
    $code = captureMagicCode('c3@example.com')['code'];

    $this->postJson('/auth/magic-code/verify', ['email' => 'c3@example.com', 'code' => $code])
        ->assertNoContent();

    expect(auth()->check())->toBeTrue();
});

it('rejects a wrong code', function () {
    captureMagicCode('c4@example.com');

    $this->postJson('/auth/magic-code/verify', ['email' => 'c4@example.com', 'code' => '000000'])
        ->assertStatus(401);
});

it('rejects an expired code', function () {
    $code = captureMagicCode('c5@example.com')['code'];
    Challenge::query()->where('type', 'mc_code')->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/auth/magic-code/verify', ['email' => 'c5@example.com', 'code' => $code])
        ->assertStatus(401);
});

it('rejects a reused code', function () {
    $code = captureMagicCode('c6@example.com')['code'];

    $this->postJson('/auth/magic-code/verify', ['email' => 'c6@example.com', 'code' => $code])
        ->assertNoContent();
    auth()->logout();

    $this->postJson('/auth/magic-code/verify', ['email' => 'c6@example.com', 'code' => $code])
        ->assertStatus(401);
});

it('locks the email after repeated failures', function () {
    captureMagicCode('c7@example.com');

    foreach (range(1, 5) as $i) {
        $this->postJson('/auth/magic-code/verify', ['email' => 'c7@example.com', 'code' => '000000'])
            ->assertStatus(401);
    }

    $this->postJson('/auth/magic-code/verify', ['email' => 'c7@example.com', 'code' => '000000'])
        ->assertStatus(423)
        ->assertHeader('Retry-After');
});
