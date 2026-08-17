# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Status

`v0.1.0` scaffolded — magic link + login code complete. Passkeys were removed (Laravel Fortify provides first-party WebAuthn). Read `PRD.md` for scope and `docs/superpowers/specs/2026-07-22-passwordless-ui-kit-design.md` + `docs/superpowers/plans/2026-07-22-passwordless-ui-kit.md` for the current work (passkey removal + opt-in UI kit).

## What this is

A Laravel package providing passwordless authentication strategies for Laravel 11 / 12 / 13 apps:

1. **Magic link** — signed, single-use, time-limited URL emailed to the user. Production-ready.
2. **Login code** — short numeric OTP emailed to the user. Channel is a contract; email is the only built-in driver. Production-ready.
3. **magicCode** — one email carrying BOTH a magic link and a login code; the user authenticates with either and the first one used wins (the sibling is invalidated). Opt-in (disabled by default), email-only.

## Architectural ground rules (load-bearing)

These are PRD decisions made during planning. Do not relitigate without explicit user direction.

- **Backend only.** No frontend scaffolds, starter kits, Blade views, Livewire, or JS. Strictly headless.
- **One table.**
  - `passwordless_challenges` — ephemeral rows for magic link tokens and login codes (`type` ∈ {`link`, `code`}). magicCode reuses this table with two correlated rows per send (`type` ∈ {`mc_link`, `mc_code`}) sharing a `magic_code_id` in `metadata`. Identity confirmation adds `type => 'confirm'`. Cleaned up by `passwordless:prune`.
  - `metadata` also carries `remember` (chosen at send time, read at consume/verify) alongside `intended_url`.
- **Fortify is never a hard dependency.** It lives in `require-dev` + `suggest` only. 2FA support is duck-typed; apps without Fortify must behave byte-for-byte as before. Note that Fortify resolves the challenged user through `auth.providers.users.model`, so that must be the same class as `passwordless.user_model`.
- **`config/passwordless.php` contains no `env()` calls** — larastan enforces this, and it means `dev_login` cannot be switched on by a stray environment variable.
- **User must already exist by default.** `auto_create_users` config is opt-in.
- **Per-strategy enable/disable** via config flags (read at request time inside controllers, not at route registration).
- **Guard integration** uses Laravel's session guard out of the box; `api_mode` returns Sanctum-style `{ token, user }` instead.

## Hard non-goals

- Recovery codes, built-in SMS, built-in audit log table, passkeys/WebAuthn (use Fortify). The package implements **no second factor of its own** — it only hands off to Fortify's. Fortify owns recovery codes, so the non-goal stands.
- Frontend anything **in the core** — but an **opt-in, publish-only UI kit** is a sanctioned exception (see the spec/plan under `docs/superpowers/`). The headless core stays untouched; UI ships as `vendor:publish` stubs per starter-kit stack.

## Security defaults

- Email enumeration protection — request endpoints return identical responses regardless of email existence.
- Same-browser enforcement for magic links via signed cookie (default on).
- Resend cooldown distinct from rate limit (default 30s).
- Per-strategy lockout after N failed verifies (default 5 / 15 min).
- Tokens/codes hashed at rest (SHA-256), single-use, TTL-bound.

## Code map

- `src/Passwordless.php` — manager (`magicLink/loginCode/social/magicCode/gateUsing/recordUsing/redirectUsing/resolveRedirect/fake`).
- `src/Strategies/{MagicLink,LoginCode,Social,MagicCode}/` — default strategy implementations + per-strategy exceptions. `MagicCode` reuses `LoginCode\CodeGenerator` and all `Support/` primitives; its controllers gate on `strategies.magic_code.enabled` (404 when off).
- `src/Http/Controllers/{MagicLink,LoginCode,Social,MagicCode}/` — invokable controllers. `MagicCode\{Send,Consume,Verify}` — send is one request; the link consume (GET) redirects via `resolveRedirect`, the code verify (POST) returns `204`; each invalidates its sibling on success.
- `src/Http/Middleware/PasswordlessThrottle.php` — request/verify burst throttle.
- `src/Support/` — `Decision`, `AuthEvent`, `TokenHasher`, `EnumerationGuard`, `ResendCooldown`, `Lockout`, `BrowserCookie`, `UserResolver`, plus:
  - `AuthCompletion` — **the single seam** every flow uses to turn a verified user into a session or API token. Owns the `api_mode` token shape and the Fortify 2FA interception. Returns `null` = "logged in, emit your own success response"; a `Response` = "return this verbatim, nobody was logged in". Do not call `auth()->login()` in a controller; go through here.
  - `TwoFactor` — duck-typed Fortify 2FA handoff (`required()` / `challenge()`), exposed as `Passwordless::twoFactor()`. **Never import a Fortify class in a way that autoloads when Fortify is absent.** Fails closed via `TwoFactorGuardMismatchException` / `TwoFactorChallengeUnavailableException` rather than falling through to login.
  - `IdentityConfirmation` — emailed re-confirmation (`type => 'confirm'` challenge) so password-less users satisfy `password.confirm`; exposed as `Passwordless::confirmation()`. Uses a `confirm` cooldown/lockout key namespace, deliberately separate from login.
  - `RememberFlag` — single owner of the remember-me flag. Strategies `stash()` it on the request from challenge metadata; controllers `resolve()` it. Both sides live here so the channel stays explicit.
