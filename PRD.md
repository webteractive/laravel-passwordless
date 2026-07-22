# PRD — laravel-passwordless

A Laravel package providing passwordless authentication strategies for Laravel apps.

> **Descoped (2026-07): Passkeys / WebAuthn.** The passkey strategy was removed from the
> package — Laravel Fortify now ships first-party WebAuthn/passkey support, making a redundant
> implementation here unnecessary. All passkey-related sections below (strategy 3, the
> `passwordless_credentials` table, `/auth/passkeys/*` routes, `Passkey*` events, the `passkeys`
> config block, the `passwordless:passkeys` command) are **retained for historical context only**
> and do not reflect the shipped package, which provides **magic link + login code** against a
> single `passwordless_challenges` table.

## Goals
- Drop-in passwordless auth for any Laravel app (11/12/13).
- Multiple strategies, composable, opt-in per app.
- Sensible defaults, minimal config to get started.
- First-class Laravel idioms: events, notifications, guards, middleware, config publishing.

## Non-Goals
- Full identity provider (no OAuth server, no SSO broker).
- Frontend scaffolds, starter kits, or views. Backend only — strictly headless.
- User registration flows beyond what passwordless login implies.
- **Recovery codes.** Antithetical to passwordless. If apps need a backstop, that's their job.
- **Built-in SMS for login code.** Not shipped. The login-code channel is an extensible contract — apps add SMS, WhatsApp, etc. by implementing it. Email is the only built-in driver.
- **Built-in audit log table.** Events cover this. Apps that want persistent audit can listen and write to their own table.
- **Passkeys / WebAuthn.** Descoped — use Laravel Fortify's first-party passkey support.

## Strategies

### 1. Magic Link
- User submits email → package emails a signed, single-use, time-limited URL.
- Clicking the link authenticates and redirects.
- Configurable: TTL, signing key, route, redirect, throttle.
- Replay protection: token consumed on first use.

### 2. Login Code (Email OTP)
- User submits email → package emails a short numeric code (e.g. 6 digits).
- User submits code → authenticated.
- Configurable: code length, TTL, max attempts, lockout, throttle.
- Constant-time comparison; rate limit per email + per IP.

### 3. Passkeys (WebAuthn) — DESCOPED (removed; see banner at top; use Fortify)
- Register and authenticate via WebAuthn / FIDO2.
- Backed by `web-auth/webauthn-lib` (reference PHP impl, no Laravel opinions to fight).
- Multiple credentials per user; nameable; revocable.
- Fallback to magic link or login code when unsupported.
- Marked experimental until passkey ergonomics are proven across Laravel 11/12/13.

## Architecture

- **Service provider** extends `Spatie\LaravelPackageTools\PackageServiceProvider`; registers config, routes, migrations, notifications, commands fluently.
- **Contracts** for each strategy so apps can swap implementations.
- **Drivers** registered in `config/passwordless.php`; enable/disable per strategy.
- **Storage**: two tables — `passwordless_challenges` (ephemeral) and `passwordless_credentials` (persistent). No pollution of `users`.
- **User resolution**: looks up the app's default auth user model by `email` column (configurable). Unknown emails fail authentication unless `auto_create_users` is enabled in config, in which case a user is created on first successful authentication.
- **Guard integration**: works with Laravel's session guard out of the box; Sanctum/Passport compatible via documented recipes.

## Schema

### `passwordless_challenges` (ephemeral: link, code, passkey ceremony)
| column | type | notes |
|---|---|---|
| id | pk | |
| user_id | fk, nullable, cascadeOnDelete | nullable for passkey discovery flows |
| type | enum(link, code, passkey) | |
| hash | string, indexed | hashed token / code / WebAuthn challenge |
| metadata | json, nullable | attempts, IP, user agent, ceremony state |
| expires_at | timestamp | |
| consumed_at | timestamp, nullable | single-use marker |
| timestamps | | |

Cleanup job: delete where `consumed_at is not null` or `expires_at < now()`.

### `passwordless_credentials` (persistent: passkeys)
| column | type | notes |
|---|---|---|
| id | pk | |
| user_id | fk, cascadeOnDelete | |
| credential_id | string, unique, indexed | WebAuthn credential ID |
| public_key | text | |
| sign_count | unsigned int | |
| transports | json, nullable | usb, nfc, ble, internal, hybrid |
| aaguid | string, nullable | authenticator model |
| name | string, nullable | user-facing label |
| last_used_at | timestamp, nullable | |
| timestamps | | |

## Public API (sketch)

```php
Passwordless::magicLink()->send($email);
Passwordless::loginCode()->send($email);
Passwordless::loginCode()->verify($email, $code);
Passwordless::passkeys()->registerOptions($user);
Passwordless::passkeys()->verifyRegistration($user, $payload);
Passwordless::passkeys()->authenticateOptions($email);
Passwordless::passkeys()->verifyAuthentication($payload);

// Pre-auth gate — closure or contract; deny halts authentication with reason
Passwordless::gateUsing(fn ($user, $context) =>
    $user->is_active
        ? Passwordless::allow()
        : Passwordless::deny('account disabled')
);

// Custom audit / observability hook
Passwordless::recordUsing(fn (AuthEvent $event) => MyAuditWriter::write($event));
```

