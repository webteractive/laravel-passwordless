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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeInvalidException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeLockedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeResendCooldownException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkResendCooldownException;
use Webteractive\Passwordless\Support\ConfirmationLockedException;
use Webteractive\Passwordless\Support\ConfirmationResendCooldownException;
use Webteractive\Passwordless\Support\RememberFlag;

class PasswordlessLoginController extends Controller
{
    public function create(): View
    {
        return view('pages::auth.passwordless');
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

        // Persist the email across the code-entry step (NOT flash — the code page
        // renders before the user submits, which would consume flashed data).
        // Enumeration-safe: we always advance to the code step, known email or not.
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

    public function startOver(Request $request): RedirectResponse
    {
        $request->session()->forget('passwordless.email');

        return redirect()->route('passwordless.login');
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

    /**
     * Email an identity-confirmation code to the signed-in user.
     *
     * This posts here rather than straight to the package's
     * `passwordless.confirm.send` route because that route is part of the
     * headless core and answers with JSON — a classic form post would land the
     * browser on a raw `{"status":"sent"}` page. Same validate/act/redirect-back
     * shape as the rest of this controller.
     */
    public function sendConfirmation(Request $request): RedirectResponse
    {
        try {
            Passwordless::confirmation()->send($request->user(), [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (ConfirmationResendCooldownException|ConfirmationLockedException $e) {
            return back()->withErrors(['password' => __('Please wait :s seconds and try again.', ['s' => $e->retryAfter])]);
        }

        return back()->with('status', __('We emailed you a confirmation code.'));
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
