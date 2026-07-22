<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Events\MagicLinkConsumed;
use Webteractive\Passwordless\Events\MagicLinkRequested;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Webteractive\Passwordless\Notifications\MagicLinkNotification;
use Workbench\App\Models\User;

it('fires lifecycle events', function () {
    config()->set('passwordless.strategies.magic_link.same_browser', false);
    Notification::fake();
    Event::fake([MagicLinkRequested::class, MagicLinkConsumed::class, UserAuthenticated::class]);

    User::firstOrCreate(['email' => 'e@example.com']);
    $this->postJson('/auth/magic-link', ['email' => 'e@example.com'])->assertStatus(202);
    Event::assertDispatched(MagicLinkRequested::class);

    $url = null;
    Notification::assertSentTo(User::where('email', 'e@example.com')->first(), MagicLinkNotification::class, function ($n) use (&$url) {
        $url = $n->url;

        return true;
    });

    $this->get($url)->assertNoContent();
    Event::assertDispatched(MagicLinkConsumed::class);
    Event::assertDispatched(UserAuthenticated::class);
});
