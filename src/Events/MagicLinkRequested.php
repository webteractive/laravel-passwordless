<?php

namespace Webteractive\Passwordless\Events;

class MagicLinkRequested
{
    public function __construct(
        public readonly string $email,
        public readonly mixed $user = null,
        public readonly array $context = [],
    ) {}
}
