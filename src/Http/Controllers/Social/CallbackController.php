<?php

namespace Webteractive\Passwordless\Http\Controllers\Social;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\InvalidStateException;
use Webteractive\Passwordless\Contracts\SocialStrategy;
use Webteractive\Passwordless\Strategies\Social\SocialGateDeniedException;
use Webteractive\Passwordless\Strategies\Social\SocialProviderNotEnabledException;

class CallbackController
{
    public function __invoke(Request $request, string $provider, SocialStrategy $strategy): JsonResponse|RedirectResponse
    {
        try {
            $user = $strategy->callback($provider, $request);
        } catch (SocialProviderNotEnabledException) {
            abort(404);
        } catch (SocialGateDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidStateException) {
            // Missing/expired OAuth state (CSRF token) — e.g. a stale or forged
            // callback. Fail closed with a neutral, non-leaking response.
            return response()->json(['message' => 'Invalid or expired social login attempt.'], 401);
        }

        if (config('passwordless.api_mode')) {
            $token = method_exists($user, 'createToken')
                ? $user->createToken('passwordless')->plainTextToken
                : null;

            return response()->json(['token' => $token, 'user' => $user]);
        }

        auth(config('passwordless.guard'))->login($user);

        return redirect()->intended(config('passwordless.redirect', '/'));
    }
}
