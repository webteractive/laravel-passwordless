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

it('resolveRedirect falls back to config when no closure is set', function () {
    config()->set('passwordless.redirect', '/home');
    /** @var Passwordless $m */
    $m = app(Passwordless::class);

    expect($m->resolveRedirect(null, request()))->toBe('/home');
});

it('resolveRedirect uses the redirectUsing closure', function () {
    /** @var Passwordless $m */
    $m = app(Passwordless::class);
    $m->redirectUsing(fn ($user, $request) => '/dashboard');

    expect($m->resolveRedirect(null, request()))->toBe('/dashboard');
});

it('resolveRedirect passes the user and request to the closure', function () {
    /** @var Passwordless $m */
    $m = app(Passwordless::class);
    $received = [];
    $m->redirectUsing(function ($user, $request) use (&$received) {
        $received = [$user, $request];

        return '/x';
    });

    $req = request();
    $m->resolveRedirect('the-user', $req);

    expect($received[0])->toBe('the-user');
    expect($received[1])->toBe($req);
});

it('resolveRedirect coerces a non-string closure return to the config default', function () {
    config()->set('passwordless.redirect', '/fallback');
    /** @var Passwordless $m */
    $m = app(Passwordless::class);
    $m->redirectUsing(fn ($user, $request) => null);

    expect($m->resolveRedirect(null, request()))->toBe('/fallback');
});
