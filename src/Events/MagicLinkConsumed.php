<?php

namespace Webteractive\Passwordless\Events;

class MagicLinkConsumed
{
    public function __construct(
        public readonly mixed $user,
        public readonly array $context = [],
    ) {}
}
