<?php

/*
 * Passwordless — login controller (INTEGRATED with the Vue starter kit)
 * -----------------------------------------------------------------------
 * Published by:  php artisan vendor:publish --tag=passwordless-ui-vue-embed
 * Target path:   app/Http/Controllers/Auth/PasswordlessLoginController.php
 *
 * Mirrors the kit's Fortify-style server-side pattern, but renders an Inertia
 * page and returns redirects (which Inertia follows). Drives the flow through
 * the package's PUBLIC API — the headless core is untouched. Adjust the
 * namespace if your app doesn't use "App\".
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeInvalidException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeLockedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeResendCooldownException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkResendCooldownException;
use Webteractive\Passwordless\Support\RememberFlag;

class PasswordlessLoginController extends Controller
{
    public function create(Request $request): Response
    {
        $email = $request->session()->get('passwordless.email');

        return Inertia::render('auth/Passwordless', [
            'step' => $email ? 'code' : 'email',
            'email' => $email,
            'status' => $request->session()->get('status'),
            'codeEnabled' => (bool) config('passwordless.strategies.login_code.enabled', true),
            'linkEnabled' => (bool) config('passwordless.strategies.magic_link.enabled', true),
            // Dev-only user picker. The route exists only when the package's
            // dev_login guard passes, so this is an empty list in production and
            // the page renders nothing.
            'devUsers' => Route::has('passwordless.dev-login.index')
                ? config('passwordless.user_model')::query()
                    ->limit(config('passwordless.dev_login.limit', 50))
                    ->get(['id', config('passwordless.user_email_column', 'email')])
                : [],
            'devLoginRoute' => Route::has('passwordless.dev-login.index')
                ? route('passwordless.dev-login.store')
                : null,
            'routes' => [
                'request' => route('passwordless.request'),
                'verify' => route('passwordless.verify'),
                'link' => route('passwordless.link'),
                'startOver' => route('passwordless.start-over'),
            ],
        ]);
    }

    public function requestCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'remember' => ['nullable', 'boolean'],
        ]);

        try {
            Passwordless::loginCode()->send($data['email'], $this->context($request));
        } catch (LoginCodeResendCooldownException $e) {
            return back()->withErrors(['email' => __('Please wait :s seconds and try again.', ['s' => $e->retryAfter])]);
        }

        $request->session()->put('passwordless.email', $data['email']);

        return back()->with('status', __('If that email exists, a code is on its way.'));
    }

    public function verify(Request $request): RedirectResponse|SymfonyResponse
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
        // The checkbox was ticked on the email step, one request earlier — so read
        // the flag the package stashed from the challenge, not this request.
        $remember = app(RememberFlag::class)->resolve($request);

        // Honour Fortify 2FA when the user has it enabled: hand off to the
        // challenge instead of completing the login here. No-op without Fortify.
        if (Passwordless::twoFactor()->required($user)) {
            return Passwordless::twoFactor()->challenge($user, $request, $remember);
        }

        Auth::guard(config('passwordless.guard'))->login($user, $remember);
        $request->session()->regenerate();

        // Honors a middleware-set intended URL first, then the package's
        // Passwordless::redirectUsing() closure, then config('passwordless.redirect').
        return redirect()->intended(Passwordless::resolveRedirect($user, $request));
    }

    public function requestLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'remember' => ['nullable', 'boolean'],
        ]);

        try {
            Passwordless::magicLink()->send($data['email'], $this->context($request));
        } catch (MagicLinkResendCooldownException $e) {
            return back()->withErrors(['email' => __('Please wait :s seconds and try again.', ['s' => $e->retryAfter])]);
        }

        return back()->with('status', __('If that email exists, a sign-in link is on its way.'));
    }

    public function startOver(Request $request): RedirectResponse
    {
        $request->session()->forget('passwordless.email');

        return redirect()->route('passwordless.login');
    }

    protected function context(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'remember' => $request->boolean('remember'),
        ];
    }
}
