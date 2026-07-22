<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Events\LoginCodeFailed;
use Webteractive\Passwordless\Events\LoginCodeRequested;
use Webteractive\Passwordless\Events\LoginCodeVerified;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;
use Workbench\App\Models\User;

it('fires lifecycle events', function () {
    Notification::fake();
    Event::fake([LoginCodeRequested::class, LoginCodeVerified::class, UserAuthenticated::class, LoginCodeFailed::class]);

    User::create(['email' => 'evt@example.com']);
    $this->postJson('/auth/login-code', ['email' => 'evt@example.com'])->assertStatus(202);
    Event::assertDispatched(LoginCodeRequested::class);

    $code = null;
    Notification::assertSentTo(User::where('email', 'evt@example.com')->first(), LoginCodeNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $this->postJson('/auth/login-code/verify', ['email' => 'evt@example.com', 'code' => $code])->assertNoContent();
    Event::assertDispatched(LoginCodeVerified::class);
    Event::assertDispatched(UserAuthenticated::class);
});

it('fires failed event on bad code', function () {
    Event::fake([LoginCodeFailed::class]);
    User::create(['email' => 'bad@example.com']);

    $this->postJson('/auth/login-code/verify', ['email' => 'bad@example.com', 'code' => '000000'])->assertStatus(401);

    Event::assertDispatched(LoginCodeFailed::class);
});
