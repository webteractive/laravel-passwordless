<?php

namespace Webteractive\Passwordless\Contracts;

use Illuminate\Http\Request;

interface LoginCodeStrategy
{
    public function send(string $email, array $context = []): void;

    public function verify(string $email, string $code, Request $request): mixed;
}
