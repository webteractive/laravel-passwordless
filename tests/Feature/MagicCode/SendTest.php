<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\MagicCodeNotification;
use Workbench\App\Models\User;

beforeEach(function () {
    config()->set('passwordless.strategies.magic_code.enabled', true);
    config()->set('passwordless.strategies.magic_code.same_browser', false);
});

it('404s when the strategy is disabled', function () {
    config()->set('passwordless.strategies.magic_code.enabled', false);

    $this->postJson('/auth/magic-code', ['email' => 'x@example.com'])->assertNotFound();
});

it('returns 202 and sends a single combined email to a known user', function () {
    Notification::fake();
    $user = User::create(['email' => 'a@example.com']);

    $this->postJson('/auth/magic-code', ['email' => 'a@example.com'])
        ->assertStatus(202)
        ->assertJson(['status' => 'sent']);

    Notification::assertSentToTimes($user, MagicCodeNotification::class, 1);
});

it('creates two correlated challenge rows sharing id and expiry', function () {
    Notification::fake();
    User::create(['email' => 'pair@example.com']);

    $this->postJson('/auth/magic-code', ['email' => 'pair@example.com'])->assertStatus(202);

    $link = Challenge::query()->where('type', 'mc_link')->first();
    $code = Challenge::query()->where('type', 'mc_code')->first();

    expect($link)->not->toBeNull();
    expect($code)->not->toBeNull();
    expect($link->metadata['magic_code_id'])->toBe($code->metadata['magic_code_id']);
    expect($link->expires_at->timestamp)->toBe($code->expires_at->timestamp);
});

it('returns 202 for an unknown email and sends nothing', function () {
    Notification::fake();

    $this->postJson('/auth/magic-code', ['email' => 'nobody@example.com'])->assertStatus(202);

    Notification::assertNothingSent();
    expect(Challenge::query()->whereIn('type', ['mc_link', 'mc_code'])->count())->toBe(0);
});

it('enforces the resend cooldown', function () {
    Notification::fake();
    User::create(['email' => 'b@example.com']);

    $this->postJson('/auth/magic-code', ['email' => 'b@example.com'])->assertStatus(202);
    $this->postJson('/auth/magic-code', ['email' => 'b@example.com'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('retires a prior unconsumed pair when re-sent', function () {
    Notification::fake();
    User::create(['email' => 'reset@example.com']);

    $this->postJson('/auth/magic-code', ['email' => 'reset@example.com'])->assertStatus(202);
    // Bypass the cooldown to force a second send.
    cache()->flush();
    $this->postJson('/auth/magic-code', ['email' => 'reset@example.com'])->assertStatus(202);

    expect(Challenge::query()->where('type', 'mc_link')->whereNull('consumed_at')->count())->toBe(1);
    expect(Challenge::query()->where('type', 'mc_code')->whereNull('consumed_at')->count())->toBe(1);
});
