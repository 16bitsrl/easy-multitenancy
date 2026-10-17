## Easy Multitenancy

This app is multi-tenant with `16bit/easy-multitenancy`: one SQLite database per tenant, the tenant taken from the first URL segment (`/{tenant}/...`). Every web route is prefixed with `{tenant}` automatically, except central routes and the excluded patterns in `config/easy-multitenancy.php`.

- Read the tenant with `Bit16\EasyMultitenancy\Facades\Tenant::current()`. Never add a `$tenant` parameter to controllers or route closures, and never read `$request->route('tenant')`: the tenant is not passed to actions.
- Declare routes without the tenant segment (`Route::get('invoices/{invoice}', ...)`); generate URLs with `route()` / `url()` without passing the tenant.
- Landlord routes go inside `Tenant::centralRoutes(function () { ... })`. Models that always live in the central database use the `UsesCentralConnection` trait.
- Queued jobs carry the tenant automatically. Jobs that must run without a tenant implement `Bit16\EasyMultitenancy\Contracts\GlobalJob`.
- Files: the disks in `easy-multitenancy.storage.disks` are already tenant-scoped; do not add the tenant to paths yourself.
- Tenant migrations live in `database/migrations` and run with `php artisan tenant:migrate {tenant}` or `tenant:migrate-all`.
- Activate the `easy-multitenancy` skill for details, and `easy-multitenancy-upgrade` when upgrading from 0.x.
