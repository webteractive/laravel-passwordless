<?php

namespace Webteractive\Passwordless\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single place a passwordless flow turns a verified user into a session (or
 * an API token). Every strategy controller funnels through here, so behaviour
 * that must apply to *all* logins — the api_mode token shape, the Fortify 2FA
 * challenge — is written once.
 *
 * Returning null means "the user is logged in, emit your own success response".
 * Returning a Response means "return this verbatim; do not log anyone in".
 */
class AuthCompletion
{
    public function complete(
        mixed $user,
        Request $request,
        bool $remember = false,
        bool $skipTwoFactor = false,
    ): ?Response {
        if (config('passwordless.api_mode')) {
            $token = method_exists($user, 'createToken')
                ? $user->createToken('passwordless')->plainTextToken
                : null;

            return response()->json(['token' => $token, 'user' => $user]);
        }

        // SessionGuard::login() rotates the session id itself (updateSession ->
        // regenerate(true)), so no explicit regeneration is needed here.
        auth(config('passwordless.guard'))->login($user, $remember);

        return null;
    }
}
