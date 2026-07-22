<?php

namespace Webteractive\Passwordless\Events;

class LoginCodeVerified
{
    public function __construct(
        public readonly mixed $user,
        public readonly array $context = [],
    ) {}
}
