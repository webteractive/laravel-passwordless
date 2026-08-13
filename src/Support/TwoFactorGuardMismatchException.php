<?php

namespace Webteractive\Passwordless\Support;

use RuntimeException;

/**
 * Fortify completes the two-factor challenge against config('fortify.guard'),
 * so handing off while this package logs in via a different guard would
 * authenticate the user somewhere the app does not expect.
 */
class TwoFactorGuardMismatchException extends RuntimeException
{
    public function __construct(string $passwordlessGuard, string $fortifyGuard)
    {
        parent::__construct(sprintf(
            'Cannot hand off to Fortify\'s two-factor challenge: config(\'passwordless.guard\') is '
            .'[%s] but config(\'fortify.guard\') is [%s]. Set them to the same guard.',
            $passwordlessGuard,
            $fortifyGuard,
        ));
    }
}
