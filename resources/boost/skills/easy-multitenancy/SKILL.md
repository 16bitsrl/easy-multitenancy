---
name: easy-multitenancy
description: >
  Build features in a Laravel app that uses 16bit/easy-multitenancy (one SQLite database per tenant,
  tenant from the first URL segment). Use when adding routes, controllers, Livewire components, models,
  migrations, queued jobs, file storage, cache or tests in such an app, when creating or migrating
  tenants, or when something reads or writes the wrong tenant's data.
compatibility: Requires 16bit/easy-multitenancy ^1.1 on Laravel 13.
---

# Easy Multitenancy

## How a request becomes tenant-scoped

- Every route is prefixed with `{tenant}` at boot (`dashboard` becomes `{tenant}/dashboard`), unless it is
  a central route or matches `routes.excluded_routes` / `routes.excluded_patterns`.
- The `tenant` middleware runs before the session starts. It switches the default database connection to
  the tenant's SQLite file, scopes cache keys, session cookie and the configured disks, then removes the
  tenant from the route parameters.
- Identifiers are 2-63 characters of lowercase letters, numbers and single hyphens (`acme`, `acme-corp`).

## Routes, controllers and URLs

```php
// routes/web.php: no tenant segment, no tenant argument
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

public function show(Invoice $invoice) // bound from the tenant database
{
    $tenant = Tenant::current(); // 'acme'
}

route('invoices.show', $invoice); // /acme/invoices/42 inside a tenant request
route('invoices.show', ['tenant' => 'contoso', 'invoice' => 42]); // another tenant
```

Do not add `$tenant` to action signatures and do not read `$request->route('tenant')`: both are wrong in
v1 (the action would receive the next parameter, the route value is `null`).

## Central (landlord) code

```php
Tenant::centralRoutes(function () {
    Route::middleware('web')->get('pricing', PricingController::class)->name('pricing');
});
```

- Central routes keep their URI and run without a tenant. `route('pricing')` is never tenant-prefixed.
- Inside a tenant, `url('/')` is the tenant home; link the central home with a named central route.
- Models stored in the central database: `use UsesCentralConnection;` (requires
  `easy-multitenancy.central.enabled`).

## Data, cache, files

- Tenant tables come from the app's normal migrations, run per tenant:
  `php artisan tenant:migrate acme`, `tenant:migrate-all`, `tenant:seed acme`, `tenant:seed-all`.
- `tenant:create acme` creates the database, migrates, runs `seeders.on_create` and an admin user.
- Cache keys are prefixed per tenant (Redis, database, Memcached), `file` caches use a tenant folder.
- Disks in `storage.disks` (default `local`, `public`) store under `tenants/{tenant}`; S3-like disks get an
  object prefix. Use `Storage::disk(...)` normally and never build tenant paths by hand.

## Queues

- Jobs dispatched inside a tenant carry it and run in that tenant; nothing to add to the job.
- Jobs for all tenants or the landlord: implement `GlobalJob`, list them in `queue.excluded_jobs` /
  `queue.excluded_patterns`, or set `public bool $tenantAware = false;`.
- To run an Artisan command in every tenant (also from the scheduler), use
  `php artisan tenant:run "reports:send --daily"` (`--tenant=acme` to pick tenants). It keeps going
  when one tenant fails and exits with a failure listing them.

## Running code for a tenant outside a request

```php
Tenant::identify('acme');
try {
    // default connection, cache, session settings and disks are acme's
} finally {
    Tenant::forget();
}
```

## Managing tenants

- `tenant:create`, `tenant:list`, `tenant:migrate(-all)`, `tenant:seed(-all)`.
- `tenant:delete acme` asks for confirmation and keeps a copy of the database in
  `database/tenants/backups`; `--files` also deletes the tenant folder on the scoped disks.
- `tenant:rename acme acme-corp` moves the database and files. URLs change and users log in again;
  let the queue drain first. Both commands are destructive: ask the user before running them.

## Tests

Use the package's trait in the app's `TestCase`:

```php
use Bit16\EasyMultitenancy\Testing\InteractsWithTenants;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithTenants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('acme');
    }
}
```

- `actingAsTenant('acme')` creates the tenant if needed (migrated, seeded with
  `seeders.on_create`), makes it current for the test body and sets the URL default, so `route()`
  includes `/acme`. Tenant databases live in a temporary folder and are removed after each test.
- Each request made by the test runs in the tenant of its URL (central URLs run without a tenant);
  the tenant the test acts as is current again afterwards.
- `createTenant('globex')` adds more tenants; assert isolation by writing in one and reading from another.
