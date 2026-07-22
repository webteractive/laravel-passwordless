<?php

namespace Webteractive\Passwordless\Support;

use Illuminate\Support\Facades\Cache;

class ResendCooldown
{
    public function key(string $strategy, string $email): string
    {
        return 'passwordless:cooldown:'.$strategy.':'.sha1(strtolower($email));
    }

    public function remaining(string $strategy, string $email): int
    {
        $key = $this->key($strategy, $email);
        $expiresAt = Cache::get($key);

        if (! $expiresAt) {
            return 0;
        }

        return max(0, $expiresAt - time());
    }

    public function start(string $strategy, string $email): void
    {
        $seconds = (int) config('passwordless.resend_cooldown', 30);

        if ($seconds <= 0) {
            return;
        }

        Cache::put(
            $this->key($strategy, $email),
            time() + $seconds,
            $seconds
        );
    }
}
