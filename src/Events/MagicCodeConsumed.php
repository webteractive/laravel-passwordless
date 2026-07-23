<?php

namespace Webteractive\Passwordless\Events;

class MagicCodeConsumed
{
    public function __construct(
        public readonly mixed $user,
        public readonly array $context = [],
    ) {}
}
