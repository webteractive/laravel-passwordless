<?php

namespace Webteractive\Passwordless\Support;

use RuntimeException;

class DomainNotAllowedException extends RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct('domain_not_allowed');
    }
}
