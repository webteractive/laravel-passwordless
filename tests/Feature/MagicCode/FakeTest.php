<?php

use Webteractive\Passwordless\Facades\Passwordless;

it('asserts a magic code was sent via the fake', function () {
    $fake = Passwordless::fake();

    Passwordless::magicCode()->send('foo@example.com');

    $fake->assertMagicCodeSent('foo@example.com');
});
