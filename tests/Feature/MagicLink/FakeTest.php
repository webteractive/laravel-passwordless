<?php

use Webteractive\Passwordless\Facades\Passwordless;

it('asserts link sent via fake', function () {
    $fake = Passwordless::fake();

    Passwordless::magicLink()->send('foo@example.com');
    $fake->assertLinkSent('foo@example.com');
});
