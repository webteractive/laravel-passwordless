<?php

namespace Webteractive\Passwordless\Strategies\MagicLink;

use RuntimeException;

class MagicLinkGateDeniedException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
