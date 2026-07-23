{{--
    Passwordless — standalone login page (Blade, zero JS-framework dependency)
    -------------------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-livewire
    Target path:   resources/views/passwordless/login.blade.php

    This is YOUR file now — edit freely. It is fully self-contained: its own
    document + layout, plain vanilla JS (no Alpine/Livewire/Vue required), and it
    talks to the package's JSON endpoints via `fetch`, so the headless core is
    untouched. Drop it into any app.

    Only requirement: Tailwind CSS compiled through Vite (the default
    `resources/css/app.css` entry). The interaction JS is inline below.

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
    @vite(['resources/css/app.css'])
    <style>
        @keyframes pwl-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .pwl-rise { animation: none !important; } }
        [hidden] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <div id="pwl-root" class="pwl-rise w-full max-w-sm" style="animation: pwl-rise 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;">

            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-900 text-lg font-semibold text-white dark:bg-white dark:text-neutral-900">
                    {{ strtoupper(substr($appName, 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Sign in to {{ $appName }}</h1>
                    <p id="pwl-heading" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Enter your email to receive a one-time code.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div id="pwl-error" hidden class="mb-4 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300" role="alert"></div>

                @if (! $codeEnabled && ! $linkEnabled)
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        No passwordless strategies are enabled. Enable <code>login_code</code> or
                        <code>magic_link</code> in <code>config/passwordless.php</code>.
                    </p>
                @endif

                {{-- Step: email --}}
                <form id="pwl-step-email" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="pwl-email" class="text-sm font-medium">Email address</label>
                        <input id="pwl-email" type="email" name="email" required autocomplete="email"
                               placeholder="you@example.com"
                               class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-950">
                    </div>

                    @if ($codeEnabled)
                        <button type="submit" id="pwl-send-code"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900/20 active:translate-y-px disabled:opacity-60 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                            Send me a code
                        </button>
                    @endif

                    @if ($linkEnabled)
                        <button type="button" id="pwl-send-link"
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
                    <form id="pwl-step-code" hidden class="flex flex-col gap-4">
                        <div class="flex justify-between gap-2" id="pwl-otp">
                            @for ($i = 0; $i < $codeLength; $i++)
                                <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code"
                                       aria-label="Verification digit" data-pwl-otp
                                       class="aspect-[3/4] w-full min-w-0 rounded-lg border border-neutral-300 bg-white text-center text-lg font-semibold shadow-sm outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-950">
                            @endfor
                        </div>

                        <button type="submit" id="pwl-verify"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900/20 active:translate-y-px disabled:opacity-60 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200">
                            Verify &amp; sign in
                        </button>

                        <button type="button" id="pwl-to-email" class="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100">
                            &larr; Use a different email
                        </button>
                    </form>
                @endif

                {{-- Step: link sent --}}
                <div id="pwl-step-sent" hidden class="flex flex-col gap-4 text-center">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 0 1 1.4-1.4l3.3 3.29 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    </div>
                    <p class="text-sm text-neutral-600 dark:text-neutral-300">
                        Check your inbox — we sent a sign-in link to <span id="pwl-sent-email" class="font-medium text-neutral-900 dark:text-neutral-100"></span>.
                    </p>
                    <button type="button" id="pwl-sent-back" class="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100">&larr; Back</button>
                </div>
            </div>
        </div>
    </main>

    <script type="application/json" id="pwl-config">
        {!! json_encode([
            'codeLength' => $codeLength,
            'redirect' => $redirect,
            'endpoints' => [
                'sendCode' => $codeEnabled ? route('passwordless.login-code.send') : null,
                'verifyCode' => $codeEnabled ? route('passwordless.login-code.verify') : null,
                'sendLink' => $linkEnabled ? route('passwordless.magic-link.send') : null,
            ],
        ], JSON_UNESCAPED_SLASHES) !!}
    </script>

    <script>
        (function () {
            var cfg = JSON.parse(document.getElementById('pwl-config').textContent);
            var csrf = document.querySelector('meta[name="csrf-token"]').content;

            var els = {
                heading: document.getElementById('pwl-heading'),
                error: document.getElementById('pwl-error'),
                stepEmail: document.getElementById('pwl-step-email'),
                stepCode: document.getElementById('pwl-step-code'),
                stepSent: document.getElementById('pwl-step-sent'),
                email: document.getElementById('pwl-email'),
                sendLink: document.getElementById('pwl-send-link'),
                verify: document.getElementById('pwl-verify'),
                toEmail: document.getElementById('pwl-to-email'),
                sentEmail: document.getElementById('pwl-sent-email'),
                sentBack: document.getElementById('pwl-sent-back'),
                otp: document.getElementById('pwl-otp'),
            };
            var boxes = els.otp ? Array.prototype.slice.call(els.otp.querySelectorAll('[data-pwl-otp]')) : [];

            function showError(msg) { els.error.textContent = msg; els.error.hidden = !msg; }
            function setStep(step) {
                if (els.stepEmail) els.stepEmail.hidden = step !== 'email';
                if (els.stepCode) els.stepCode.hidden = step !== 'code';
                if (els.stepSent) els.stepSent.hidden = step !== 'sent';
                els.heading.textContent = step === 'code'
                    ? 'Enter the ' + cfg.codeLength + '-digit code we emailed you.'
                    : step === 'sent' ? 'A sign-in link is on its way.'
                    : 'Enter your email to receive a one-time code.';
            }

            function post(url, body) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
            }

            function messageFor(res, data, fallback) {
                if (res.status === 422) {
                    var errs = data.errors || {};
                    var first = Object.keys(errs)[0];
                    return first ? errs[first][0] : fallback;
                }
                if (res.status === 429 || res.status === 423) {
                    var secs = data.retry_after || res.headers.get('Retry-After');
                    return secs ? 'Please wait ' + secs + 's and try again.' : (data.message || fallback);
                }
                return data.message || fallback;
            }

            function resetDigits() { boxes.forEach(function (b) { b.value = ''; }); }
            function code() { return boxes.map(function (b) { return b.value; }).join(''); }

            function requestCode(e) {
                if (e) e.preventDefault();
                if (!cfg.endpoints.sendCode) return;
                showError('');
                post(cfg.endpoints.sendCode, { email: els.email.value }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        if (res.status === 202) { setStep('code'); resetDigits(); if (boxes[0]) boxes[0].focus(); return; }
                        showError(messageFor(res, data, 'Something went wrong. Try again.'));
                    });
                }).catch(function () { showError('Network error. Try again.'); });
            }

            function requestLink() {
                if (!cfg.endpoints.sendLink) return;
                showError('');
                post(cfg.endpoints.sendLink, { email: els.email.value }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        if (res.status === 202) { els.sentEmail.textContent = els.email.value; setStep('sent'); return; }
                        showError(messageFor(res, data, 'Something went wrong. Try again.'));
                    });
                }).catch(function () { showError('Network error. Try again.'); });
            }

            function verifyCode(e) {
                if (e) e.preventDefault();
                if (!cfg.endpoints.verifyCode) return;
                showError('');
                post(cfg.endpoints.verifyCode, { email: els.email.value, code: code() }).then(function (res) {
                    if (res.status === 204 || res.status === 200) { window.location.assign(cfg.redirect); return; }
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        showError(messageFor(res, data, 'Invalid or expired code.'));
                        resetDigits();
                        if (boxes[0]) boxes[0].focus();
                    });
                }).catch(function () { showError('Network error. Try again.'); });
            }

            if (els.stepEmail) els.stepEmail.addEventListener('submit', requestCode);
            if (els.sendLink) els.sendLink.addEventListener('click', requestLink);
            if (els.stepCode) els.stepCode.addEventListener('submit', verifyCode);
            if (els.toEmail) els.toEmail.addEventListener('click', function () { setStep('email'); showError(''); els.email.focus(); });
            if (els.sentBack) els.sentBack.addEventListener('click', function () { setStep('email'); showError(''); els.email.focus(); });

            boxes.forEach(function (box, i) {
                box.addEventListener('input', function () {
                    box.value = box.value.replace(/\D/g, '').slice(-1);
                    if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
                    if (boxes.every(function (b) { return b.value !== ''; })) verifyCode();
                });
                box.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
                });
                box.addEventListener('paste', function (ev) {
                    ev.preventDefault();
                    var text = (ev.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, boxes.length);
                    text.split('').forEach(function (ch, idx) { if (boxes[idx]) boxes[idx].value = ch; });
                    if (text.length) boxes[Math.min(text.length, boxes.length - 1)].focus();
                    if (text.length === boxes.length) verifyCode();
                });
            });

            if (els.email) els.email.focus();
        })();
    </script>
</body>
</html>
