<?php

use Webteractive\Passwordless\Support\Decision;

it('allows', function () {
    $d = Decision::allow();
    expect($d->allowed)->toBeTrue();
    expect($d->reason)->toBeNull();
});

it('denies with reason', function () {
    $d = Decision::deny('account disabled');
    expect($d->allowed)->toBeFalse();
    expect($d->reason)->toBe('account disabled');
});
