# Changelog

## v0.1.1 - Add seeders configuration and improve commands output - 2025-11-24

### Fixed

- Improved `SeedTenantCommand` to use `$this->call()` instead of `Artisan::call()` for better console integration

### Improved

- Update README with seeders configuration documentation

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
