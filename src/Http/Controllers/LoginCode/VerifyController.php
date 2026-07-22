<?php

namespace Webteractive\Passwordless\Http\Controllers\LoginCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeInvalidException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeLockedException;

class VerifyController
{
    public function __invoke(Request $request, LoginCodeStrategy $strategy): JsonResponse|Response
    {
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

        if (config('passwordless.api_mode')) {
            $token = method_exists($user, 'createToken')
                ? $user->createToken('passwordless')->plainTextToken
                : null;

            return response()->json(['token' => $token, 'user' => $user]);
        }

        auth(config('passwordless.guard'))->login($user);

        return response()->noContent();
    }
}
