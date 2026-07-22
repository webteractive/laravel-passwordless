<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;
use Workbench\App\Models\User;

it('returns 202 and sends to known email', function () {
    Notification::fake();
    $user = User::create(['email' => 'a@example.com']);

    $this->postJson('/auth/login-code', ['email' => 'a@example.com'])
        ->assertStatus(202)
        ->assertJson(['status' => 'sent']);

    Notification::assertSentTo($user, LoginCodeNotification::class);
});

it('returns 202 for unknown email and sends nothing', function () {
    Notification::fake();
    $this->postJson('/auth/login-code', ['email' => 'nobody@example.com'])
        ->assertStatus(202);

    Notification::assertNothingSent();
});

it('enforces resend cooldown', function () {
    Notification::fake();
    User::create(['email' => 'b@example.com']);

    $this->postJson('/auth/login-code', ['email' => 'b@example.com'])->assertStatus(202);
    $this->postJson('/auth/login-code', ['email' => 'b@example.com'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
