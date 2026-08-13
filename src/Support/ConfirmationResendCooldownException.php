<?php

namespace Webteractive\Passwordless\Support;

use RuntimeException;

class ConfirmationResendCooldownException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Please wait');
    }
}
