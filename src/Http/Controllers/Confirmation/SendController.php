<?php

namespace Webteractive\Passwordless\Http\Controllers\Confirmation;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webteractive\Passwordless\Support\ConfirmationLockedException;
use Webteractive\Passwordless\Support\ConfirmationResendCooldownException;
use Webteractive\Passwordless\Support\IdentityConfirmation;

class SendController
{
    public function __invoke(Request $request, IdentityConfirmation $confirmation): JsonResponse
    {
        abort_unless((bool) config('passwordless.confirmation.enabled', true), 404);

        try {
            $confirmation->send($request->user(), [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (ConfirmationResendCooldownException|ConfirmationLockedException $e) {
            return response()->json(
                ['message' => __('passwordless::passwordless.please_wait'), 'retry_after' => $e->retryAfter],
                429,
                ['Retry-After' => (string) $e->retryAfter]
            );
        }

        return response()->json(['status' => __('passwordless::passwordless.sent')], 202);
    }
}
