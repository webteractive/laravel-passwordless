<?php

namespace Webteractive\Passwordless\Http\Controllers\Social;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Webteractive\Passwordless\Contracts\SocialStrategy;
use Webteractive\Passwordless\Strategies\Social\SocialProviderNotEnabledException;
use Webteractive\Passwordless\Support\RememberFlag;

class RedirectController
{
    public function __invoke(
        Request $request,
        string $provider,
        SocialStrategy $strategy,
        RememberFlag $flag,
    ): RedirectResponse {
        // An OAuth round trip creates no challenge row, so the flag rides in the
        // same session that already carries the OAuth state.
        $request->session()->put('passwordless.remember', $flag->resolve($request));

        try {
            return $strategy->redirect($provider);
        } catch (SocialProviderNotEnabledException) {
            abort(404);
        }
    }
}
