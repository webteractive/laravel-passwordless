<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Webteractive\Passwordless\Events\AuthenticationDenied;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Notifications\MagicLinkNotification;
use Workbench\App\Models\User;

it('returns 403 when gate denies', function () {
    config()->set('passwordless.strategies.magic_link.same_browser', false);
    Notification::fake();
    Event::fake([AuthenticationDenied::class]);

    Passwordless::gateUsing(fn ($u, $c) => Passwordless::deny('account disabled'));

    User::firstOrCreate(['email' => 'd@example.com']);
    $this->postJson('/auth/magic-link', ['email' => 'd@example.com'])->assertStatus(202);

    $url = null;
    Notification::assertSentTo(User::where('email', 'd@example.com')->first(), MagicLinkNotification::class, function ($n) use (&$url) {
        $url = $n->url;

        return true;
    });

    $this->get($url)->assertStatus(403)->assertJson(['message' => 'account disabled']);
    Event::assertDispatched(AuthenticationDenied::class);
});
