<?php

namespace Webteractive\Passwordless\Http\Controllers\MagicCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeLockedException;
use Webteractive\Passwordless\Strategies\MagicCode\MagicCodeResendCooldownException;

class SendController
{
    public function __invoke(Request $request, MagicCodeStrategy $strategy): JsonResponse
    {
        abort_unless((bool) config('passwordless.strategies.magic_code.enabled', false), 404);

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
        } catch (MagicCodeResendCooldownException $e) {
            return response()->json(
                ['message' => __('passwordless::passwordless.please_wait'), 'retry_after' => $e->retryAfter],
                429,
                ['Retry-After' => (string) $e->retryAfter]
            );
        } catch (MagicCodeLockedException $e) {
            return response()->json(
                ['message' => __('passwordless::passwordless.locked'), 'retry_after' => $e->retryAfter],
                423,
                ['Retry-After' => (string) $e->retryAfter]
            );
        }

        return response()->json(['status' => __('passwordless::passwordless.sent')], 202);
    }
}
