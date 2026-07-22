<?php

use Webteractive\Passwordless\Facades\Passwordless;

it('asserts code sent via fake', function () {
    $fake = Passwordless::fake();
    Passwordless::loginCode()->send('foo@example.com');
    $fake->assertCodeSent('foo@example.com');
});
