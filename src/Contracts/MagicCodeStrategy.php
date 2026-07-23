<?php

namespace Webteractive\Passwordless\Contracts;

use Illuminate\Http\Request;

interface MagicCodeStrategy
{
    public function send(string $email, array $context = []): void;

    public function consume(string $token, Request $request): mixed;

    public function verify(string $email, string $code, Request $request): mixed;
}
