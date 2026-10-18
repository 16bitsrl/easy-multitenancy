# AGENTS.md

Guidance for contributors and coding agents working on `16bit/easy-multitenancy`, a Laravel package
for database-per-tenant multitenancy (one SQLite file per tenant, tenant from the first URL segment).

## Commands

```bash
composer test      # Pest
composer analyse   # PHPStan (Larastan), level 8
composer format    # Pint
```

Run all three before committing. Redis tests are skipped unless a dedicated server is given:
`EASY_MULTITENANCY_TEST_REDIS=127.0.0.1:6379 composer test`. Never point it at a Redis holding real data.

## Layout

- `src/Managers/TenantManager.php`: identify/forget a tenant, switch database, cache, session, disks.
- `src/EasyMultitenancyServiceProvider.php`: route prefixing, central routes, middleware priority,
  queue hooks, Octane reset listeners.
- `src/Middleware/IdentifyTenant.php`: per-request tenant context; runs before the session starts.
- `src/TenantUrlGenerator.php`: tenant-aware `route()` / `url()`.
- `src/Queue/QueueTenantInjector.php`: tenant in job payloads, context around each job.
- `src/Commands/`: `tenant:*` Artisan commands, including `tenant:upgrade-check`.
- `src/Testing/InteractsWithTenants.php`: test helpers shipped to apps (`actingAsTenant`, `createTenant`).
- `resources/lang/`: translations (English is the base language, Italian included).
- `resources/boost/`: Laravel Boost guideline and skills shipped to apps. Keep them in sync with
  behaviour changes.

## Rules

- Tests first: reproduce a bug or describe a feature with a failing test, then implement. Prefer HTTP
  tests through real routes and real queue workers over unit tests of internals.
- Tests that need configuration before boot get their own `TestCase` and folder (see `tests/Pest.php`).
- Every user-facing string is translatable, written in English first.
- The tenant parameter name comes from `easy-multitenancy.routes.parameter`; never hardcode `tenant`.
- Anything bound to a tenant (connections, session stores, cache prefixes, disks, URL defaults) must be
  reset by `Tenant::forget()` or at the end of the request; long-running processes (Octane, queue
  workers) reuse the application.
- Breaking changes go in `CHANGELOG.md` and `UPGRADE.md`, and `tenant:upgrade-check` should detect them
  when it can.
- Commit messages: one short headline line.

## Local notes

`docs/` is git-ignored and holds local working notes (reviews, decisions). Do not commit it.
