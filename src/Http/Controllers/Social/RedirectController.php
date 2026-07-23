<?php

namespace Webteractive\Passwordless\Http\Controllers\Social;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Webteractive\Passwordless\Contracts\SocialStrategy;
use Webteractive\Passwordless\Strategies\Social\SocialProviderNotEnabledException;

class RedirectController
{
    public function __invoke(Request $request, string $provider, SocialStrategy $strategy): RedirectResponse
    {
        try {
            return $strategy->redirect($provider);
        } catch (SocialProviderNotEnabledException) {
            abort(404);
        }
    }
}
