<?php

namespace Webteractive\Passwordless\Testing;

use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;

class FakeMagicCodeStrategy implements MagicCodeStrategy
{
    public function __construct(protected PasswordlessFake $fake) {}

    public function send(string $email, array $context = []): void
    {
        $this->fake->recordMagicCode($email, $context);
    }

    public function consume(string $token, Request $request): mixed
    {
        return $this->fake->verifyResponse;
    }

    public function verify(string $email, string $code, Request $request): mixed
    {
        return $this->fake->verifyResponse;
    }
}
