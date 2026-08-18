# Laravel Passwordless

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webteractive/laravel-passwordless.svg?style=flat-square)](https://packagist.org/packages/webteractive/laravel-passwordless)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/webteractive/laravel-passwordless/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/webteractive/laravel-passwordless/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/webteractive/laravel-passwordless.svg?style=flat-square)](https://packagist.org/packages/webteractive/laravel-passwordless)

Drop-in **passwordless authentication** for Laravel 11, 12, and 13 — **magic links**, **email
login codes**, and **social (OAuth) login**. Headless by design: it ships secure JSON endpoints,
events, and notifications, and stays out of the way of your frontend. An **optional, opt-in UI
kit** is available when you want a login page without building one.

```http
POST /auth/login-code          { "email": "ada@example.com" }        → 202 sent
POST /auth/login-code/verify   { "email": "ada@example.com", "code": "123456" }  → 204 (logged in)
```

## Features

- ✉️ **Magic link** — signed, single-use, time-limited URL, with optional same-browser enforcement.
- 🔢 **Login code** — short numeric OTP over email (SMS/WhatsApp/etc. via a pluggable channel contract).
- 🪄 **magicCode** — one email with both a magic link *and* a code; sign in with either, first one wins. Opt-in.
- 🌐 **Social login (OAuth)** — Google, GitHub, and any Socialite provider: verified-email account linking, auto-registration, and encrypted token storage. Install the driver + add keys → it works.
- 🔐 **Honours starter-kit 2FA** — when a user has Laravel Fortify two-factor enabled, every flow hands off to Fortify's challenge instead of logging them in. Fortify stays optional; apps without it are unaffected.
- 🔑 **2FA for password-less accounts** — an emailed identity-confirmation code satisfies Laravel's `password.confirm` gate, so users with no password can still turn 2FA on from a starter kit's settings page.
- ☑️ **Remember me** — across every flow, persisted on the challenge so it survives the magic-link round trip.
- 🧑‍💻 **Dev login** — an opt-in, local-only user picker for fast development sign-in, behind a three-condition guard with a permanent production denylist.
- 🚧 **Domain limiting** — restrict which email domains may log in and/or auto-register, per strategy type.
- 🛡️ **Secure by default** — hashing at rest, single-use, enumeration protection, lockout, resend cooldown, and burst throttling — all on out of the box.
- 🔌 **Headless** — JSON endpoints, lifecycle events, a pre-auth gate, and an audit funnel. Bring any frontend.
- 🎨 **Optional UI kit** — publish a ready-made login page for Blade, React, or Vue (standalone or matched to an official starter kit). Nothing is routed unless you opt in.
- 🧪 **Test-friendly** — `Passwordless::fake()` for assertion-only strategy stubs.
- 🔐 **Session or API mode** — Laravel's session guard by default; a Sanctum-style `{ token, user }` in `api_mode`.

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
- [Two-factor authentication (Fortify)](#two-factor-authentication-fortify)
- [Enabling 2FA without a password](#enabling-2fa-without-a-password)
- [Remember me](#remember-me)
- [Dev login (user selection)](#dev-login-user-selection)
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

Publish and run the migrations (`passwordless_challenges` + `passwordless_social_accounts`):

```bash
php artisan vendor:publish --tag="passwordless-migrations"
php artisan migrate
```

Re-run both after upgrading the package — releases occasionally add a migration, and
publishing skips every file you already have rather than duplicating or re-timestamping
it. See the [changelog](CHANGELOG.md) for which releases need this (0.1.5 does).

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

- **Two tables, `users` untouched.** `passwordless_challenges` holds ephemeral magic-link tokens
  and login codes (hashed, single-use, TTL-bound — prune with `passwordless:prune`).
  `passwordless_social_accounts` persists linked OAuth identities (tokens encrypted at rest). Neither
  touches your `users` table.
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
| `POST` | `/auth/magic-code` | request a combined link + code (magicCode) |
| `GET`  | `/auth/magic-code/{token}` | consume the magicCode link and sign in |
| `POST` | `/auth/magic-code/verify` | verify the magicCode code and sign in |
| `GET`  | `/auth/social/{provider}/redirect` | start the OAuth flow |
| `GET`  | `/auth/social/{provider}/callback` | handle the OAuth callback and sign in |
| `POST` | `/auth/confirm/send` | email an identity-confirmation code (**auth required**) |
| `GET`  | `/auth/dev-login` | list users for the dev picker (**local only, absent otherwise**) |
| `POST` | `/auth/dev-login` | sign in the selected user (**local only, absent otherwise**) |

The two `dev-login` routes are not registered unless the
[dev-login guard](#dev-login-user-selection) passes — they `404` rather than `403` everywhere else.

Every sign-in endpoint may instead hand off to Fortify's two-factor challenge when the user has 2FA
enabled — see [Two-factor authentication](#two-factor-authentication-fortify). All of them also
accept an optional `remember` flag; see [Remember me](#remember-me).

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

## magicCode (link + code in one email)

`magicCode` sends **one** email containing both a magic link and a numeric code. The user
authenticates with whichever suits their device — click the link on the same machine, or type the
code on a phone. The **first path used wins**; the other is invalidated immediately.

It's **opt-in** (disabled by default) and **email-only**. Enable it:

```php
// config/passwordless.php
'strategies' => [
    'magic_code' => [
        'enabled' => true,
        'ttl' => 15 * 60,        // shared TTL for BOTH the link and the code
        'same_browser' => true,  // enforced on the LINK path only
        'code' => ['length' => 6],
    ],
],
```

Flow:

```
POST /auth/magic-code            { email }                 -> 202 (always)
GET  /auth/magic-code/{token}    (signed link click)       -> sign in + redirect
POST /auth/magic-code/verify     { email, code }           -> 204 (sign in)
```

While disabled, all three routes return `404`. The link path enforces same-browser (via the signed
cookie) just like `magicLink`; the **code path is intentionally device-agnostic**, so a user can
request on desktop and type the code on their phone — that flexibility is the whole point. All the
usual protections apply: enumeration-safe send, resend cooldown, per-email lockout on failed code
verifies, hashed-at-rest secrets, single-use.

```php
use Webteractive\Passwordless\Facades\Passwordless;

Passwordless::magicCode()->send('user@example.com');
```

## Two-factor authentication (Fortify)

The official Laravel starter kits ship TOTP two-factor authentication via **Laravel Fortify**. When
a user has it enabled, this package **stops short of logging them in** and hands off to Fortify's
own challenge — across every strategy: magic link, login code, magicCode, social, and the published
embed controllers.

Nothing to configure. Fortify is **not** a dependency of this package; detection is duck-typed and
apps without it behave exactly as before:

```php
// What the package checks, in effect:
class_exists(Laravel\Fortify\Fortify::class)
    && in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))
    && $user->hasEnabledTwoFactorAuthentication()
```

On a match it writes Fortify's own session contract (`login.id`, `login.remember`), dispatches
`TwoFactorAuthenticationChallenged`, and redirects to `two-factor.login` — or returns
`{"two_factor": true}` for JSON requests. Fortify's challenge controller then completes the login
unchanged, including recovery codes and the remember flag.

**Requirements and failure modes — all fail closed by design:**

| Condition | Result |
| --- | --- |
| `config('passwordless.guard')` ≠ `config('fortify.guard')` | `TwoFactorGuardMismatchException` |
| Fortify installed but its 2FA feature is off (`two-factor.login` route absent) | `TwoFactorChallengeUnavailableException` |
| `api_mode` and the user has 2FA enabled | `409 {"two_factor": true}` — **no token issued** |

The exceptions are deliberate. Falling through to `login()` in either case would silently bypass the
user's second factor, so a misconfiguration is a loud error rather than a quiet downgrade.

> **`passwordless.user_model` must be the same class as `auth.providers.users.model`.** Fortify
> resolves the challenged user through the auth provider's model, so if the two differ, the model
> Fortify loads may lack the `TwoFactorAuthenticatable` trait and recovery codes will fail.

`api_mode` cannot complete the challenge — Fortify's is session-based. The package withholds the
token instead of issuing one; complete the challenge over a session route, or handle 2FA yourself.

You can also drive the handoff directly, which is what the published embed controllers do:

```php
if (Passwordless::twoFactor()->required($user)) {
    return Passwordless::twoFactor()->challenge($user, $request, $remember);
}
```

## Enabling 2FA without a password

The starter kits gate *enabling* 2FA behind `Features::twoFactorAuthentication(['confirmPassword' => true])`,
which routes through Laravel's `password.confirm` middleware. A passwordless-only user has no
password hash, so they can never satisfy it — and therefore can never turn 2FA on.

The fix is an emailed **identity confirmation** code that stands in for the password. Publish an
embed UI kit (see [Optional UI kit](#optional-ui-kit)) and register the published provider in
`bootstrap/providers.php`; it wires Fortify's own confirm-password flow to the package:

```php
Fortify::confirmPasswordsUsing(function ($user, $password) {
    // Users who DO have a password keep the normal path.
    if ($password && $user->getAuthPassword() && Auth::guard(config('fortify.guard'))->validate([
        Fortify::username() => $user->{Fortify::username()},
        'password' => $password,
    ])) {
        return true;
    }

    return Passwordless::confirmation()->verify($user, (string) $password);
});
```

Because this reuses Fortify's endpoint, **Fortify** still stamps `auth.password_confirmed_at` on
success — so `two-factor.enable`, `two-factor.confirm`, `two-factor.disable` and recovery-code
regeneration all pass with no route overrides.

`POST /auth/confirm/send` (auth required) emails the code. Confirmation challenges are stored in
`passwordless_challenges` as `type = confirm` and pruned by `passwordless:prune` like any other.

> **`Fortify::confirmPasswordsUsing()` is global.** The published callback already composes both
> paths (real password *or* emailed code). If your app registers its own callback elsewhere, merge
> them rather than registering twice — the last one wins.

Its resend cooldown and lockout use a **separate key namespace** from login, deliberately: a login
cooldown must not lock a user out of their own security settings, and failed confirmation attempts
must not lock them out of logging in.

```php
'confirmation' => [
    'enabled' => true,   // requires an authenticated user; emails only their own address
    'length'  => 6,
    'ttl'     => 10 * 60,
],
```

If you would rather not have this at all, set `'confirmPassword' => false` in your Fortify features
— but that drops the re-authentication guard around enabling and disabling 2FA entirely.

## Remember me

All flows accept a `remember` flag and issue a long-lived recaller cookie via the session guard.

The wrinkle it solves: for magic links the checkbox is ticked when the email is **requested**, but
the login happens in a **later** request when the link is clicked. So the flag is persisted on the
challenge row and read back at consume time.

| Flow | Captured at | Stored in | Read at |
| --- | --- | --- | --- |
| Magic link | send | `Challenge.metadata['remember']` | consume |
| magicCode (link) | send | `Challenge.metadata['remember']` | consume |
| Login code | send, overridable on verify | `Challenge.metadata['remember']` | verify |
| magicCode (code) | send, overridable on verify | `Challenge.metadata['remember']` | verify |
| Social | redirect | session `passwordless.remember` | callback |
| Dev login | the POST itself | — | immediately |

Where a verify request carries its own `remember` key, that value wins — it is the user's most
recent expressed intent, in their own session.

```php
'remember' => [
    'enabled' => true,   // false forces remember off everywhere, whatever clients send
],
```

Ignored in `api_mode`: remember-me is a session-cookie concept with no meaning for a Sanctum token.
Token lifetime is your `sanctum` config's business.

## Dev login (user selection)

> ### ⚠️ Read this before enabling
>
> This is a user picker that signs in **any** user with **no credential**. Enabled in a shared or
> production environment it is a total authentication bypass. It exists for local development only.

Off by default. Three **independent** conditions must all hold before the routes are registered
*at all* — when any fails the endpoints do not exist and return `404`, rather than existing and
returning `403`:

1. `dev_login.enabled` is **strictly** `true` (a stray `"1"` does not count)
2. the current `APP_ENV` is listed in `dev_login.environments`
3. the app is not in production — a permanent denylist that `environments` **cannot** override

```php
'dev_login' => [
    'enabled'      => false,      // deliberately a literal, not env() — see below
    'environments' => ['local'],
    'two_factor'   => false,      // dev logins skip the 2FA challenge by default
    'limit'        => 50,
],
```

There is intentionally **no `env()` default**, so no stray environment variable can switch this on —
enabling it is a deliberate edit to your published config. If you want env control, change that line
to `env('PASSWORDLESS_DEV_LOGIN', false)` yourself, and never set the variable outside local dev.

| Endpoint | Purpose |
| --- | --- |
| `GET /auth/dev-login` | Lists at most `limit` users as `{id, name, email}` — optional `?q=` filters by email. Never returns password hashes, remember tokens, or 2FA secrets. |
| `POST /auth/dev-login` | Signs in `{user, remember?}` through the same seam as a real login. |

Dev logins fire `UserAuthenticated('dev_login', $user)` and pass through
[`Passwordless::recordUsing()`](#audit-funnel), so your audit hook sees them as their own strategy
rather than as a magic link. They **bypass** the 2FA challenge by default — a shortcut that demands
a TOTP defeats its purpose. Set `dev_login.two_factor => true` to exercise that path instead.

The published UI stubs render the picker only when the endpoint is reachable, so a stub that ships to
production is inert: the route is absent and the control never appears.

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
| `MagicCodeRequested` | a magicCode (link + code) is requested |
| `MagicCodeConsumed` | a magicCode link is consumed |
| `MagicCodeVerified` | a magicCode code is verified |
| `MagicCodeFailed` | a magicCode code verification fails |
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

### Post-auth redirect

Customize where server-driven logins land — the social callback and the published
embed controllers. The closure receives `($user, $request)` and returns a URL. It
is used as the fallback for `redirect()->intended(...)`, so a middleware-set
intended URL (e.g. the page a guest was bounced from) still wins; the closure only
decides where you land otherwise. When no closure is set, `config('passwordless.redirect')`
is used.

```php
use Webteractive\Passwordless\Facades\Passwordless;

Passwordless::redirectUsing(fn ($user, $request) =>
    $user->is_admin ? '/admin' : '/dashboard'
);
```

> The headless magic-link and login-code endpoints return `204`/JSON and never
> redirect, so this hook does not apply to them — your frontend navigates itself.

### Two-factor and identity confirmation

Both are public API, safe to call whether or not Fortify is installed:

```php
Passwordless::twoFactor()->required($user);                    // bool — false without Fortify
Passwordless::twoFactor()->challenge($user, $request, $remember); // Response — hands off to Fortify

Passwordless::confirmation()->send($user);                     // emails a confirmation code
Passwordless::confirmation()->verify($user, $code);            // bool — Fortify-callback shaped
```

See [Two-factor authentication](#two-factor-authentication-fortify) and
[Enabling 2FA without a password](#enabling-2fa-without-a-password).

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
