<?php

namespace Webteractive\Passwordless\Http\Controllers\MagicCode;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeDifferentBrowserException;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeInvalidException;
use Webteractive\Passwordless\Support\AuthCompletion;
use Webteractive\Passwordless\Support\RememberFlag;

class ConsumeController
{
    public function __invoke(
        Request $request,
        MagicCodeStrategy $strategy,
        Passwordless $passwordless,
        AuthCompletion $completion,
        RememberFlag $flag,
        string $token,
    ): SymfonyResponse {
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

        if ($response = $completion->complete($user, $request, $flag->resolve($request))) {
            return $response;
        }

        return redirect()->intended($passwordless->resolveRedirect($user, $request));
    }
}
