<?php

namespace Webteractive\Passwordless\Http\Controllers\Social;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Webteractive\Passwordless\Contracts\SocialStrategy;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Strategies\Social\SocialGateDeniedException;
use Webteractive\Passwordless\Strategies\Social\SocialProviderNotEnabledException;
use Webteractive\Passwordless\Support\AuthCompletion;

class CallbackController
{
    public function __invoke(
        Request $request,
        string $provider,
        SocialStrategy $strategy,
        Passwordless $passwordless,
        AuthCompletion $completion,
    ): JsonResponse|RedirectResponse|SymfonyResponse {
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

        if ($response = $completion->complete($user, $request)) {
            return $response;
        }

        return redirect()->intended($passwordless->resolveRedirect($user, $request));
    }
}
