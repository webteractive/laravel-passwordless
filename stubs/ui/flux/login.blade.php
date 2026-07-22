{{--
    Passwordless — login page stub (Livewire Volt + Flux UI, faithful Livewire starter-kit flavor)
    ----------------------------------------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-flux
    Target path:   resources/views/livewire/passwordless/login.blade.php

    This is YOUR file now — edit freely. Unlike the fetch-based stubs, this Volt
    component drives the flow SERVER-SIDE through the package's public API
    (Passwordless::loginCode()/magicLink()), mirroring the package controllers.
    The headless core is still untouched — this only consumes its public surface.

    Requirements (present in a Laravel Livewire starter-kit app):
      - livewire/livewire, livewire/volt, livewire/flux
      - Tailwind v4 (Flux ships its own styles).

    Wire it with a Volt route — see routes/passwordless-ui.php (also published):
      Volt::route('login', 'passwordless.login')->name('login');
--}}
<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeGateDeniedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeInvalidException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeLockedException;
use Webteractive\Passwordless\Strategies\LoginCode\LoginCodeResendCooldownException;
use Webteractive\Passwordless\Strategies\MagicLink\MagicLinkResendCooldownException;

new class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $code = '';

    /** email | code | sent */
    public string $step = 'email';

    public function context(): array
    {
        return ['ip' => request()->ip(), 'user_agent' => request()->userAgent()];
    }

    public function sendCode(): void
    {
        $this->validateOnly('email');

        try {
            Passwordless::loginCode()->send($this->email, $this->context());
        } catch (LoginCodeResendCooldownException $e) {
            $this->addError('email', __('Please wait :s seconds and try again.', ['s' => $e->retryAfter]));

            return;
        }

        // Enumeration-safe: we always advance, whether or not the email exists.
        $this->reset('code');
        $this->step = 'code';
    }

    public function sendLink(): void
    {
        $this->validateOnly('email');

        try {
            Passwordless::magicLink()->send($this->email, $this->context());
        } catch (MagicLinkResendCooldownException $e) {
            $this->addError('email', __('Please wait :s seconds and try again.', ['s' => $e->retryAfter]));

            return;
        }

        $this->step = 'sent';
    }

    public function verify()
    {
        $this->validate();

        try {
            $user = Passwordless::loginCode()->verify($this->email, $this->code, request());
        } catch (LoginCodeLockedException $e) {
            $this->addError('code', __('Too many attempts. Try again in :s seconds.', ['s' => $e->retryAfter]));

            return;
        } catch (LoginCodeInvalidException) {
            $this->reset('code');
            $this->addError('code', __('That code is invalid or expired.'));

            return;
        } catch (LoginCodeGateDeniedException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        if (config('passwordless.api_mode')) {
            // API mode returns a token via the JSON endpoints; a server-rendered
            // page logs into the session guard instead.
        }

        Auth::guard(config('passwordless.guard'))->login($user);
        session()->regenerate();

        return $this->redirectIntended(config('passwordless.redirect', '/'), navigate: true);
    }

    public function toEmail(): void
    {
        $this->reset('code');
        $this->resetErrorBag();
        $this->step = 'email';
    }

    public function with(): array
    {
        return [
            'appName' => config('passwordless.branding.app_name') ?? config('app.name', 'Laravel'),
            'codeEnabled' => (bool) config('passwordless.strategies.login_code.enabled', true),
            'linkEnabled' => (bool) config('passwordless.strategies.magic_link.enabled', true),
        ];
    }
}; ?>

<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-3 text-center">
            <flux:avatar :name="$appName" size="lg" />
            <div>
                <flux:heading size="xl">Sign in to {{ $appName }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($step === 'code')
                        Enter the code we emailed you.
                    @elseif ($step === 'sent')
                        A sign-in link is on its way.
                    @else
                        Enter your email to receive a one-time code.
                    @endif
                </flux:text>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @if (! $codeEnabled && ! $linkEnabled)
                <flux:text>
                    No passwordless strategies are enabled. Enable <code>login_code</code> or
                    <code>magic_link</code> in <code>config/passwordless.php</code>.
                </flux:text>
            @endif

            {{-- Step: email --}}
            @if ($step === 'email')
                <form wire:submit="sendCode" class="flex flex-col gap-4">
                    <flux:input
                        wire:model="email"
                        type="email"
                        label="Email address"
                        placeholder="you@example.com"
                        autocomplete="email"
                        autofocus
                    />

                    @if ($codeEnabled)
                        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                            Send me a code
                        </flux:button>
                    @endif

                    @if ($linkEnabled)
                        <flux:button
                            type="button"
                            wire:click="sendLink"
                            :variant="$codeEnabled ? 'subtle' : 'primary'"
                            class="w-full"
                            wire:loading.attr="disabled"
                        >
                            Email me a magic link
                        </flux:button>
                    @endif
                </form>
            @endif

            {{-- Step: code --}}
            @if ($step === 'code' && $codeEnabled)
                <form wire:submit="verify" class="flex flex-col gap-4">
                    <flux:input
                        wire:model="code"
                        label="Verification code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        autofocus
                        class="text-center tracking-[0.5em]"
                    />

                    <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                        Verify &amp; sign in
                    </flux:button>

                    <flux:button type="button" variant="ghost" wire:click="toEmail" class="w-full">
                        &larr; Use a different email
                    </flux:button>
                </form>
            @endif

            {{-- Step: link sent --}}
            @if ($step === 'sent')
                <div class="flex flex-col items-center gap-4 text-center">
                    <flux:icon.check-circle variant="solid" class="size-10 text-green-500" />
                    <flux:text>
                        Check your inbox — we sent a sign-in link to
                        <span class="font-medium">{{ $email }}</span>.
                    </flux:text>
                    <flux:button type="button" variant="ghost" wire:click="toEmail">&larr; Back</flux:button>
                </div>
            @endif
        </div>

        @if ($codeEnabled && $linkEnabled && $step === 'code')
            <flux:text class="mt-6 text-center">
                Didn't get it?
                <flux:link wire:click="sendLink" class="cursor-pointer">Email a magic link instead</flux:link>
            </flux:text>
        @endif
    </div>
</div>
