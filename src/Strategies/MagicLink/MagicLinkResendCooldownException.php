<?php

namespace Webteractive\Passwordless\Strategies\MagicLink;

use RuntimeException;

class MagicLinkResendCooldownException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Please wait');
    }
}
