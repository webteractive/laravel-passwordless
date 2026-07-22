<?php

namespace Webteractive\Passwordless\Channels;

use Webteractive\Passwordless\Contracts\LoginCodeChannel;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;

class MailLoginCodeChannel implements LoginCodeChannel
{
    public function send(mixed $user, string $email, string $code, array $context = []): void
    {
        if ($user && method_exists($user, 'notify')) {
            $ttl = (int) config('passwordless.strategies.login_code.ttl', 10 * 60);
            $user->notify(new LoginCodeNotification($code, $ttl));
        }
    }
}