Routes (publishable, prefix configurable):
- `POST /auth/magic-link` — request link
- `GET  /auth/magic-link/{token}` — consume link
- `POST /auth/login-code` — request code
- `POST /auth/login-code/verify` — verify code
- `POST /auth/passkeys/register/options`
- `POST /auth/passkeys/register`
- `POST /auth/passkeys/authenticate/options`
- `POST /auth/passkeys/authenticate`
- `GET    /auth/passkeys` — list current user's passkeys (auth required)
- `PATCH  /auth/passkeys/{id}` — rename
- `DELETE /auth/passkeys/{id}` — revoke

All request endpoints respond identically whether the email exists or not (enumeration protection).

## Events
- `MagicLinkRequested`, `MagicLinkConsumed`
- `LoginCodeRequested`, `LoginCodeVerified`, `LoginCodeFailed`
- `PasskeyRegistered`, `PasskeyAuthenticated`, `PasskeyRevoked`, `PasskeyRenamed`
- `PasskeyCloneDetected` — fired when sign count goes backward, triggers forced re-registration
- `AuthenticationDenied` — fired when the pre-auth gate denies; carries reason
- `UserAuthenticated` — umbrella, fired by all strategies after gate passes

## Notifications
- `MagicLinkNotification`, `LoginCodeNotification` — markdown mail by default, publishable.
- Translatable strings ship with publishable lang files.
- Branding (app name, support email, primary color) configurable via `config/passwordless.php` without publishing views.

## Middleware

- **Session mode** (default): routes registered in the `web` group — session, CSRF, cookies.
- **API mode**: routes registered in the `api` group. Request/verify endpoints stay public (no `auth:sanctum`); passkey CRUD endpoints (`GET/PATCH/DELETE /auth/passkeys/...`) require `auth:sanctum`.
- **Same-browser cookie** for magic links is a separate signed cookie, independent of session — works in both modes.
- Apps in API mode opt out of CSRF for these routes by virtue of the `api` group.

## HTTP Responses

| Scenario | Status | Body / Headers |
|---|---|---|
| Request link/code (always, known or unknown email) | `202` | `{ "status": "sent" }` |
| Verify success — session mode | `204` | session cookie set |
| Verify success — API mode | `200` | `{ "token": "...", "user": {...} }` |
| Validation error | `422` | `{ "message", "errors": { field: [...] } }` (Laravel default) |
| Invalid / expired token or code | `401` | `{ "message": "Invalid or expired" }` (deliberately vague) |
| Resend cooldown active | `429` | `Retry-After: <s>`, `{ "message": "Please wait", "retry_after": <s> }` |
| Locked out (max attempts) | `423` | `Retry-After: <s>`, `{ "message": "Locked", "retry_after": <s> }` |
| Pre-auth gate denied | `403` | `{ "message": "<reason from gate>" }` |

## Same-Browser Cookie (magic links)

- **Name**: `passwordless_browser` (configurable).
- **Value**: signed random 32-byte token; SHA-256 hash stored on the challenge's `metadata.browser_hash`.
- **Lifetime**: matches the challenge TTL.
- **Attributes**: `SameSite=Lax`, `Secure` in production, `HttpOnly`.
- **Mismatch**: missing or different cookie on link consumption → `401`, body `{ "message": "different browser" }` so apps can offer a resend UX.
- **Disable**: `strategies.magic_link.same_browser => false`.

## Token & Code Format

**Magic link token**
- 32 bytes of CSPRNG (`random_bytes(32)`), encoded as base64url (~43 chars).
- Stored as SHA-256 hash. Lookup: hash incoming token, query by hash.
- Rationale: 256 bits of entropy → no bcrypt needed.

**Login code**
- Numeric only. Default length **6**, configurable **6–10**.
- Stored as SHA-256 of the normalized code (whitespace stripped).
- Leading zeros preserved — handled as string throughout, never int.
- Display formatting (e.g. `123 456`) is the notification template's concern.

