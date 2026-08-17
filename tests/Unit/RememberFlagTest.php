<?php

use Illuminate\Http\Request;
use Webteractive\Passwordless\Support\RememberFlag;

it('is enabled by default and disabled by config', function () {
    expect((new RememberFlag)->enabled())->toBeTrue();

    config()->set('passwordless.remember.enabled', false);

    expect((new RememberFlag)->enabled())->toBeFalse();
});

it('resolves false when nothing is supplied', function () {
    expect((new RememberFlag)->resolve(Request::create('/', 'POST')))->toBeFalse();
});

it('resolves a remember key on the request', function () {
    $request = Request::create('/', 'POST', ['remember' => '1']);

    expect((new RememberFlag)->resolve($request))->toBeTrue();
});

it('resolves from challenge metadata when the request carries nothing', function () {
    $flag = new RememberFlag;

    expect($flag->resolve(Request::create('/', 'GET'), ['remember' => true]))->toBeTrue();
    expect($flag->resolve(Request::create('/', 'GET'), ['remember' => false]))->toBeFalse();
    expect($flag->resolve(Request::create('/', 'GET'), []))->toBeFalse();
});

it('lets a request key override challenge metadata', function () {
    $request = Request::create('/', 'POST', ['remember' => '0']);

    expect((new RememberFlag)->resolve($request, ['remember' => true]))->toBeFalse();
});

it('round-trips a stashed value and prefers it over metadata', function () {
    $flag = new RememberFlag;
    $request = Request::create('/', 'GET');

    expect($flag->stashed($request))->toBeNull();

    $flag->stash($request, true);

    expect($flag->stashed($request))->toBeTrue();
    expect($flag->resolve($request, ['remember' => false]))->toBeTrue();
});

it('forces false everywhere when disabled', function () {
    config()->set('passwordless.remember.enabled', false);

    $flag = new RememberFlag;
    $request = Request::create('/', 'POST', ['remember' => '1']);
    $flag->stash($request, true);

    expect($flag->resolve($request, ['remember' => true]))->toBeFalse();
    expect($flag->fromContext(['remember' => true]))->toBeFalse();
});

it('lets an explicit verify-request key outrank the stashed value', function () {
    $flag = new RememberFlag;
    $request = Request::create('/', 'POST', ['remember' => '0']);
    $flag->stash($request, true);

    // resolve() prefers the stash; resolveForVerify() prefers this request.
    expect($flag->resolve($request))->toBeTrue();
    expect($flag->resolveForVerify($request))->toBeFalse();
});

it('falls back to the stashed value when the verify request says nothing', function () {
    $flag = new RememberFlag;
    $request = Request::create('/', 'POST');
    $flag->stash($request, true);

    expect($flag->resolveForVerify($request))->toBeTrue();
});

it('forces verify resolution to false when disabled', function () {
    config()->set('passwordless.remember.enabled', false);

    $request = Request::create('/', 'POST', ['remember' => '1']);

    expect((new RememberFlag)->resolveForVerify($request))->toBeFalse();
});

it('reads a remember flag out of a strategy context array', function () {
    expect((new RememberFlag)->fromContext(['remember' => true]))->toBeTrue();
    expect((new RememberFlag)->fromContext([]))->toBeFalse();
});
