# Changelog

All notable changes to `laravel-passwordless` will be documented in this file.

## 0.1.0 - 2026-07-23

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

### Opt-in UI kit (publish-only)
- Nothing routed by default; the headless core is untouched. Publish a login page matched to your setup.
- **Standalone** stubs (self-contained page, own layout, `fetch` → JSON endpoints) for greenfield apps: `passwordless-ui-livewire` (Blade + Alpine), `passwordless-ui-react` and `passwordless-ui-vue` (Inertia + TypeScript).
- **Integrated** stubs that copy an official starter kit's auth layout/components and drive the flow server-side via a published Fortify-style controller: `passwordless-ui-livewire-embed` (Blade + Flux), `passwordless-ui-react-embed` and `passwordless-ui-vue-embed` (Inertia). Each browser-tested end-to-end against a real starter kit and coexists with the kit's own login.
- Two-step email → code with optional "email me a magic link", dark mode, config-gated affordances.

### Plumbing
- One table — `passwordless_challenges` (ephemeral: magic link tokens + login codes).
- `Passwordless::gateUsing()`, `recordUsing()`, `fake()`.
- `php artisan passwordless:prune`.
- Events for every strategy lifecycle plus `AuthenticationDenied` and `UserAuthenticated`.
- Pest test suite, CI matrix across PHP 8.3/8.4 × Laravel 11/12/13, plus MySQL and Postgres on the latest combo.
