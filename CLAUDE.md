# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Status

`v0.1.0` scaffolded — magic link + login code complete. Passkeys were removed (Laravel Fortify provides first-party WebAuthn). Read `PRD.md` for scope and `docs/superpowers/specs/2026-07-22-passwordless-ui-kit-design.md` + `docs/superpowers/plans/2026-07-22-passwordless-ui-kit.md` for the current work (passkey removal + opt-in UI kit).

## What this is

A Laravel package providing two passwordless authentication strategies for Laravel 11 / 12 / 13 apps:

1. **Magic link** — signed, single-use, time-limited URL emailed to the user. Production-ready.
2. **Login code** — short numeric OTP emailed to the user. Channel is a contract; email is the only built-in driver. Production-ready.

## Architectural ground rules (load-bearing)

These are PRD decisions made during planning. Do not relitigate without explicit user direction.

- **Backend only.** No frontend scaffolds, starter kits, Blade views, Livewire, or JS. Strictly headless.
- **One table.**
  - `passwordless_challenges` — ephemeral rows for magic link tokens and login codes (`type` ∈ {`link`, `code`}). Cleaned up by `passwordless:prune`.
- **User must already exist by default.** `auto_create_users` config is opt-in.
- **Per-strategy enable/disable** via config flags (read at request time inside controllers, not at route registration).
- **Guard integration** uses Laravel's session guard out of the box; `api_mode` returns Sanctum-style `{ token, user }` instead.

## Hard non-goals

- Recovery codes, built-in SMS, built-in audit log table, passkeys/WebAuthn (use Fortify).
- Frontend anything **in the core** — but an **opt-in, publish-only UI kit** is a sanctioned exception (see the spec/plan under `docs/superpowers/`). The headless core stays untouched; UI ships as `vendor:publish` stubs per starter-kit stack.

## Security defaults

- Email enumeration protection — request endpoints return identical responses regardless of email existence.
- Same-browser enforcement for magic links via signed cookie (default on).
- Resend cooldown distinct from rate limit (default 30s).
- Per-strategy lockout after N failed verifies (default 5 / 15 min).
- Tokens/codes hashed at rest (SHA-256), single-use, TTL-bound.

## Code map

- `src/Passwordless.php` — manager (`magicLink/loginCode/gateUsing/recordUsing/fake`).
- `src/Strategies/{MagicLink,LoginCode}/` — default strategy implementations + per-strategy exceptions.
- `src/Http/Controllers/{MagicLink,LoginCode}/` — invokable controllers.
- `src/Http/Middleware/PasswordlessThrottle.php` — request/verify burst throttle.
- `src/Support/` — `Decision`, `AuthEvent`, `TokenHasher`, `EnumerationGuard`, `ResendCooldown`, `Lockout`, `BrowserCookie`, `UserResolver`.
- `src/Channels/MailLoginCodeChannel.php` — built-in login-code channel; new channels register at `passwordless.login_code_channels.{name}`.
- `src/Models/Challenge.php` — Eloquent model with scopes/casts.
- `src/Events/` — full lifecycle events (`MagicLinkRequested/Consumed`, `LoginCodeRequested/Verified/Failed`, `AuthenticationDenied`, `UserAuthenticated`).
- `src/Notifications/{MagicLink,LoginCode}Notification.php` — markdown mail.
- `src/Testing/PasswordlessFake.php` and the per-strategy fakes — used by `Passwordless::fake()`.
- `routes/web.php` — all routes registered unconditionally; per-strategy gating belongs inside controllers.
- `config/passwordless.php` — full option surface.
- `stubs/ui/{livewire,flux,react,vue}/` — opt-in UI kit sources (NOT autoloaded). Registered as `vendor:publish` tags `passwordless-ui-{livewire,flux,react,vue}` in `PasswordlessServiceProvider::packageBooted()`. Nothing routed by default. `livewire`/`react`/`vue` are fetch-based (consume the JSON endpoints); `flux` is a server-side Volt+Flux component that calls the package's public API directly.

## Commands

- `composer test` — Pest suite (sqlite in-memory).
- `composer analyse` — Larastan.
- `composer format` — Pint.
- `php artisan passwordless:prune` — schedule hourly.

## Tests

Pest under `tests/`. Workbench user model at `workbench/app/Models/User.php`. The TestCase boots the package provider and runs every `database/migrations/*.php.stub` plus `tests/database/migrations/*.php` — add new package migrations as `*.php.stub` files only.

## Extension surface

- `Passwordless::gateUsing(closure)` — pre-auth allow/deny.
- `Passwordless::recordUsing(closure)` — single observability funnel.
- `Passwordless::fake()` — test helper.
- `LoginCodeChannel` contract for SMS/WhatsApp/etc.
- Per-strategy contract bindings — swap implementations via the container.
