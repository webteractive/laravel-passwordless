<?php

namespace Webteractive\Passwordless\Support;

final class AuthEvent
{
    public function __construct(
        public readonly string $strategy,
        public readonly string $action,
        public readonly mixed $user = null,
        public readonly array $context = [],
    ) {}
}
