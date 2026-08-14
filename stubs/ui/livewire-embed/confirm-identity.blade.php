{{--
    Passwordless — confirm identity (INTEGRATED with the Livewire starter kit)
    -------------------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-livewire-embed
    Target path:   resources/views/pages/auth/confirm-identity.blade.php

    Rendered in place of Fortify's confirm-password page, via the
    ConfirmPasswordViewResponse binding in PasswordlessFortifyServiceProvider.

    Two forms, two destinations:
      1. passwordless.confirm.request — YOUR route below (sendConfirmation), which
         calls the package and redirects back. Do NOT post a browser form straight
         to the package's `passwordless.confirm.send` route: that is the headless
         JSON endpoint and would dump the user on a raw {"status":"sent"} page.
      2. password.confirm.store     — FORTIFY's own route; it runs the
         confirmPasswordsUsing callback and stamps auth.password_confirmed_at

    The code field must be named `password` — that is the input Fortify reads.
--}}
<x-layouts::auth :title="__('Confirm your identity')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Confirm it\'s you')"
            :description="__('For your security, confirm your identity before changing security settings.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('passwordless.confirm.request') }}">
            @csrf
            <flux:button type="submit" variant="filled" class="w-full">
                {{ __('Email me a code') }}
            </flux:button>
        </form>

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input
                name="password"
                :label="__('Confirmation code')"
                autocomplete="one-time-code"
                inputmode="numeric"
                placeholder="123456"
                required
                autofocus
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Confirm') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
