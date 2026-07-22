# laravel-passwordless

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webteractive/laravel-passwordless.svg?style=flat-square)](https://packagist.org/packages/webteractive/laravel-passwordless)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/webteractive/laravel-passwordless/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/webteractive/laravel-passwordless/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/webteractive/laravel-passwordless.svg?style=flat-square)](https://packagist.org/packages/webteractive/laravel-passwordless)

Drop-in passwordless authentication for Laravel 11/12/13 — magic links and email login codes. Backend only. Strictly headless.

## Installation

```bash
composer require webteractive/laravel-passwordless
```

Publish migrations and run them:

```bash
php artisan vendor:publish --tag="passwordless-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="passwordless-config"
```

Publish translations / mail views (optional):

```bash
php artisan vendor:publish --tag="passwordless-translations"
php artisan vendor:publish --tag="passwordless-views"
```

## Quickstart

### Magic link

Request a link:

```http
POST /auth/magic-link
{ "email": "user@example.com" }

→ 202 { "status": "sent" }
```

Consume the link (the user clicks it from their email):

```http
GET /auth/magic-link/{token}?expires=...&signature=...
→ 204 No Content   (session mode)
→ 200 { "token": "...", "user": {...} }   (api_mode = true)
```

### Login code (email OTP)

```http
POST /auth/login-code            { "email": "user@example.com" }   → 202
POST /auth/login-code/verify     { "email": "...", "code": "123456" } → 204 / 200
```

Default code length is 6 (configurable 6–10). Codes are numeric strings — leading zeros preserved.

> Looking for passkeys / WebAuthn? Use Laravel Fortify's first-party passkey support — this package intentionally stays focused on magic links and login codes.

## Optional UI kit (opt-in, publish-only)

The core is strictly headless — no routes or views render by default. If you want a ready-made
login page, publish a stub matched to your Laravel starter kit. The stub becomes **your** file;
it talks to the JSON endpoints above via `fetch`, so the headless core stays untouched.

```bash
php artisan vendor:publish --tag=passwordless-ui-livewire   # Blade + Alpine (no extra deps)
php artisan vendor:publish --tag=passwordless-ui-flux       # Livewire Volt + Flux UI
php artisan vendor:publish --tag=passwordless-ui-react      # Inertia + React + TypeScript
php artisan vendor:publish --tag=passwordless-ui-vue        # Inertia + Vue + TypeScript
```

Each publishes a login page plus a commented `routes/passwordless-ui.php` example route (the
package registers no page route itself — you wire it). The page is a two-step **email → 6-digit
code** flow with paste-to-fill and auto-submit, an optional "email me a magic link instead"
affordance, dark mode, and reduced-motion support. Affordances follow your enabled strategies in
`config/passwordless.php`. Styling is neutral Tailwind v4 — restyle by editing the published file.

The `livewire`, `react`, and `vue` stubs submit to the JSON endpoints with `fetch`. The `flux`
stub is different: it's a server-side **Volt** component (requires `livewire/volt` + `livewire/flux`)
that drives the flow through the package's public API (`Passwordless::loginCode()` / `magicLink()`),
so it needs no client-side calls — the closest match to the Livewire starter kit.

## Security defaults (always-on)

- Tokens hashed at rest (SHA-256), single-use, TTL-bound.
- **Email enumeration protection** — request endpoints return identical responses regardless of whether the email exists.
- **Same-browser enforcement** for magic links — the link only consumes from the browser that requested it. Toggle: `strategies.magic_link.same_browser`.
- **Resend cooldown** — default 30s between requests for the same email.
- **Per-strategy lockout** — N failed verifies → 423 + `Retry-After`. Default 5 attempts / 15 minutes.
- **Burst throttle** middleware — per-email and per-IP, separate limits for request vs. verify.

## Pre-auth gate

```php
use Webteractive\Passwordless\Facades\Passwordless;

Passwordless::gateUsing(fn ($user, $context) =>
    $user->is_active
        ? Passwordless::allow()
        : Passwordless::deny('account disabled')
);
```

Denials produce `403` and fire `AuthenticationDenied`.

## Audit funnel

```php
Passwordless::recordUsing(function (\Webteractive\Passwordless\Support\AuthEvent $event) {
    AuditLog::write($event);
});
```

## Custom login-code channels

```php
use Webteractive\Passwordless\Contracts\LoginCodeChannel;

class SmsChannel implements LoginCodeChannel
{
    public function send(mixed $user, string $email, string $code, array $context = []): void
    {
        // Twilio call, etc.
    }
}

// In a service provider:
$this->app->bind('passwordless.login_code_channels.sms', SmsChannel::class);
```

Then in `config/passwordless.php`:

```php
'strategies' => [
    'login_code' => ['channel' => 'sms'],
],
```

## Sanctum recipe

Set `api_mode => true` in config (or per request via your own controller wrapper). The package returns `{ token, user }` instead of using the session guard. Your `User` model must use `Laravel\Sanctum\HasApiTokens`.

## Testing

```php
use Webteractive\Passwordless\Facades\Passwordless;

it('sends a magic link', function () {
    $fake = Passwordless::fake();
    Passwordless::magicLink()->send('user@example.com');
    $fake->assertLinkSent('user@example.com');
});
```

`fake()` swaps the strategy bindings for assertion-only stubs — no real challenges, no notifications.

## Operational

```bash
php artisan passwordless:prune                            # delete expired/consumed challenges
```

Schedule the prune in `app/Console/Kernel.php`:

```php
$schedule->command('passwordless:prune')->hourly();
```

## Configuration

See `config/passwordless.php` after publishing — every option is documented inline.

## Compatibility

- PHP 8.3+
- Laravel 11.x, 12.x, 13.x
- MySQL, PostgreSQL, SQLite

## Testing the package

```bash
composer test
composer analyse
composer format
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Security

Report vulnerabilities privately via GitHub security advisories.

## License

MIT — see [LICENSE.md](LICENSE.md).
