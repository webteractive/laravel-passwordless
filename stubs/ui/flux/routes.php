<?php

/*
|--------------------------------------------------------------------------
| Passwordless UI — example login route (Livewire Volt + Flux, published stub)
|--------------------------------------------------------------------------
|
| Published by:  php artisan vendor:publish --tag=passwordless-ui-flux
| Target path:   routes/passwordless-ui.php
|
| The package registers its JSON endpoints but does NOT register any page route.
| This Volt component drives the flow server-side via the package's public API,
| so no JSON calls are involved — just point a Volt route at the component.
|
| Require this file from bootstrap/app.php (see the Blade stub's routes file for
| the withRouting example) or paste the route into routes/web.php.
|
*/

use Livewire\Volt\Volt;

Volt::route('login', 'passwordless.login')
    ->middleware('web')
    ->name('login');
