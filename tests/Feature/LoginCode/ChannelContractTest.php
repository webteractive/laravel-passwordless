<?php

use Webteractive\Passwordless\Contracts\LoginCodeChannel;
use Workbench\App\Models\User;

class InMemoryChannel implements LoginCodeChannel
{
    public static array $sent = [];

    public function send(mixed $user, string $email, string $code, array $context = []): void
    {
        self::$sent[] = compact('email', 'code');
    }
}

it('uses a custom registered channel', function () {
    config()->set('passwordless.strategies.login_code.channel', 'memory');
    app()->bind('passwordless.login_code_channels.memory', InMemoryChannel::class);

    InMemoryChannel::$sent = [];
    User::create(['email' => 'ch@example.com']);

    $this->postJson('/auth/login-code', ['email' => 'ch@example.com'])->assertStatus(202);

    expect(InMemoryChannel::$sent)->toHaveCount(1);
    expect(InMemoryChannel::$sent[0]['email'])->toBe('ch@example.com');
    expect(InMemoryChannel::$sent[0]['code'])->toMatch('/^\d{6}$/');
});
