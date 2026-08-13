<?php

namespace Webteractive\Passwordless\Http\Controllers\MagicLink;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkDifferentBrowserException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkGateDeniedException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkInvalidException;
use Webteractive\Passwordless\Support\AuthCompletion;
use Webteractive\Passwordless\Support\RememberFlag;

class ConsumeController
{
    public function __invoke(
        Request $request,
        MagicLinkStrategy $strategy,
        AuthCompletion $completion,
        RememberFlag $flag,
        string $token,
    ): JsonResponse|Response|SymfonyResponse {
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => __('passwordless::passwordless.invalid_or_expired')], 401);
        }

        try {
            $user = $strategy->consume($token, $request);
        } catch (MagicLinkInvalidException) {
            return response()->json(['message' => __('passwordless::passwordless.invalid_or_expired')], 401);
        } catch (MagicLinkDifferentBrowserException) {
            return response()->json(['message' => __('passwordless::passwordless.different_browser')], 401);
        } catch (MagicLinkGateDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($response = $completion->complete($user, $request, $flag->resolve($request))) {
            return $response;
        }

        return response()->noContent();
    }
}
