<?php

namespace Webteractive\Passwordless\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

interface SocialStrategy
{
    public function redirect(string $provider): RedirectResponse;

    public function callback(string $provider, Request $request): mixed;
}
