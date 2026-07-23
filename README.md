# Laravel Passwordless

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webteractive/laravel-passwordless.svg?style=flat-square)](https://packagist.org/packages/webteractive/laravel-passwordless)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/webteractive/laravel-passwordless/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/webteractive/laravel-passwordless/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/webteractive/laravel-passwordless.svg?style=flat-square)](https://packagist.org/packages/webteractive/laravel-passwordless)

Drop-in **passwordless authentication** for Laravel 11, 12, and 13 — **magic links** and **email
login codes**. Headless by design: it ships secure JSON endpoints, events, and notifications, and
stays out of the way of your frontend. An **optional, opt-in UI kit** is available when you want a
login page without building one.

```http
POST /auth/login-code          { "email": "ada@example.com" }        → 202 sent
POST /auth/login-code/verify   { "email": "ada@example.com", "code": "123456" }  → 204 (logged in)
```

## Features

- ✉️ **Magic link** — signed, single-use, time-limited URL, with optional same-browser enforcement.
- 🔢 **Login code** — short numeric OTP over email (SMS/WhatsApp/etc. via a pluggable channel contract).
- 🌐 **Social login (OAuth)** — Google, GitHub, and any Socialite provider: verified-email account linking, auto-registration, and encrypted token storage. Install the driver + add keys → it works.
- 🚧 **Domain limiting** — restrict which email domains may log in and/or auto-register, per strategy type.
- 🛡️ **Secure by default** — hashing at rest, single-use, enumeration protection, lockout, resend cooldown, and burst throttling — all on out of the box.
- 🔌 **Headless** — JSON endpoints, lifecycle events, a pre-auth gate, and an audit funnel. Bring any frontend.
- 🎨 **Optional UI kit** — publish a ready-made login page for Blade, React, or Vue (standalone or matched to an official starter kit). Nothing is routed unless you opt in.
- 🧪 **Test-friendly** — `Passwordless::fake()` for assertion-only strategy stubs.
- 🔐 **Session or API mode** — Laravel's session guard by default; a Sanctum-style `{ token, user }` in `api_mode`.

> **Passkeys / WebAuthn?** Intentionally out of scope — Laravel Fortify ships first-class passkey
> support. This package stays focused on magic links and login codes.

## Requirements

- PHP 8.3+
- Laravel 11.x, 12.x, or 13.x
- MySQL, PostgreSQL, or SQLite

## Table of contents

- [Installation](#installation)
- [How it works](#how-it-works)
- [Quickstart](#quickstart)
- [Endpoints](#endpoints)
- [HTTP responses](#http-responses)
- [Social login](#social-login)
- [Domain limiting](#domain-limiting)
- [Optional UI kit](#optional-ui-kit)
- [Security defaults](#security-defaults)
- [Configuration](#configuration)
- [Events](#events)
- [Extending](#extending)
- [API mode (Sanctum)](#api-mode-sanctum)
- [Testing](#testing)
- [Operational](#operational)

## Installation

```bash
composer require webteractive/laravel-passwordless
```

Publish and run the migration (one table, `passwordless_challenges`):

```bash
php artisan vendor:publish --tag="passwordless-migrations"
php artisan migrate
```

Publish the config (optional — sensible defaults ship built-in):

```bash
php artisan vendor:publish --tag="passwordless-config"
```

Publish translations / mail views to customize them (optional):

```bash
php artisan vendor:publish --tag="passwordless-translations"
php artisan vendor:publish --tag="passwordless-views"
```

By default the user must already exist (looked up by the `email` column). Set
`auto_create_users => true` to create users on first successful sign-in.

## How it works

- **One table.** `passwordless_challenges` holds ephemeral magic-link tokens and login codes
  (hashed, single-use, TTL-bound). Prune it with `passwordless:prune`. No changes to your `users` table.
- **Routes.** Registered under a configurable prefix (`auth` by default) inside the `web`
  middleware group, so session login and cookies work out of the box.
- **Two modes.** Session mode (default) logs the user into Laravel's session guard; `api_mode`
  returns a Sanctum token instead. See [API mode](#api-mode-sanctum).
- **Enable or disable each strategy** independently in config; the UI kit hides affordances for strategies you've turned off.

## Quickstart

After [installing](#installation), the endpoints are live. Pick how you want to drive them:

**A. Headless** — call the endpoints from your own frontend (SPA, mobile, or your own Blade):

```js
await fetch('/auth/login-code', { method: 'POST', body: JSON.stringify({ email }) });        // → 202
await fetch('/auth/login-code/verify', { method: 'POST', body: JSON.stringify({ email, code }) }); // → 204, logged in
```

**B. With the UI kit** — publish a ready-made login page and wire one route, no frontend work:

```bash
php artisan vendor:publish --tag=passwordless-ui-livewire   # or -react / -vue, and -embed variants
```

Then add the published example route from `routes/passwordless-ui.php` and visit it. See
[Optional UI kit](#optional-ui-kit).

## Endpoints

Registered under the `route_prefix` (`auth` by default), inside the `web` middleware group:

| Method | URI | Purpose |
|---|---|---|
| `POST` | `/auth/login-code` | request a login code |
| `POST` | `/auth/login-code/verify` | verify a code and sign in |
| `POST` | `/auth/magic-link` | request a magic link |
| `GET`  | `/auth/magic-link/{token}` | consume a signed link and sign in |
| `GET`  | `/auth/social/{provider}/redirect` | start the OAuth flow |
| `GET`  | `/auth/social/{provider}/callback` | handle the OAuth callback and sign in |

Request endpoints always return `202` whether or not the email exists (enumeration protection).
Login codes are numeric **strings**, default length **6** (configurable 6–10) — leading zeros are
preserved. Full status codes below.

## HTTP responses

| Scenario | Status | Body / headers |
|---|---|---|
| Request link/code (known or unknown email) | `202` | `{ "status": "sent" }` |
| Verify success — session mode | `204` | session cookie set |
| Verify success — `api_mode` | `200` | `{ "token": "...", "user": {...} }` |
| Validation error | `422` | `{ "message", "errors": {…} }` |
| Invalid / expired token or code | `401` | `{ "message": "…" }` (deliberately vague) |
| Pre-auth gate denied | `403` | `{ "message": "<reason>" }` |
| Resend cooldown active | `429` | `Retry-After`, `{ "message", "retry_after" }` |
| Locked out (max attempts) | `423` | `Retry-After`, `{ "message", "retry_after" }` |

## Social login

OAuth sign-in via [Laravel Socialite](https://laravel.com/docs/socialite). The package handles
identity storage, verified-email account linking, auto-registration, and encrypted token storage —
you just enable a provider and supply keys.

1. Install the driver (Google/GitHub/etc. ship with Socialite; others via `socialiteproviders/*`).
2. Add credentials to `config/services.php` (Socialite's convention):
   ```php
   'google' => [
       'client_id'     => env('GOOGLE_CLIENT_ID'),
       'client_secret' => env('GOOGLE_CLIENT_SECRET'),
       'redirect'      => env('GOOGLE_REDIRECT_URI'),
   ],
   ```
3. Enable it in `config/passwordless.php` (this is a thin enable-list — no secrets here):
   ```php
   'social' => [
       'providers' => [
           'google',
           'github' => ['scopes' => ['read:user']],
       ],
       'auto_register' => true,
   ],
   ```
4. Link a button to the redirect route:
   ```blade
   <a href="{{ route('passwordless.social.redirect', 'google') }}">Continue with Google</a>
   ```

**How a user is resolved** on callback: a known `(provider, provider_id)` logs straight in; else,
for a **verified** email, it links to an existing user, or auto-registers a new one (when
`social.auto_register` is on). Only listed providers get routes — others return `404`. Access/refresh
tokens are stored **encrypted**. Fires `SocialAuthenticated` + `UserAuthenticated`.

**Email verification (account-takeover protection).** Linking/registering by email requires proof
the email is verified — the provider sends `email_verified: true`, or the provider is on the
`social.trusted_providers` allow-list (mainstream providers that only return verified emails). An
explicit `email_verified: false` always denies. Unverified → `403`. This prevents an attacker with
an unverified address at some provider from taking over an existing account. (Known-identity logins
skip this check — identity is already proven.) Override the whole resolution with
`resolveSocialUserUsing()` if you need custom verification.

**Custom resolution** — override how a Socialite user maps to an app user (stricter verification,
custom fields):

```php
use Webteractive\Passwordless\Facades\Passwordless;

Passwordless::resolveSocialUserUsing(function (string $provider, $oauth, $container) {
    // return an app user, or null to deny
    return User::firstOrCreate(['email' => $oauth->getEmail()], ['name' => $oauth->getName()]);
});
```

## Domain limiting

Restrict which email domains may authenticate. An empty `allowed` list disables all checks (the
default — no behavior change). When set, enforcement is independent **per type** (`passwordless` =
magic link + login code, `social`) and **per action** (`login` of existing users, `register` /
auto-create):

```php
'domains' => [
    'allowed' => ['acme.com'],
    'enforce' => [
        'passwordless' => ['login' => false, 'register' => true],
        'social'       => ['login' => true,  'register' => true],
    ],
],
```

Blocked auto-registration is enumeration-safe (behaves like an unknown email); a blocked login
returns `403`.

## Optional UI kit

The core is strictly headless — **no page routes or views render by default.** When you want a
ready-made login page, publish the stub that matches your app. The published files become **yours**
to edit; the headless core is never touched.

There are two flavors:

- **Standalone** — a self-contained page (its own layout + `@vite`) for apps with **no auth yet**.
  Submits to the JSON endpoints with `fetch`.
- **Integrated (`-embed`)** — copies an **official starter kit's** auth layout and components, and
  drives the flow server-side through a published Fortify-style controller. Best when you already
  run a starter kit.

| Tag | Mode | Stack | Submission | Extra deps |
|---|---|---|---|---|
| `passwordless-ui-livewire` | Standalone | Blade + vanilla JS | `fetch` | none |
| `passwordless-ui-react` | Standalone | Inertia + React + TS | `fetch` | Inertia |
| `passwordless-ui-vue` | Standalone | Inertia + Vue + TS | `fetch` | Inertia |
| `passwordless-ui-livewire-embed` | Integrated | Blade + Flux + `<x-layouts::auth>` | server-side redirect | Livewire kit |
| `passwordless-ui-react-embed` | Integrated | Inertia page under `pages/auth/*` | server-side redirect | React kit |
| `passwordless-ui-vue-embed` | Integrated | Inertia page under `pages/auth/*` | server-side redirect | Vue kit |

```bash
# Standalone (greenfield)
php artisan vendor:publish --tag=passwordless-ui-livewire
php artisan vendor:publish --tag=passwordless-ui-react
php artisan vendor:publish --tag=passwordless-ui-vue

# Integrated with an official starter kit
php artisan vendor:publish --tag=passwordless-ui-livewire-embed
php artisan vendor:publish --tag=passwordless-ui-react-embed
php artisan vendor:publish --tag=passwordless-ui-vue-embed
```

Every stub is a two-step **email → code** flow (paste-to-fill, auto-submit) with an optional
"email me a magic link" affordance, dark mode, and reduced-motion support. Affordances follow the
strategies you've enabled in `config/passwordless.php`. Each also publishes a **commented example
route** (`routes/passwordless-ui.php`) — the package registers no page route, so you wire it up.
The `-embed` route names are `passwordless.*` so they coexist with a starter kit's own `login`.

Every variant is browser-tested end-to-end (email → code → authenticated dashboard) against a real
Laravel starter kit.

## Security defaults

All on by default:

- **Hashed at rest** — tokens and codes stored as SHA-256, single-use, TTL-bound.
- **Email enumeration protection** — request endpoints respond identically for known and unknown emails.
- **Same-browser enforcement** for magic links — a link only consumes from the browser that requested it. Toggle: `strategies.magic_link.same_browser`.
- **Resend cooldown** — default 30s between requests for the same email (`429` + `Retry-After`).
- **Per-strategy lockout** — after N failed verifies, lock the email/strategy for a window (`423` + `Retry-After`). Default 5 attempts / 15 minutes.
- **Burst throttle** middleware — per-email and per-IP, with separate limits for request vs. verify.

## Configuration

Every option is documented inline in `config/passwordless.php`. A brief tour:

```php
return [
    'user_model' => App\Models\User::class,
    'user_email_column' => 'email',
    'auto_create_users' => false,

    'guard' => 'web',
    'route_prefix' => 'auth',
    'redirect' => '/',          // where the UI kit sends users after login
    'api_mode' => false,        // return a token instead of a session login

    'resend_cooldown' => 30,
    'lockout' => ['max_attempts' => 5, 'window' => 15 * 60],

    'branding' => [
        'app_name' => env('APP_NAME'),
        'support_email' => null,
    ],

    'strategies' => [
        'magic_link' => ['enabled' => true, 'ttl' => 15 * 60, 'same_browser' => true],
        'login_code' => ['enabled' => true, 'length' => 6, 'ttl' => 10 * 60, 'channel' => 'mail'],
    ],

    'social' => [
        'providers' => ['google', 'github' => ['scopes' => ['read:user']]],
        'auto_register' => true,
        'trusted_providers' => ['google', 'github', 'apple', /* … */], // treated as verified-email
    ],

    'domains' => [
        'allowed' => [],          // empty = unrestricted
        'enforce' => [
            'passwordless' => ['login' => false, 'register' => true],
            'social'       => ['login' => false, 'register' => true],
        ],
    ],
];
```

## Events

Listen for the full lifecycle (namespace `Webteractive\Passwordless\Events`):

| Event | Fired when |
|---|---|
| `MagicLinkRequested` | a magic link is requested |
| `MagicLinkConsumed` | a magic link is successfully consumed |
| `LoginCodeRequested` | a login code is requested |
| `LoginCodeVerified` | a login code is verified |
| `LoginCodeFailed` | a login code verification fails |
| `SocialAuthenticated` | a social provider authenticates a user (carries provider, registered, linked) |
| `AuthenticationDenied` | the pre-auth gate or a domain rule denies (carries the reason) |
| `UserAuthenticated` | any strategy authenticates a user (umbrella) |

Prefer a single hook over subscribing to each? See the [audit funnel](#audit-funnel).

## Extending

### Pre-auth gate

Run a check after user resolution but before login. Denials return `403` and fire `AuthenticationDenied`.

```php
use Webteractive\Passwordless\Facades\Passwordless;

Passwordless::gateUsing(fn ($user, $context) =>
    $user->is_active
        ? Passwordless::allow()
        : Passwordless::deny('account disabled')
);
```

### Audit funnel

One hook for every authentication event — handy for a custom audit table.

```php
use Webteractive\Passwordless\Support\AuthEvent;

Passwordless::recordUsing(fn (AuthEvent $event) => AuditLog::write($event));
```

### Custom login-code channels

Email is the built-in channel. Add SMS, WhatsApp, etc. by implementing the contract:

```php
use Webteractive\Passwordless\Contracts\LoginCodeChannel;

class SmsChannel implements LoginCodeChannel
{
    public function send(mixed $user, string $email, string $code, array $context = []): void
    {
        // Twilio, Vonage, etc.
    }
}

// Register it in a service provider:
$this->app->bind('passwordless.login_code_channels.sms', SmsChannel::class);
```

```php
// config/passwordless.php
'strategies' => [
    'login_code' => ['channel' => 'sms'],
],
```

## API mode (Sanctum)

Set `api_mode => true` (or wrap the endpoints in your own controller). Successful verification
returns `{ token, user }` instead of logging into the session guard. Your `User` model must use
`Laravel\Sanctum\HasApiTokens`. For SPA/mobile clients, register the endpoints via `routes/api.php`.

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
php artisan passwordless:prune   # delete expired / consumed challenges
```

Schedule it (e.g. in `routes/console.php` or your scheduler):

```php
Schedule::command('passwordless:prune')->hourly();
```

## Contributing

```bash
composer test      # Pest suite (sqlite in-memory)
composer analyse   # Larastan
composer format    # Pint
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Security

Report vulnerabilities privately via GitHub security advisories.

## License

MIT — see [LICENSE.md](LICENSE.md).
