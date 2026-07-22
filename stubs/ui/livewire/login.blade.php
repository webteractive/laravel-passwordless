{{--
    Passwordless — login page stub (Blade + Alpine, Livewire starter-kit stack)
    ---------------------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-livewire
    Target path:   resources/views/passwordless/login.blade.php

    This is YOUR file now — edit freely. It talks to the package's JSON endpoints
    (routes are registered by the package), so the headless core is untouched.

    Requirements (all present in a Laravel Livewire starter-kit app):
      - Tailwind CSS v4 compiled via Vite (this file's classes are picked up by
        the default `resources/**/*.blade.php` content scan).
      - Alpine.js (bundled with the Livewire starter kit's resources/js/app.js).
      - Vite entry points resources/css/app.css and resources/js/app.js.

    Wire a GET route to render it — see routes/passwordless-ui.php (also published).
--}}
@php
    $appName = config('passwordless.branding.app_name') ?? config('app.name', 'Laravel');
    $codeEnabled = (bool) config('passwordless.strategies.login_code.enabled', true);
    $linkEnabled = (bool) config('passwordless.strategies.magic_link.enabled', true);
    $codeLength = (int) config('passwordless.strategies.login_code.length', 6);
    $redirect = config('passwordless.redirect', '/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — {{ $appName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes pwl-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .pwl-rise { animation: none !important; } }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <div
            class="pwl-rise w-full max-w-sm"
            style="animation: pwl-rise 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;"
            x-data="passwordlessLogin({
                codeEnabled: {{ $codeEnabled ? 'true' : 'false' }},
                linkEnabled: {{ $linkEnabled ? 'true' : 'false' }},
                codeLength: {{ $codeLength }},
                redirect: @js($redirect),
                endpoints: {
                    sendCode: @js($codeEnabled ? route('passwordless.login-code.send') : null),
                    verifyCode: @js($codeEnabled ? route('passwordless.login-code.verify') : null),
                    sendLink: @js($linkEnabled ? route('passwordless.magic-link.send') : null),
                },
            })"
        >
            {{-- Logo / brand slot --}}
            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-900 text-lg font-semibold text-white dark:bg-white dark:text-neutral-900">
                    {{ strtoupper(substr($appName, 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Sign in to {{ $appName }}</h1>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400" x-text="heading()"></p>
                </div>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">

                {{-- Error banner --}}
                <div x-cloak x-show="error"
                     class="mb-4 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300"
                     x-text="error" role="alert"></div>

                @if (! $codeEnabled && ! $linkEnabled)
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        No passwordless strategies are enabled. Enable <code>login_code</code> or
                        <code>magic_link</code> in <code>config/passwordless.php</code>.
                    </p>
                @endif

                {{-- Step: email --}}
                <form x-show="step === 'email'" x-on:submit.prevent="requestCode()" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="pwl-email" class="text-sm font-medium">Email address</label>
                        <input id="pwl-email" type="email" name="email" required autocomplete="email"
                               x-model="email" x-ref="email" :disabled="loading"
                               placeholder="you@example.com"
                               class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100 dark:focus:ring-neutral-100/10">
                    </div>

                    @if ($codeEnabled)
                        <button type="submit" :disabled="loading"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900/20 active:translate-y-px disabled:opacity-60 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                            <span x-show="!loading">Send me a code</span>
                            <span x-show="loading" x-cloak>Sending…</span>
                        </button>
                    @endif

                    @if ($linkEnabled)
                        <button type="button" x-on:click="requestLink()" :disabled="loading"
                                @class([
                                    'inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition focus:outline-none disabled:opacity-60',
                                    'border border-neutral-300 text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800' => $codeEnabled,
                                    'bg-neutral-900 text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200' => ! $codeEnabled,
                                ])>
                            Email me a magic link
                        </button>
                    @endif
                </form>

                {{-- Step: code --}}
                @if ($codeEnabled)
                    <form x-cloak x-show="step === 'code'" x-on:submit.prevent="verifyCode()" class="flex flex-col gap-4">
                        <div class="flex justify-between gap-2" x-ref="otp">
                            <template x-for="(d, i) in digits" :key="i">
                                <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code"
                                       aria-label="Verification digit"
                                       x-on:input="onInput($event, i)"
                                       x-on:keydown.backspace="onBackspace($event, i)"
                                       x-on:paste.prevent="onPaste($event)"
                                       class="aspect-[3/4] w-full min-w-0 rounded-lg border border-neutral-300 bg-white text-center text-lg font-semibold shadow-sm outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100">
                            </template>
                        </div>

                        <button type="submit" :disabled="loading"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900/20 active:translate-y-px disabled:opacity-60 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                            <span x-show="!loading">Verify &amp; sign in</span>
                            <span x-show="loading" x-cloak>Verifying…</span>
                        </button>

                        <button type="button" x-on:click="toEmail()" class="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100">
                            &larr; Use a different email
                        </button>
                    </form>
                @endif

                {{-- Step: link sent --}}
                <div x-cloak x-show="step === 'sent'" class="flex flex-col gap-4 text-center">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 0 1 1.4-1.4l3.3 3.29 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    </div>
                    <p class="text-sm text-neutral-600 dark:text-neutral-300">
                        Check your inbox — we sent a sign-in link to
                        <span class="font-medium text-neutral-900 dark:text-neutral-100" x-text="email"></span>.
                    </p>
                    <button type="button" x-on:click="toEmail()" class="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100">
                        &larr; Back
                    </button>
                </div>
            </div>

            @if ($codeEnabled && $linkEnabled)
                <p x-cloak x-show="step === 'code'" class="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                    Didn't get it?
                    <button type="button" x-on:click="requestLink()" class="font-medium text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100">Email a magic link instead</button>
                </p>
            @endif
        </div>
    </main>

    <script>
        function passwordlessLogin(config) {
            return {
                step: 'email',
                email: '',
                digits: Array(config.codeLength).fill(''),
                error: '',
                loading: false,

                init() {
                    this.$nextTick(() => this.$refs.email?.focus());
                },

                heading() {
                    if (this.step === 'code') return `Enter the ${config.codeLength}-digit code we emailed you.`;
                    if (this.step === 'sent') return 'A sign-in link is on its way.';
                    return 'Enter your email to receive a one-time code.';
                },

                async post(url, body) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body),
                    });
                    return res;
                },

                messageFor(res, data, fallback) {
                    if (res.status === 422) return Object.values(data.errors ?? {})[0]?.[0] ?? fallback;
                    if (res.status === 429 || res.status === 423) {
                        const secs = data.retry_after ?? res.headers.get('Retry-After');
                        return secs ? `Please wait ${secs}s and try again.` : (data.message ?? fallback);
                    }
                    return data.message ?? fallback;
                },

                async requestCode() {
                    if (!config.endpoints.sendCode) return;
                    this.error = ''; this.loading = true;
                    try {
                        const res = await this.post(config.endpoints.sendCode, { email: this.email });
                        const data = await res.json().catch(() => ({}));
                        if (res.status === 202) { this.toCode(); return; }
                        this.error = this.messageFor(res, data, 'Something went wrong. Try again.');
                    } catch { this.error = 'Network error. Try again.'; }
                    finally { this.loading = false; }
                },

                async requestLink() {
                    if (!config.endpoints.sendLink) return;
                    this.error = ''; this.loading = true;
                    try {
                        const res = await this.post(config.endpoints.sendLink, { email: this.email });
                        const data = await res.json().catch(() => ({}));
                        if (res.status === 202) { this.step = 'sent'; return; }
                        this.error = this.messageFor(res, data, 'Something went wrong. Try again.');
                    } catch { this.error = 'Network error. Try again.'; }
                    finally { this.loading = false; }
                },

                async verifyCode() {
                    if (!config.endpoints.verifyCode) return;
                    this.error = ''; this.loading = true;
                    try {
                        const res = await this.post(config.endpoints.verifyCode, {
                            email: this.email,
                            code: this.digits.join(''),
                        });
                        if (res.status === 204 || res.status === 200) { window.location.assign(config.redirect); return; }
                        const data = await res.json().catch(() => ({}));
                        this.error = this.messageFor(res, data, 'Invalid or expired code.');
                        this.resetDigits();
                    } catch { this.error = 'Network error. Try again.'; }
                    finally { this.loading = false; }
                },

                // --- step transitions ---
                toCode() { this.step = 'code'; this.resetDigits(); this.$nextTick(() => this.otpBoxes()[0]?.focus()); },
                toEmail() { this.step = 'email'; this.error = ''; this.$nextTick(() => this.$refs.email?.focus()); },

                // --- OTP box behavior ---
                otpBoxes() { return this.$refs.otp ? [...this.$refs.otp.querySelectorAll('input')] : []; },
                resetDigits() { this.digits = Array(config.codeLength).fill(''); this.otpBoxes().forEach(b => b.value = ''); },
                onInput(e, i) {
                    e.target.value = e.target.value.replace(/\D/g, '').slice(-1);
                    this.digits[i] = e.target.value;
                    const boxes = this.otpBoxes();
                    if (e.target.value && i < config.codeLength - 1) boxes[i + 1].focus();
                    if (this.digits.every(d => d !== '')) this.verifyCode();
                },
                onBackspace(e, i) {
                    if (!e.target.value && i > 0) this.otpBoxes()[i - 1].focus();
                },
                onPaste(e) {
                    const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, config.codeLength);
                    const boxes = this.otpBoxes();
                    text.split('').forEach((ch, idx) => { this.digits[idx] = ch; if (boxes[idx]) boxes[idx].value = ch; });
                    if (text.length) boxes[Math.min(text.length, config.codeLength - 1)].focus();
                    if (text.length === config.codeLength) this.verifyCode();
                },
            };
        }
    </script>
</body>
</html>
