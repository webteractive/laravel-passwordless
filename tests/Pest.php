<?php

use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Notifications\MagicCodeNotification;
use Webteractive\Passwordless\Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Send a magicCode to $email and return ['url' => ..., 'code' => ...] captured
 * from the single combined notification. Assumes strategies.magic_code.enabled.
 *
 * @return array{url: string, code: string}
 */
function captureMagicCode(string $email): array
{
    Notification::fake();
    User::firstOrCreate(['email' => $email]);
    test()->postJson('/auth/magic-code', ['email' => $email])->assertStatus(202);

    $captured = ['url' => '', 'code' => ''];
    Notification::assertSentTo(
        User::where('email', $email)->first(),
        MagicCodeNotification::class,
        function ($n) use (&$captured) {
            $captured = ['url' => $n->url, 'code' => $n->code];

            return true;
        }
    );

    return $captured;
}
