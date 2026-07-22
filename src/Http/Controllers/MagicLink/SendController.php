<?php

namespace Webteractive\Passwordless\Http\Controllers\MagicLink;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkResendCooldownException;

class SendController
{
    public function __invoke(Request $request, MagicLinkStrategy $strategy): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'intended_url' => ['nullable', 'string'],
        ]);

        try {
            $strategy->send($data['email'], [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'intended_url' => $data['intended_url'] ?? null,
            ]);
        } catch (MagicLinkResendCooldownException $e) {
            return response()->json(
                ['message' => __('passwordless::passwordless.please_wait'), 'retry_after' => $e->retryAfter],
                429,
                ['Retry-After' => (string) $e->retryAfter]
            );
        }

        return response()->json(['status' => __('passwordless::passwordless.sent')], 202);
    }
}
