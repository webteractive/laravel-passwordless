<?php

/*
 * Passwordless — login controller (INTEGRATED with the Livewire starter kit)
 * --------------------------------------------------------------------------
 * Published by:  php artisan vendor:publish --tag=passwordless-ui-livewire-embed
 * Target path:   app/Http/Controllers/Auth/PasswordlessLoginController.php
 *
 * This is YOUR file now. It mirrors the Fortify-style server-side pattern the
 * starter kit uses for password login: validate, act, then redirect (success ->
 * intended/home; failure -> back with errors that Flux surfaces by field name).
 * It drives the flow through the package's PUBLIC API, so the headless core is
 * untouched. Adjust the namespace if your app doesn't use "App\".
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeInvalidException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeLockedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeResendCooldownException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkResendCooldownException;

class PasswordlessLoginController extends Controller
{
    public function create(): View
    {
        return view('pages::auth.passwordless');
    }

    public function requestCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            Passwordless::loginCode()->send($data['email'], $this->context($request));
        } catch (LoginCodeResendCooldownException $e) {
            return back()->withErrors(['email' => __('Please wait :s seconds and try again.', ['s' => $e->retryAfter])]);
        }

        // Persist the email across the code-entry step (NOT flash — the code page
        // renders before the user submits, which would consume flashed data).
        // Enumeration-safe: we always advance to the code step, known email or not.
        $request->session()->put('passwordless.email', $data['email']);

        return back()->with('status', __('If that email exists, a code is on its way.'));
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = $request->session()->get('passwordless.email');
        $data = $request->validate(['code' => ['required', 'string']]);

        if (! $email) {
            return redirect()->route('passwordless.login');
        }

        try {
            $user = Passwordless::loginCode()->verify($email, $data['code'], $request);
        } catch (LoginCodeLockedException $e) {
            return back()->withErrors(['code' => __('Too many attempts. Try again in :s seconds.', ['s' => $e->retryAfter])]);
        } catch (LoginCodeInvalidException) {
            return back()->withErrors(['code' => __('That code is invalid or expired.')]);
        } catch (LoginCodeGateDeniedException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget('passwordless.email');
        Auth::guard(config('passwordless.guard'))->login($user);
        $request->session()->regenerate();

        // Honors a middleware-set intended URL first, then the package's
        // Passwordless::redirectUsing() closure, then config('passwordless.redirect').
        return redirect()->intended(Passwordless::resolveRedirect($user, $request));
    }

    public function startOver(Request $request): RedirectResponse
    {
        $request->session()->forget('passwordless.email');

        return redirect()->route('passwordless.login');
    }

    public function requestLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        try {
            Passwordless::magicLink()->send($data['email'], $this->context($request));
        } catch (MagicLinkResendCooldownException $e) {
            return back()->withErrors(['email' => __('Please wait :s seconds and try again.', ['s' => $e->retryAfter])]);
        }

        return back()->with('status', __('If that email exists, a sign-in link is on its way.'));
    }

    protected function context(Request $request): array
    {
        return ['ip' => $request->ip(), 'user_agent' => $request->userAgent()];
    }
}
