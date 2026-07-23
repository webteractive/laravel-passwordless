<?php

namespace Webteractive\Passwordless\Strategies\MagicCode;

use RuntimeException;

class MagicCodeResendCooldownException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Please wait');
    }
}
