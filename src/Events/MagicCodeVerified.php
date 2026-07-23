<?php

namespace Webteractive\Passwordless\Events;

class MagicCodeVerified
{
    public function __construct(
        public readonly mixed $user,
        public readonly array $context = [],
    ) {}
}
