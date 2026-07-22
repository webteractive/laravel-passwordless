<?php

namespace Webteractive\Passwordless\Events;

class LoginCodeFailed
{
    public function __construct(
        public readonly string $email,
        public readonly string $reason,
        public readonly array $context = [],
    ) {}
}
