<?php

namespace Webteractive\Passwordless\Support;

use RuntimeException;

class ConfirmationLockedException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Locked');
    }
}
