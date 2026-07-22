<?php

namespace Webteractive\Passwordless\Support;

use Illuminate\Support\Facades\Cache;

class Lockout
{
    public function attemptsKey(string $strategy, string $email): string
    {
        return 'passwordless:lockout:attempts:'.$strategy.':'.sha1(strtolower($email));
    }

    public function lockKey(string $strategy, string $email): string
    {
        return 'passwordless:lockout:locked:'.$strategy.':'.sha1(strtolower($email));
    }

    public function lockedFor(string $strategy, string $email): int
    {
        $expiresAt = Cache::get($this->lockKey($strategy, $email));

        return $expiresAt ? max(0, $expiresAt - time()) : 0;
    }

    public function recordFailure(string $strategy, string $email): void
    {
        $max = (int) config('passwordless.lockout.max_attempts', 5);
        $window = (int) config('passwordless.lockout.window', 15 * 60);

        $attempts = (int) Cache::get($this->attemptsKey($strategy, $email), 0) + 1;
        Cache::put($this->attemptsKey($strategy, $email), $attempts, $window);

        if ($attempts >= $max) {
            Cache::put($this->lockKey($strategy, $email), time() + $window, $window);
            Cache::forget($this->attemptsKey($strategy, $email));
        }
    }

    public function clear(string $strategy, string $email): void
    {
        Cache::forget($this->attemptsKey($strategy, $email));
        Cache::forget($this->lockKey($strategy, $email));
    }
}
