# Workbench

The workbench houses the `Workbench\App\Models\User` model used by the package's Pest test suite — see `tests/TestCase.php`. There is no runnable Testbench app here yet; the curl-driven smoke tests below describe the **target** experience for a future workbench scaffold.

## Smoke tests against a host app

The cleanest way to exercise the package end-to-end today is from a real Laravel app:

```bash
composer require webteractive/laravel-passwordless
php artisan vendor:publish --tag=passwordless-migrations
php artisan vendor:publish --tag=passwordless-config
php artisan migrate
```

Seed a user, then hit the package routes:

```bash
# Magic link
curl -X POST http://127.0.0.1:8000/auth/magic-link \
    -H 'Content-Type: application/json' \
    -d '{"email":"glen@example.com"}'

# Login code (read the code from MAIL_MAILER=log)
curl -X POST http://127.0.0.1:8000/auth/login-code \
    -H 'Content-Type: application/json' \
    -d '{"email":"glen@example.com"}'

# Login code verify
curl -X POST http://127.0.0.1:8000/auth/login-code/verify \
    -H 'Content-Type: application/json' \
    -d '{"email":"glen@example.com","code":"012345"}'
```
