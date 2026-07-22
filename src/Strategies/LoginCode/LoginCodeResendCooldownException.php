<?php

namespace Webteractive\Passwordless\Strategies\LoginCode;

use RuntimeException;

class LoginCodeResendCooldownException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Please wait');
    }
}
