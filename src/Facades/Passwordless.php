<?php

namespace Webteractive\Passwordless\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Webteractive\Passwordless\Passwordless
 */
class Passwordless extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Webteractive\Passwordless\Passwordless::class;
    }
}
