<?php

namespace Webteractive\Passwordless\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands a verified passwordless user off to Laravel Fortify's two-factor
 * challenge.
 *
 * Fortify is an optional dependency, so every touchpoint is guarded by
 * class_exists() and resolved through a fully-qualified string rather than
 * assumed present. required() short-circuits on the first check when Fortify is
 * absent, which is what keeps behaviour identical for apps that don't use it.
 */
class TwoFactor
{
    public function required(mixed $user): bool
    {
        if (! class_exists(Fortify::class)) {
            return false;
        }

        if (! $user || ! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            return false;
        }

        return (bool) $user->hasEnabledTwoFactorAuthentication();
    }

    public function challenge(mixed $user, Request $request, bool $remember = false): Response
    {
        $passwordlessGuard = (string) config('passwordless.guard', 'web');
        $fortifyGuard = (string) config('fortify.guard', 'web');

        // Both checks run BEFORE anything is written to the session, so a
        // failure leaves no half-finished challenge behind.
        if ($passwordlessGuard !== $fortifyGuard) {
            throw new TwoFactorGuardMismatchException($passwordlessGuard, $fortifyGuard);
        }

        if (! Route::has('two-factor.login')) {
            throw new TwoFactorChallengeUnavailableException;
        }

        // Exactly the contract Fortify's own RedirectIfTwoFactorAuthenticatable
        // writes, so Fortify's TwoFactorLoginRequest picks the user up unchanged
        // — including recovery codes and the remember flag.
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $remember,
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->route('two-factor.login');
    }
}
