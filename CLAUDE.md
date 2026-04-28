# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Status

Pre-code. The repo currently contains only `PRD.md` and this file. No `composer.json`, no source, no tests. Read `PRD.md` first — it is the source of truth for scope, schema, API surface, security model, and explicit non-goals.

## What this is

A Laravel package (target namespace TBD, likely `webteractive/laravel-passwordless`) providing three passwordless authentication strategies for Laravel 11 / 12 / 13 apps:

1. **Magic link** — signed, single-use, time-limited URL emailed to the user.
2. **Login code** — short numeric OTP emailed to the user. Channel is a contract; email is the only built-in driver.
3. **Passkeys (WebAuthn)** — experimental.

## Architectural ground rules (load-bearing)

These are PRD decisions made during planning. Do not relitigate without explicit user direction.

- **Backend only.** No frontend scaffolds, starter kits, Blade views, Livewire, or JS. Strictly headless. The package exposes routes, controllers, contracts, events, notifications, lang files — nothing else.
- **Two tables, not one and not four.**
  - `passwordless_challenges` — ephemeral rows for magic link tokens, login codes, and passkey ceremony challenges. Cleaned up after consumption or expiry.
  - `passwordless_credentials` — persistent passkey credentials.
  - Do not pollute the `users` table.
- **User must already exist by default.** Lookup is by email column on the configured user model. Unknown emails fail. Auto-creation is opt-in via `auto_create_users` config (default `false`).
- **Per-strategy enable/disable** via `config/passwordless.php`. Apps pick what they want.
- **Guard integration** uses Laravel's session guard out of the box. API mode (Sanctum token return) is a config flag; no Sanctum/Passport scaffolding is bundled.
- **Passkeys are experimental** until proven across all three supported Laravel versions.

## Hard non-goals (do not add without user direction)

- Recovery codes — antithetical to passwordless.
- Built-in SMS for login code — channel is a contract; apps add SMS by implementing it.
- Built-in audit log table — events cover this; apps that want persistence listen and write to their own table.
- Frontend anything.

## Security defaults that shape the design

When implementing, these are not optional toggles to skip — they are the package's reputation:

- **Email enumeration protection** — request endpoints return identical responses for known and unknown emails. Default on.
- **Same-browser enforcement for magic links** — challenge bound to a signed cookie set on request. Default on, configurable.
- **Resend cooldown** distinct from rate limit (default 30s).
- **Per-strategy lockout** after N failed verifies (default 5 / 15min).
- **Sign-count regression** on passkey auth → fire `PasskeyCloneDetected`, force re-registration, reject auth.
- **Tokens/codes hashed at rest, single-use, TTL-bound.**

## Extension surface (don't reinvent)

The PRD already defines these hooks — wire features through them rather than adding new ones:

- `Passwordless::gateUsing(closure)` — pre-auth allow/deny gate.
- `Passwordless::recordUsing(closure)` — single observability funnel for custom audit writers.
- `Passwordless::fake()` — test helper.
- Login-code `channel` driver contract — for SMS / WhatsApp / etc.
- Strategy contracts — apps can swap implementations.

## Open decisions

Tracked at the bottom of `PRD.md`. Resolve with the user before scaffolding code that depends on them:

- Passkey library choice (`web-auth/webauthn-lib` vs `asbiin/laravel-webauthn` vs thin wrapper).

## When code exists

Update this section once `composer.json`, source, and tests land:

- Test command (Pest, in-memory SQLite per the PRD).
- Lint/format command (Pint expected).
- How to run a single test.
- Service provider entry point and how strategies/contracts/drivers are wired.
- `php artisan passwordless:prune` (challenge cleanup) and `passwordless:passkeys` (CLI passkey management) commands.
