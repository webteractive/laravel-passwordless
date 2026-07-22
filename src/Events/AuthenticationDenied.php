<?php

namespace Webteractive\Passwordless\Events;

class AuthenticationDenied
{
    public function __construct(
        public readonly string $strategy,
        public readonly mixed $user,
        public readonly string $reason,
        public readonly array $context = [],
    ) {}
}
