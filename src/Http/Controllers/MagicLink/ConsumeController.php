<?php

namespace Webteractive\Passwordless\Http\Controllers\MagicLink;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkDifferentBrowserException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkGateDeniedException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkInvalidException;

class ConsumeController
{
    public function __invoke(Request $request, MagicLinkStrategy $strategy, string $token): JsonResponse|Response
    {
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

        if (config('passwordless.api_mode')) {
            $token = method_exists($user, 'createToken')
                ? $user->createToken('passwordless')->plainTextToken
                : null;

            return response()->json([
                'token' => $token,
                'user' => $user,
            ]);
        }

        auth(config('passwordless.guard'))->login($user);

        return response()->noContent();
    }
}
