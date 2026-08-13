<?php

namespace Webteractive\Passwordless\Http\Controllers\DevLogin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Webteractive\Passwordless\Support\AuthCompletion;
use Webteractive\Passwordless\Support\RememberFlag;

/**
 * Signs in the selected user with no credential. Reachable only when the
 * three-condition dev_login guard in routes/web.php passed at registration.
 */
class StoreController
{
    public function __invoke(
        Request $request,
        AuthCompletion $completion,
        RememberFlag $flag,
    ): JsonResponse|Response|SymfonyResponse {
        $request->validate([
            'user' => ['required'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $model = config('passwordless.user_model');
        $user = $model::query()->find($request->input('user'));

        abort_if($user === null, 404);

        // Fire the normal funnel so audit hooks see dev logins as their own
        // strategy rather than as a magic link or login code.
        event(new UserAuthenticated('dev_login', $user));

        $skipTwoFactor = ! (bool) config('passwordless.dev_login.two_factor', false);

        if ($response = $completion->complete($user, $request, $flag->resolve($request), $skipTwoFactor)) {
            return $response;
        }

        return response()->noContent();
    }
}
