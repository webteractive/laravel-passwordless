<?php

namespace Webteractive\Passwordless\Strategies\MagicCode;

use RuntimeException;

class MagicCodeLockedException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Locked');
    }
}
