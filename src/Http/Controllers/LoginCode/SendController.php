<?php

namespace Webteractive\Passwordless\Http\Controllers\LoginCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeResendCooldownException;

class SendController
{
    public function __invoke(Request $request, LoginCodeStrategy $strategy): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'remember' => ['nullable', 'boolean'],
        ]);

        try {
            $strategy->send($data['email'], [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'remember' => $request->boolean('remember'),
            ]);
        } catch (LoginCodeResendCooldownException $e) {
            return response()->json(
                ['message' => __('passwordless::passwordless.please_wait'), 'retry_after' => $e->retryAfter],
                429,
                ['Retry-After' => (string) $e->retryAfter]
            );
        }

        return response()->json(['status' => __('passwordless::passwordless.sent')], 202);
    }
}
