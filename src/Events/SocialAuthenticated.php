<?php

namespace Webteractive\Passwordless\Events;

class SocialAuthenticated
{
    public function __construct(
        public readonly string $provider,
        public readonly mixed $user,
        public readonly bool $registered,
        public readonly bool $linked,
    ) {}
}
