<?php

use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Support\AuthEvent;
use Webteractive\Passwordless\Support\Decision;
use Webteractive\Passwordless\Testing\FakeMagicLinkStrategy;

it('default gate allows', function () {
    /** @var Passwordless $m */
    $m = app(Passwordless::class);
    expect($m->runGate(null)->allowed)->toBeTrue();
});

it('respects gateUsing', function () {
    /** @var Passwordless $m */
    $m = app(Passwordless::class);
    $m->gateUsing(fn ($u, $c) => Passwordless::deny('nope'));

    $d = $m->runGate(null);
    expect($d->allowed)->toBeFalse();
    expect($d->reason)->toBe('nope');
});

it('runs recordUsing closure', function () {
    /** @var Passwordless $m */
    $m = app(Passwordless::class);
    $captured = null;
    $m->recordUsing(function (AuthEvent $e) use (&$captured) {
        $captured = $e;
    });

    $m->record(new AuthEvent('magic_link', 'sent'));

    expect($captured)->toBeInstanceOf(AuthEvent::class);
    expect($captured->strategy)->toBe('magic_link');
});

it('fake() swaps strategy bindings', function () {
    /** @var Passwordless $m */
    $m = app(Passwordless::class);
    $fake = $m->fake();

    expect(app(MagicLinkStrategy::class))->toBeInstanceOf(FakeMagicLinkStrategy::class);

    $m->magicLink()->send('foo@example.com');
    $fake->assertLinkSent('foo@example.com');
});

it('static allow/deny return Decision', function () {
    expect(Passwordless::allow())->toBeInstanceOf(Decision::class);
    expect(Passwordless::deny('x'))->toBeInstanceOf(Decision::class);
});
