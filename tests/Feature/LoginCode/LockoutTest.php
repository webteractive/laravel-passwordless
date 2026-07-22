<?php

use Workbench\App\Models\User;

it('locks the email after max failed attempts', function () {
    config()->set('passwordless.lockout.max_attempts', 3);
    config()->set('passwordless.lockout.window', 600);

    User::create(['email' => 'lock@example.com']);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/auth/login-code/verify', ['email' => 'lock@example.com', 'code' => '999999'])
            ->assertStatus(401);
    }

    $this->postJson('/auth/login-code/verify', ['email' => 'lock@example.com', 'code' => '999999'])
        ->assertStatus(423)
        ->assertHeader('Retry-After');
});
