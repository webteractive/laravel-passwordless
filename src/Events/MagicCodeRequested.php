<?php

namespace Webteractive\Passwordless\Events;

class MagicCodeRequested
{
    public function __construct(
        public readonly string $email,
        public readonly mixed $user = null,
        public readonly array $context = [],
    ) {}
}
