<?php

namespace Webteractive\Passwordless\Http\Controllers\MagicCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeInvalidException;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeLockedException;

class VerifyController
{
    public function __invoke(Request $request, MagicCodeStrategy $strategy): JsonResponse|Response
    {
        abort_unless((bool) config('passwordless.strategies.magic_code.enabled', false), 404);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        try {
            $user = $strategy->verify($data['email'], $data['code'], $request);
        } catch (MagicCodeLockedException $e) {
            return response()->json(
                ['message' => __('passwordless::passwordless.locked'), 'retry_after' => $e->retryAfter],
                423,
                ['Retry-After' => (string) $e->retryAfter]
            );
        } catch (MagicCodeInvalidException) {
            return response()->json(['message' => __('passwordless::passwordless.invalid_or_expired')], 401);
        } catch (MagicCodeGateDeniedException $e) {
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
