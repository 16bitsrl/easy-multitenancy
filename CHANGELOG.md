# Changelog

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
