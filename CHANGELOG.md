# Changelog

All notable changes to `laravel-passwordless` will be documented in this file.

## Unreleased

### Added

- **Starter-kit two-factor authentication.** When a user has Laravel Fortify 2FA enabled, every flow (magic link, login code, magicCode, social, and the published embed controllers) now hands off to Fortify's own challenge instead of logging them in — writing Fortify's `login.id` / `login.remember` session contract, dispatching `TwoFactorAuthenticationChallenged`, and redirecting to `two-factor.login` (or returning `{"two_factor": true}` for JSON). Fortify remains **optional**: it is a dev-only dependency, all detection is duck-typed, and apps without it are byte-for-byte unaffected. New `Passwordless::twoFactor()` API.
- **2FA enrollment for password-less accounts.** An emailed identity-confirmation code now satisfies Laravel's `password.confirm` middleware via `Fortify::confirmPasswordsUsing()`, so users with no password hash can enable 2FA from a starter kit's settings page. New `Passwordless::confirmation()` API, `POST /auth/confirm/send` endpoint, and a `confirm` challenge type (reuses `passwordless_challenges` — no schema change; `passwordless:prune` covers it). Its resend cooldown and lockout use a key namespace separate from login, so neither can lock a user out of the other.
- **Remember me** across every flow. The flag is chosen at send time and persisted in `Challenge.metadata`, so it survives the magic-link round trip; social carries it through the session. A `remember` key on a verify request overrides the stored value. Configurable via `remember.enabled`; ignored in `api_mode`.
- **Dev login (user selection).** An opt-in, local-only picker that signs in a chosen user — `GET`/`POST /auth/dev-login`. Guarded by three independent conditions (strictly-`true` config flag, an `APP_ENV` allow-list, and a permanent production denylist); when any fails the routes are **not registered at all** and `404`. Fires `UserAuthenticated('dev_login', …)` so audit hooks see it distinctly.
- Remember-me checkboxes and dev pickers in all six UI stubs, plus `confirm-identity` pages and a `PasswordlessFortifyServiceProvider` for the three `-embed` variants.

### Changed

- All five auth-completing controllers now funnel through a new `Support\AuthCompletion` seam, removing five copies of the `api_mode` token branch. Behaviour-preserving — no explicit session regeneration was added, since `SessionGuard::login()` already rotates the session id.

### Security

- **`api_mode` no longer issues a token to a user with 2FA enabled.** It returns `409 {"two_factor": true}` instead. Fortify's challenge is session-based and cannot be completed over a token flow, so issuing one would have bypassed the user's second factor.
- Two deliberate fail-closed errors rather than silent downgrades: `TwoFactorGuardMismatchException` when `passwordless.guard` and `fortify.guard` disagree, and `TwoFactorChallengeUnavailableException` when a user requires a challenge Fortify cannot deliver.
- `dev_login.enabled` ships as a literal `false` with **no `env()` default**, so no stray environment variable can enable the user picker; turning it on requires a deliberate edit to the published config.

## 0.1.3 - 2026-07-23

### Added

- **magicCode** — a new combined strategy that sends **one** email containing both a magic link and a numeric login code. The user authenticates with either; the first path used wins and the sibling is invalidated. `Passwordless::magicCode()`, endpoints `POST /auth/magic-code`, `GET /auth/magic-code/{token}`, `POST /auth/magic-code/verify`. Reuses the `passwordless_challenges` table (two correlated `mc_link`/`mc_code` rows sharing a `magic_code_id`) — no schema change. Same-browser enforcement applies to the link path only; the code path is device-agnostic (request on desktop, type the code on a phone). Opt-in (`strategies.magic_code.enabled`, off by default; the routes 404 while disabled) and email-only. Full lifecycle events (`MagicCodeRequested/Consumed/Verified/Failed`) and `Passwordless::fake()` support (`assertMagicCodeSent()`).

## 0.1.2 - 2026-07-23

### Added

- **Customizable post-auth redirect** — `Passwordless::redirectUsing(fn ($user, $request) => ...)` sets where server-driven logins (social callback, published embed controllers) land. The returned URL is used as the fallback for `redirect()->intended()`, so a middleware-set intended URL still wins; when no closure is set it falls back to `config('passwordless.redirect')`. Headless magic-link/login-code endpoints (JSON/`204`) are unaffected.

## 0.1.1 - 2026-07-23

### Added

- **Social login** via Laravel Socialite — `GET /auth/social/{provider}/{redirect,callback}` routes; resolves identity by known `(provider, provider_id)` → verified-email link → auto-registration; OAuth tokens stored **encrypted** in a new `passwordless_social_accounts` table. Linking/registering requires a verified email (provider `email_verified` claim or a `social.trusted_providers` allow-list) to prevent account takeover. Enable providers in `config('passwordless.social.providers')`; credentials stay in `config/services.php`. Adds `Passwordless::social()`, `Passwordless::resolveSocialUserUsing()`, and the `SocialAuthenticated` event.
- **Domain limiting** — `domains.allowed` allow-list with independent enforcement per type (`passwordless` / `social`) and action (`login` / `register`). Empty by default (no-op); applies to magic link, login code, and social.

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