## Security
- Tokens/codes hashed at rest.
- Single-use, TTL-bound.
- Rate limiting per email + IP for request and verify endpoints.
- Configurable throttle hooks for custom limiters.
- Signed URLs for magic links (Laravel's built-in signed routes).
- WebAuthn challenges stored server-side; origin + RP ID validated.
- **Email enumeration protection** — request endpoints always return identical responses for known and unknown emails. On by default, configurable.
- **Same-browser enforcement for magic links** — on request, set a short-lived signed cookie tying the challenge to that browser. Consuming the link from a different browser fails. Configurable per app (defaults on; some users do want cross-device link clicking).
- **Resend cooldown** — separate from rate limit. After requesting a code/link, the user must wait N seconds before requesting another (default 30s). Returns `429` with retry hint.
- **Per-strategy lockout** — N failed verify attempts within window → lock that email/strategy combo for TTL (default 5 attempts / 15 min lockout). Independent of global rate limit.
- **Sign-count regression handling** — passkey authentication with a sign count ≤ stored value fires `PasskeyCloneDetected`, marks the credential for forced re-registration, and rejects the auth.

## Flow control & integration

- **Pre-auth gate** — register a closure or contract via `Passwordless::gateUsing()` that runs after user resolution but before session login. Allow/deny + reason. Denials fire `AuthenticationDenied`.
- **Post-auth intent** — package captures the URL the user was trying to reach (intended URL stored against the challenge) and redirects there on success. Configurable default redirect.
- **Strategy fallback hints** — failed passkey responses include a JSON hint listing other enabled strategies so clients can offer "try magic link instead." No automatic fallback — the client decides.
- **API mode** — per-route or per-strategy config to return a Sanctum token on successful auth instead of (or in addition to) logging into the session guard. For SPA / mobile.

## Operational

- **`php artisan passwordless:prune`** — deletes consumed/expired challenges. Schedule it.
- **`php artisan passwordless:passkeys`** — list/revoke a user's passkeys from CLI (`--user=`, `--revoke=`).
- **Observability** — events cover most. `Passwordless::recordUsing()` adds a single funnel hook for custom audit writers without subscribing to every event.

## Configuration (excerpt)
```php
return [
    'user_model' => App\Models\User::class,
    'user_email_column' => 'email',
    'auto_create_users' => false,
    'guard' => 'web',
    'route_prefix' => 'auth',
    'enumeration_protection' => true,
    'resend_cooldown' => 30,
    'lockout' => ['max_attempts' => 5, 'window' => 15 * 60],
    'throttle' => [
        'request' => [
            'per_email' => ['max' => 5, 'window' => 10 * 60],
            'per_ip'    => ['max' => 20, 'window' => 10 * 60],
        ],
        'verify' => [
            'per_email' => ['max' => 10, 'window' => 10 * 60],
            'per_ip'    => ['max' => 30, 'window' => 10 * 60],
        ],
    ],
    'redirect' => '/',
    'api_mode' => false, // or per-strategy below
    'branding' => [
        'app_name' => env('APP_NAME'),
        'support_email' => null,
        'primary_color' => '#000000',
    ],
    'strategies' => [
        'magic_link' => [
            'enabled' => true,
            'ttl' => 15 * 60,
            'same_browser' => true,
        ],
        'login_code' => [
            'enabled' => true,
            'length' => 6,
            'ttl' => 10 * 60,
            'channel' => 'mail', // contract; apps may register sms, etc.
        ],
        'passkeys' => [
            'enabled' => false,
            'rp_name' => env('APP_NAME'),
            'rp_id' => null,
            'attestation' => 'none',           // none | indirect | direct
            'user_verification' => 'preferred', // required | preferred | discouraged
            'resident_key' => 'preferred',      // required | preferred | discouraged
            'pub_key_algorithms' => [-7, -257], // ES256, RS256
            'timeout' => 60_000,                // milliseconds
        ],
    ],
];
```

## Scaffold

- Bootstrapped from **`spatie/package-laravel-passwordless-laravel`**. Run its `configure.php` to set vendor, package name, namespace, author, etc.
- Inherits from the laravel-passwordless: Pest 3, Testbench, Pint, PHPStan (Larastan), Rector, GitHub Actions matrix, `.editorconfig`, README template.
- Trim the laravel-passwordless's CI matrix to: PHP `8.3`, `8.4` × Laravel `11`, `12`, `13` × `sqlite-memory` (default), with one extra job each for MySQL and PostgreSQL on the latest combo only.

## Testing
- Pest. Feature tests per strategy. Unit tests for token/code generation, hashing, expiry.
- In-memory SQLite for the package test suite (Testbench-driven).
- Test helpers/fakes: `Passwordless::fake()` to assert sent links/codes without dispatching mail.
- Passkey ceremonies tested via `web-auth/webauthn-lib` test fixtures (no real browser needed).

## Compatibility
- PHP 8.3+ (Laravel 13 floor).
- Laravel 11.x, 12.x, 13.x.
- Database: MySQL, PostgreSQL, SQLite.

## Distribution
- Composer package, MIT license.
- Composer: `webteractive/laravel-passwordless`. PSR-4 root: `Webteractive\\Passwordless\\`.
- Auto-discovery via service provider.

## Milestones
1. Bootstrap from `spatie/package-laravel-passwordless-laravel`; run `configure.php`; trim CI matrix; wire `PackageServiceProvider` with config + migrations.
2. Magic link strategy + tests.
3. Login code strategy + tests.
4. Passkeys strategy (experimental) + tests.
5. Docs + example app.
6. 0.1.0 release.

## Open Questions
_None._
