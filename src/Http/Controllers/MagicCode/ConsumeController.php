<?php

namespace Webteractive\Passwordless\Http\Controllers\MagicCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeDifferentBrowserException;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeInvalidException;

class ConsumeController
{
    public function __invoke(Request $request, MagicCodeStrategy $strategy, Passwordless $passwordless, string $token): JsonResponse|RedirectResponse|Response
    {
        abort_unless((bool) config('passwordless.strategies.magic_code.enabled', false), 404);

        if (! $request->hasValidSignature()) {
            return response()->json(['message' => __('passwordless::passwordless.invalid_or_expired')], 401);
        }

        try {
            $user = $strategy->consume($token, $request);
        } catch (MagicCodeInvalidException) {
            return response()->json(['message' => __('passwordless::passwordless.invalid_or_expired')], 401);
        } catch (MagicCodeDifferentBrowserException) {
            return response()->json(['message' => __('passwordless::passwordless.different_browser')], 401);
        } catch (MagicCodeGateDeniedException $e) {
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

        return redirect()->intended($passwordless->resolveRedirect($user, $request));
    }
}
