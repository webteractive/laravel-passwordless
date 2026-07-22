<?php

namespace Webteractive\Passwordless\Contracts;

interface LoginCodeChannel
{
    public function send(mixed $user, string $email, string $code, array $context = []): void;
}
