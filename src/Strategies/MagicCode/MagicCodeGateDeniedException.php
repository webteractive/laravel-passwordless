<?php

namespace Webteractive\Passwordless\Strategies\MagicCode;

use RuntimeException;

class MagicCodeGateDeniedException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
