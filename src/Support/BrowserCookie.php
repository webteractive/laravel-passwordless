<?php

namespace Webteractive\Passwordless\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class BrowserCookie
{
    public function name(): string
    {
        return (string) config('passwordless.browser_cookie.name', 'passwordless_browser');
    }

    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    public function make(string $value, int $ttl): Cookie
    {
        return new Cookie(
            $this->name(),
            $value,
            time() + $ttl,
            '/',
            null,
            (bool) config('passwordless.browser_cookie.secure', true),
            (bool) config('passwordless.browser_cookie.http_only', true),
            false,
            (string) config('passwordless.browser_cookie.same_site', 'lax')
        );
    }

    public function fromRequest(Request $request): ?string
    {
        $value = $request->cookie($this->name());

        return is_string($value) && $value !== '' ? $value : null;
    }
}
