# Changelog

## v1.0.1 - 2026-10-19

### Fixed
- The recent tenants list (central home, `Tenant::getRecentTenants()`) skips deleted tenants and malformed cookie entries instead of linking to them or failing.
- `tenant:create` skips the default user when `auth.providers.users.model` is not an Eloquent model, instead of stopping with an error.
- Static analysis raised to PHPStan level 8.

## v1.0.0 - 2026-10-18

See [UPGRADE.md](UPGRADE.md) and run `php artisan tenant:upgrade-check` to find what your app needs to change.

### Breaking changes
- Requires Laravel 13 and PHP 8.4 or later.
- The tenant parameter is no longer passed to controllers and route closures: `{tenant}/posts/{post}` calls `function (Post $post)`. Read the tenant with `Tenant::current()`; `$request->route('tenant')` now returns `null`.
- Tenant identifiers must be 2-63 characters of lowercase letters, numbers and single hyphens, not starting or ending with a hyphen. Databases with other names are no longer listed or reachable.
- Storage isolation scopes the disks listed in `storage.disks` (default `local` and `public`) instead of switching the default disk to a `tenant` disk. Tenant files move from `storage/app/tenants/{tenant}` to a `tenants/{tenant}` folder inside each disk. Remote disks such as S3 can be listed too and get a `tenants/{tenant}` object prefix.
- `QueueTenantInjector::shouldRestoreTenant()`, `restoreTenant()` and `cleanupTenant()` are replaced by `beginJob()` / `endJob()`.
- The central home is in English by default and translatable (English and Italian included).
- `Tenant::sanitize()` turns spaces and underscores into hyphens and throws when the result is not a valid identifier.

### Added
- `php artisan tenant:upgrade-check` lists the routes, code, tenant databases and files to update for v1.
- `Tenant::isReserved()` and `Tenant::flushSessionState()`.
- `storage.disks` option.
- Translations for the central home (`lang/vendor/easy-multitenancy` to override).

### Fixed
- Sessions are now isolated per tenant: the tenant is identified before the session starts, so the tenant cookie name (and `session.use_tenant_db`) actually apply. Previously a session, including the logged-in user id, was shared across tenants.
- Queued jobs now carry the tenant: injection reads the job object Laravel passes to payload hooks (it never matched before, so every job ran in the central context).
- A worker no longer leaks a tenant into the next job after a released attempt, and sync jobs no longer change the tenant of the request that dispatched them.
- `queue.strict_mode` now fails jobs whose tenant no longer exists instead of running them in the central context.
- Queues on the `database` driver stay on the central connection instead of following the default connection into the tenant database.
- Route model bindings resolve against the tenant database.
- Grouped routes with a prefix keep their tenant segment when loaded from the route cache.
- An app root route (prefixed to `{tenant}`) no longer shadows central routes registered after it, such as `/pricing`.
- `route()`/`url()` no longer add the tenant to central routes; `url()` leaves `mailto:`, `tel:` and `#fragment` links alone and checks the tenant segment exactly.
- The tenant URL default is cleared after each request and on Octane lifecycle events.
- `routes.parameter` is honoured everywhere (it was hardcoded to `tenant` in several places).
- `Tenant::centralRoutes()` works with `routes.auto_prefix` disabled.
- `file` and `array` cache stores are isolated per tenant.
- A `url` (or `read`/`write`) on the tenant connection no longer overrides the tenant database path.
- `Tenant::forget()` restores the tenant connection configuration; a failed `Tenant::identify()` no longer leaves a partial tenant context.
- `tenant:create` creates the database exclusively, rejects names reserved by the application (excluded paths, central routes), no longer deletes the new tenant when the app has no `login` route, hides the admin password while typing, and creates the admin with the app's configured user model.
- The not-found response for an unknown tenant no longer echoes the requested name.
- Under Octane, sessions and logins no longer carry over from a tenant request to the next request in the same worker, and the session uses the tenant cookie name.


## v0.3.1 - 2026-05-31

### Fixed
- Fixed PHPStan on fresh installs by avoiding Larastan's strict `view-string` helper check in the default central home controller.
- Removed duplicate tenant identification on auto-prefixed web routes and clear the tenant context after each HTTP request.
- Ensure tenant migrate/seed commands clean up tenant context and fail when underlying Artisan commands fail.
- Avoid unserializing queued jobs when payload class metadata is enough to apply configured class or pattern exclusions.
- Reject invalid `tenant:create` identifiers instead of silently stripping characters.

### Changed
- Run tests and PHPStan on pull requests, and update GitHub metadata links to the current repository.

## v0.3.0 - 2026-05-31

### Added
- Default central home: a tenant-picker landing page served at `/` when the host app has no root route of its own. Opt out via `central.default_home`, or publish the view (`easy-multitenancy-views`) to customize it.
- The picker validates the submitted identifier (POST) and shows a "Tenant non trovato" message, redirecting to the tenant on success (case-insensitive); the app's own central `/` always takes precedence.
- `Tenant::isValid()` to check whether a name is a well-formed tenant identifier.

## v0.2.0 - 2026-05-30

### Added
- Laravel 13 support (now requires Laravel 12 or 13, PHP 8.3+)
- Optional `central` (landlord) connection, reachable while a tenant is active, plus the `UsesCentralConnection` trait
- `Tenant::centralRoutes()` to register landlord routes that are never tenant-prefixed
- Automatic tenant injection for queued jobs (`QueueTenantInjector`), with opt-out via the `GlobalJob` interface, class/pattern exclusions or a `tenantAware = false` property
- Recent-tenants tracking in a shared cookie, readable via `Tenant::getRecentTenants()`
- `session.use_tenant_db` option to store database sessions in the tenant database
- Octane support: the tenant context is flushed on each request/task lifecycle event
- SQLite WAL + busy timeout defaults on the tenant connection, merged with any user-defined `tenant` connection
- Integration test suite covering isolation, identification, route prefixing, central connection/routes, queue injection and recent tenants

### Fixed
- `forget()` now snapshots and fully restores cache prefix, session cookie/driver, storage disk and the default connection — fixing state leaking across tenants in queue workers / Octane
- Cache prefix and session cookie no longer accumulate across consecutive tenant switches

### Changed
- Config: `session.prefix_enabled` / `storage.prefix_enabled` renamed to `suffix_enabled` (the value is appended, not prepended); `cache.prefix_enabled` is unchanged
- Storage isolation now routes the default filesystem disk to a per-tenant directory (and registers a `tenant` disk) instead of overriding the `local` disk

### Removed
- The `TenantAware` job trait — superseded by automatic queue tenant injection

## v0.1.1 - 2025-11-25

### Fixed

- Improved `SeedTenantCommand` to use `$this->call()` instead of `Artisan::call()` for better console integration

### Improved

- Update README with seeders configuration documentation

## v0.1.0 - 2024-11-24

### Added

- Initial release
- Multi-tenancy support based on URL routing
- SQLite database per tenant
- Automatic route prefixing with `{tenant}` parameter
- Tenant identification middleware
- Artisan commands:
  - `tenant:list` - List all tenants
  - `tenant:create` - Create a new tenant
  - `tenant:migrate` - Migrate a specific tenant
  - `tenant:migrate-all` - Migrate all tenants
  - `tenant:seed` - Seed a specific tenant
  - `tenant:seed-all` - Seed all tenants
  
- Tenant-aware session, cache, storage, and queue management
- Custom URL generator for tenant-aware route generation
- Configuration options for excluded routes and patterns
