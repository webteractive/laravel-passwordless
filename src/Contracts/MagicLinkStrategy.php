<?php

namespace Webteractive\Passwordless\Contracts;

use Illuminate\Http\Request;

interface MagicLinkStrategy
{
    public function send(string $email, array $context = []): void;

    public function consume(string $token, Request $request): mixed;
}