- `src/Http/Controllers/Confirmation/SendController.php` — emails a confirmation code (`auth` middleware; no `PasswordlessThrottle`, which keys on an email field this request lacks).
- `src/Http/Controllers/DevLogin/` — dev user-selection index/store. Routes are registered **only** when all three `dev_login` guard conditions pass, so they 404 rather than 403 elsewhere.
- `src/Channels/MailLoginCodeChannel.php` — built-in login-code channel; new channels register at `passwordless.login_code_channels.{name}`.
- `src/Models/Challenge.php` — Eloquent model with scopes/casts.
- `src/Events/` — full lifecycle events (`MagicLinkRequested/Consumed`, `LoginCodeRequested/Verified/Failed`, `MagicCodeRequested/Consumed/Verified/Failed`, `AuthenticationDenied`, `UserAuthenticated`).
- `src/Notifications/{MagicLink,LoginCode,MagicCode}Notification.php` — markdown mail. `MagicCodeNotification($url, $code, $ttl)` renders the link button and the code in one message.
- `src/Testing/PasswordlessFake.php` and the per-strategy fakes — used by `Passwordless::fake()`.
- `routes/web.php` — all routes registered unconditionally; per-strategy gating belongs inside controllers.
- `config/passwordless.php` — full option surface.
- `stubs/ui/{livewire,react,vue,livewire-embed,react-embed,vue-embed}/` — opt-in UI kit sources (NOT autoloaded). Registered as `vendor:publish` tags in `PasswordlessServiceProvider::packageBooted()`. Nothing routed by default. Two modes: **standalone** (`passwordless-ui-{livewire,react,vue}`) = self-contained pages, own layout, `fetch` → JSON endpoints (greenfield); **integrated** (`passwordless-ui-{livewire,react,vue}-embed`) = copies an official starter kit's auth layout/conventions and drives the flow server-side via the package's PHP API through a published Fortify-style `PasswordlessLoginController`. All three `-embed` variants proven end-to-end (browser-tested) against real scaffolded starter kits under `~/AI/passwordless-playground{,-react,-vue}`.

## Commands

- `composer test` — Pest suite (sqlite in-memory).
- `composer analyse` — Larastan.
- `composer format` — Pint.
- `php artisan passwordless:prune` — schedule hourly.

## Tests

Pest under `tests/`. Workbench user models at `workbench/app/Models/User.php` (plain) and
`TwoFactorUser.php` (same table, plus Fortify's `TwoFactorAuthenticatable`) — 2FA tests opt in by
pointing **both** `passwordless.user_model` and `auth.providers.users.model` at the latter. A third,
`UuidUser.php`, has a uuid primary key and no `name` column, and exists to stop code assuming `id`
and `name` exist on the user table.
`TestCase::reloadPasswordlessRoutes()` re-registers the package route file against current config,
which is how the dev-login registration guard is tested. Pest helper functions are **global**, so
new helpers need unique names (several collisions were hit during development). The TestCase boots the package provider and runs every `database/migrations/*.php.stub` plus `tests/database/migrations/*.php` — add new package migrations as `*.php.stub` files only.

## Extension surface

- `Passwordless::gateUsing(closure)` — pre-auth allow/deny.
- `Passwordless::recordUsing(closure)` — single observability funnel.
- `Passwordless::redirectUsing(closure)` — customize the post-auth redirect fallback for server-driven flows (social callback + embed controllers). Receives `($user, $request)`, returns a URL; used as the `intended()` fallback so a middleware-set intended URL still wins.
- `Passwordless::twoFactor()` — `required($user)` / `challenge($user, $request, $remember)`. Safe with Fortify absent (`required()` returns false).
- `Passwordless::confirmation()` — `send($user)` / `verify($user, $code)`. `verify()` returns a bool because that is Fortify's `confirmPasswordsUsing` contract.
- `Passwordless::fake()` — test helper.
- `LoginCodeChannel` contract for SMS/WhatsApp/etc.
- Per-strategy contract bindings — swap implementations via the container.
