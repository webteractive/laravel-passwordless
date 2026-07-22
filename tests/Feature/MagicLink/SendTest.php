<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Notifications\MagicLinkNotification;
use Workbench\App\Models\User;

it('returns 202 for known email', function () {
    Notification::fake();
    $user = User::create(['email' => 'a@example.com']);

    $this->postJson('/auth/magic-link', ['email' => 'a@example.com'])
        ->assertStatus(202)
        ->assertJson(['status' => 'sent']);

    Notification::assertSentTo($user, MagicLinkNotification::class);
});

it('returns 202 for unknown email and sends nothing', function () {
    Notification::fake();

    $this->postJson('/auth/magic-link', ['email' => 'nobody@example.com'])
        ->assertStatus(202)
        ->assertJson(['status' => 'sent']);

    Notification::assertNothingSent();
});

it('enforces resend cooldown', function () {
    Notification::fake();
    User::create(['email' => 'b@example.com']);

    $this->postJson('/auth/magic-link', ['email' => 'b@example.com'])
        ->assertStatus(202);

    $this->postJson('/auth/magic-link', ['email' => 'b@example.com'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
