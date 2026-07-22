<?php

namespace Webteractive\Passwordless\Testing;

use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;

class FakeMagicLinkStrategy implements MagicLinkStrategy
{
    public function __construct(protected PasswordlessFake $fake) {}

    public function send(string $email, array $context = []): void
    {
        $this->fake->recordLink($email, $context);
    }

    public function consume(string $token, Request $request): mixed
    {
        return $this->fake->verifyResponse;
    }
}
