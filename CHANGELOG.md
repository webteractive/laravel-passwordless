# Changelog

All notable changes to `laravel-passwordless` will be documented in this file.

## 0.1.0 - Unreleased

Initial release.

### Magic link
- `POST /auth/magic-link` and `GET /auth/magic-link/{token}` (signed, single-use, TTL-bound).
- Same-browser enforcement via signed cookie (toggleable).
- Resend cooldown, per-email/per-IP throttle, pre-auth gate, intended-URL capture.

### Login code (email OTP)
- `POST /auth/login-code` and `POST /auth/login-code/verify`.
- 6–10 digit numeric codes, leading zeros preserved, SHA-256 at rest.
- Per-strategy lockout (default 5 attempts / 15 min).
- Pluggable channel contract — `mail` driver included; SMS/WhatsApp/etc. as app-defined drivers.

### Plumbing
- One table — `passwordless_challenges` (ephemeral: magic link tokens + login codes).
- `Passwordless::gateUsing()`, `recordUsing()`, `fake()`.
- `php artisan passwordless:prune`.
- Events for every strategy lifecycle plus `AuthenticationDenied` and `UserAuthenticated`.
- Pest test suite, CI matrix across PHP 8.3/8.4 × Laravel 11/12/13, plus MySQL and Postgres on the latest combo.
