<?php

namespace Webteractive\Passwordless\Support;

use RuntimeException;

/**
 * Thrown when a user requires a two-factor challenge but Fortify cannot deliver
 * one (its two-factor feature is disabled, so `two-factor.login` is not
 * registered). Failing loudly is deliberate: falling through to login() would
 * silently bypass the user's second factor.
 */
class TwoFactorChallengeUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'This user has two-factor authentication enabled, but Fortify\'s [two-factor.login] '
            .'route is not registered. Enable Features::twoFactorAuthentication() in config/fortify.php, '
            .'or disable two-factor for this user.'
        );
    }
}
