<?php

namespace Webteractive\Passwordless\Testing;

use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;

class FakeLoginCodeStrategy implements LoginCodeStrategy
{
    public function __construct(protected PasswordlessFake $fake) {}

    public function send(string $email, array $context = []): void
    {
        $this->fake->recordCode($email, $context);
    }

    public function verify(string $email, string $code, Request $request): mixed
    {
        return $this->fake->verifyResponse;
    }
}
