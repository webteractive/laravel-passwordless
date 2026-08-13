<?php

namespace Webteractive\Passwordless\Http\Controllers\LoginCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeInvalidException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeLockedException;
use Webteractive\Passwordless\Support\AuthCompletion;

class VerifyController
{
    public function __invoke(
        Request $request,
        LoginCodeStrategy $strategy,
        AuthCompletion $completion,
    ): JsonResponse|Response|SymfonyResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        try {
            $user = $strategy->verify($data['email'], $data['code'], $request);
        } catch (LoginCodeLockedException $e) {
            return response()->json(
                ['message' => __('passwordless::passwordless.locked'), 'retry_after' => $e->retryAfter],
                423,
                ['Retry-After' => (string) $e->retryAfter]
            );
        } catch (LoginCodeInvalidException) {
            return response()->json(['message' => __('passwordless::passwordless.invalid_or_expired')], 401);
        } catch (LoginCodeGateDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        if ($response = $completion->complete($user, $request)) {
            return $response;
        }

        return response()->noContent();
    }
}
