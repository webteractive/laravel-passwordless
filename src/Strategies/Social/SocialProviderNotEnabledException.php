<?php

namespace Webteractive\Passwordless\Strategies\Social;

use RuntimeException;

class SocialProviderNotEnabledException extends RuntimeException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct("Social provider [{$provider}] is not enabled.");
    }
}
