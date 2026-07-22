{{--
    Passwordless — login page (INTEGRATED with the Livewire starter kit)
    --------------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-livewire-embed
    Target path:   resources/views/pages/auth/passwordless.blade.php

    Copies the Livewire starter kit's own auth conventions: wrapped in
    <x-layouts::auth>, reuses <x-auth-header> + <x-auth-session-status>, uses Flux
    form controls with automatic error binding by `name`, and submits with a
    classic server-side form POST -> redirect (handled by the published
    PasswordlessLoginController, which calls the package's public API). No fetch,
    no JS framework — exactly how the kit's password login works.

    Wire the routes from the published routes/passwordless-ui.php.
--}}
@php
    $email = session('passwordless.email');
    $step = $email ? 'code' : 'email';
    $codeEnabled = (bool) config('passwordless.strategies.login_code.enabled', true);
    $linkEnabled = (bool) config('passwordless.strategies.magic_link.enabled', true);
@endphp

<x-layouts::auth :title="__('Sign in')">
    <div class="flex flex-col gap-6">
        @if ($step === 'code' && $codeEnabled)
            <x-auth-header
                :title="__('Enter your code')"
                :description="__('We emailed a one-time code to :email', ['email' => $email])"
            />
        @else
            <x-auth-header
                :title="__('Sign in')"
                :description="__('Enter your email and we\'ll send you a one-time code')"
            />
        @endif

        <x-auth-session-status class="text-center" :status="session('status')" />

        @if (! $codeEnabled && ! $linkEnabled)
            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                {{ __('No passwordless strategies are enabled. Enable login_code or magic_link in config/passwordless.php.') }}
            </div>
        @elseif ($step === 'code' && $codeEnabled)
            {{-- Step 2 · verify code --}}
            <form method="POST" action="{{ route('passwordless.verify') }}" class="flex flex-col gap-6">
                @csrf
                <flux:input
                    name="code"
                    :label="__('Verification code')"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    placeholder="123456"
                />

                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('Verify & sign in') }}
                </flux:button>
            </form>

            <div class="flex items-center justify-between text-sm">
                <form method="POST" action="{{ route('passwordless.request') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <flux:link href="#" onclick="this.closest('form').submit(); return false;">{{ __('Resend code') }}</flux:link>
                </form>

                <flux:link :href="route('passwordless.start-over')" wire:navigate>{{ __('Use a different email') }}</flux:link>
            </div>
        @else
            {{-- Step 1 · email --}}
            <form method="POST" action="{{ route('passwordless.request') }}" class="flex flex-col gap-6">
                @csrf
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    type="email"
                    :value="old('email', $email)"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                @if ($codeEnabled)
                    <flux:button variant="primary" type="submit" class="w-full">
                        {{ __('Email me a code') }}
                    </flux:button>
                @endif

                @if ($linkEnabled)
                    <flux:button
                        type="submit"
                        variant="{{ $codeEnabled ? 'filled' : 'primary' }}"
                        class="w-full"
                        formaction="{{ route('passwordless.link') }}"
                    >
                        {{ __('Email me a magic link') }}
                    </flux:button>
                @endif
            </form>
        @endif

        @if (Route::has('login'))
            <div class="space-x-1 text-sm text-center text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Prefer a password?') }}</span>
                <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
