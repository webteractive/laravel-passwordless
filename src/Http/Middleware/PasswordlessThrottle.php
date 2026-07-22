<?php

namespace Webteractive\Passwordless\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PasswordlessThrottle
{
    public function handle(Request $request, Closure $next, string $kind = 'request'): mixed
    {
        $config = config("passwordless.throttle.{$kind}", []);

        $email = (string) ($request->input('email') ?? '');

        $checks = [];

        if (! empty($config['per_ip'])) {
            $checks[] = [
                'key' => 'passwordless:'.$kind.':ip:'.sha1($request->ip() ?? ''),
                'max' => (int) $config['per_ip']['max'],
                'window' => (int) $config['per_ip']['window'],
            ];
        }

        if ($email && ! empty($config['per_email'])) {
            $checks[] = [
                'key' => 'passwordless:'.$kind.':email:'.sha1(strtolower($email)),
                'max' => (int) $config['per_email']['max'],
                'window' => (int) $config['per_email']['window'],
            ];
        }

        // Hit every configured key on every request, then check whether any has
        // crossed its threshold. Hitting first removes the check-then-hit race
        // (two concurrent requests both passing the check before either bumps
        // the counter). Hitting all keys ensures one tripped key doesn't shield
        // the others from accumulating — otherwise an attacker could pin a
        // per-email lockout while leaving the per-IP bucket idle.
        $blocked = null;
        foreach ($checks as $c) {
            $hits = RateLimiter::hit($c['key'], $c['window']);

            if ($blocked === null && $hits > $c['max']) {
                $blocked = $c['key'];
            }
        }

        if ($blocked !== null) {
            $retry = RateLimiter::availableIn($blocked);

            return response()->json(
                ['message' => __('passwordless::passwordless.please_wait'), 'retry_after' => $retry],
                429,
                ['Retry-After' => (string) $retry]
            );
        }

        return $next($request);
    }
}
