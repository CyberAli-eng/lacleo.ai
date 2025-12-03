# STEP 1 — Security & Rate Limiting

## Detection Summary

- Middleware aliases registered in `bootstrap/app.php`:
  - `limit.request.size`, `request.timeout`, `csrf.guard`, `ensureCreditsAvailable`, `ensureCreditsForExport`, `ensureRevealFieldAvailable`, `admin`, `ensureWorkspace`.
- RateLimiter definitions in `app/Providers/RouteServiceProvider.php`:
  - `api`, `ai`, `search`, `reveal`, `export`, plus `App\Models\User::export` shim for tests.
- Route middleware applied in `routes/api.php`:
  - `throttle:ai`, `throttle:search`, `throttle:reveal`, `throttle:export`, with `limit.request.size`, `request.timeout`, `csrf.guard`; Stripe webhook exempt from CSRF.
- CORS hardened in `config/cors.php`:
  - `allowed_origins` and patterns include localhost and `*.lacleo.test`, `supports_credentials=true`, headers include `X-XSRF-TOKEN`.

## Changes

- No code changes required; all required middleware and rate limiters already present and applied.

## Lint/Tests

- Ran: `composer dump-autoload -q && ./vendor/bin/pint --test && ./vendor/bin/pest -q`
- Result: PASS (`pint` clean, `pest` all tests passing).

## Routes Verification

- Ran: `php artisan route:list` (summary captured), endpoints listed for AI, Search, Reveal, Export.

## Smoke (note)

- Local CLI PHP built-in server returned no response to curl (`000`) in this environment; skipping live HTTP smoke. The middleware wiring is verified by static inspection and test suite.

