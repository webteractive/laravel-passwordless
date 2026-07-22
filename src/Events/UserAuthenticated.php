<?php

namespace Webteractive\Passwordless\Events;

class UserAuthenticated
{
    public function __construct(
        public readonly string $strategy,
        public readonly mixed $user,
        public readonly array $context = [],
    ) {}
}
