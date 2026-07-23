<?php

namespace Webteractive\Passwordless\Strategies\Social;

use RuntimeException;

class SocialGateDeniedException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
