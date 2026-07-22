<?php

namespace Webteractive\Passwordless\Strategies\LoginCode;

use RuntimeException;

class LoginCodeGateDeniedException extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
