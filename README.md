# Easy Multitenancy for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/16bit/easy-multitenancy.svg?style=flat-square)](https://packagist.org/packages/16bit/easy-multitenancy)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/16bit/easy-multitenancy/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/16bit/easy-multitenancy/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/16bit/easy-multitenancy/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/16bit/easy-multitenancy/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/16bit/easy-multitenancy.svg?style=flat-square)](https://packagist.org/packages/16bit/easy-multitenancy)

A simple, drop-in Laravel package for database-per-tenant multitenancy using SQLite. Perfect for SaaS applications where each tenant gets their own isolated SQLite database with automatic URL-based tenant identification and seamless database switching.

## Installation

You can install the package via composer:

```bash
composer require 16bit/easy-multitenancy
```

Publish the config file:

```bash
php artisan vendor:publish --tag="easy-multitenancy-config"
```

This is the contents of the published config file:

```php
return [
    'database' => [
        'path' => env('TENANT_DB_PATH', database_path('tenants')),
        'connection' => env('TENANT_DB_CONNECTION', 'tenant'),
        'extension' => '.sqlite',
    ],

    // Optional central (landlord) connection, reachable even while a tenant is active.
    'central' => [
        'enabled' => env('TENANT_CENTRAL_ENABLED', false),
        'connection' => env('TENANT_CENTRAL_CONNECTION', 'central'),
    ],

    'cache' => [
        'prefix_enabled' => env('TENANT_CACHE_PREFIX', true),
    ],

    'session' => [
        'suffix_enabled' => env('TENANT_SESSION_SUFFIX', true),
        'use_tenant_db' => env('TENANT_SESSION_USE_DB', false),
    ],

    'storage' => [
        'suffix_enabled' => env('TENANT_STORAGE_SUFFIX', true),
        'path' => env('TENANT_STORAGE_PATH', 'tenants'),
    ],

    'track_recent_tenants' => env('TENANT_TRACK_RECENT', false),

    'recent_tenants' => [
        'cookie' => env('TENANT_RECENT_COOKIE', 'em_recent_tenants'),
        'max' => (int) env('TENANT_RECENT_MAX', 5),
        'lifetime' => (int) env('TENANT_RECENT_LIFETIME', 43200),
    ],

    'queue' => [
        'tenant_aware' => env('TENANT_QUEUE_AWARE', true),
        'strict_mode' => env('TENANT_QUEUE_STRICT', true),
        'debug_logging' => env('TENANT_QUEUE_DEBUG', false),
        'excluded_jobs' => [],
        'excluded_patterns' => [],
        'exclusion_interface' => \Bit16\EasyMultitenancy\Contracts\GlobalJob::class,
    ],

    'seeders' => [
        // Seeders to run when creating a new tenant
        'on_create' => [
            //  \Database\Seeders\DatabaseSeeder::class
        ],
    ],

    'routes' => [
        'parameter' => 'tenant',
        'middleware' => ['web'],
        'auto_prefix' => env('TENANT_AUTO_PREFIX_ROUTES', true),
        'excluded_routes' => [
            'home',
        ],
        'excluded_patterns' => [
            'up',
            'horizon*',
            'telescope*',
            'api/*',
            '_debugbar/*',
            '*.js',
            '*.css',
            '*.map',
        ],
    ],
];
```

## Features

- **Database-per-tenant architecture** using SQLite
- **Automatic route prefixing** with tenant identification
- **Seamless database switching** based on URL
- **Tenant-isolated storage, cache, and sessions**
- **Automatic tenant injection** for queued jobs
- **Artisan commands** for tenant management
- **Events** for tenant lifecycle hooks
- **Custom URL generator** for tenant-aware routing

## Usage

### Creating a Tenant

```bash
# Interactive creation with prompts
php artisan tenant:create

# Create with specific name
php artisan tenant:create acme

# Create without user
php artisan tenant:create acme --no-user
```

### Listing Tenants

```bash
php artisan tenant:list
```

### Running Migrations

```bash
# Migrate specific tenant
php artisan tenant:migrate acme

# Migrate with fresh (drop all tables)
php artisan tenant:migrate acme --fresh

# Migrate and seed
php artisan tenant:migrate acme --seed

# Migrate all tenants
php artisan tenant:migrate-all
```

### Seeding Databases

```bash
# Seed specific tenant
php artisan tenant:seed acme

# Seed with specific seeder class
php artisan tenant:seed acme --class=DatabaseSeeder

# Seed all tenants
php artisan tenant:seed-all
```

### Accessing Tenants in Code

The package automatically identifies tenants from the URL and switches the database context. All routes are automatically prefixed with `{tenant}` parameter.

```php
use Bit16\EasyMultitenancy\Facades\Tenant;

// Get current tenant
$currentTenant = Tenant::current(); // Returns tenant identifier (e.g., 'acme')

// Get current tenant ID (alias for current())
$tenantId = Tenant::id();

// Get current database path
$database = Tenant::database();

// Check if tenant exists
if (Tenant::exists('acme')) {
    // Tenant exists
}

// Get all tenants
$tenants = Tenant::all();

// Manually switch tenant (rarely needed)
Tenant::identify('acme');

// Forget current tenant context
Tenant::forget();
```

### Events

The package dispatches several events you can listen to:

```php
use Bit16\EasyMultitenancy\Events\TenantIdentified;
use Bit16\EasyMultitenancy\Events\TenantNotFound;
use Bit16\EasyMultitenancy\Events\DatabaseSwitched;

// Listen to tenant identified event
Event::listen(TenantIdentified::class, function ($event) {
    // $event->tenant
    // $event->database
});

// Listen to database switched event
Event::listen(DatabaseSwitched::class, function ($event) {
    // $event->tenant
    // $event->database
    // $event->connection
});

// Listen to tenant not found event
Event::listen(TenantNotFound::class, function ($event) {
    // $event->tenant
});
```

### Central (Landlord) Connection

Some data is shared across all tenants (e.g. the list of tenants, global users, billing).
Enable the optional central connection to keep the landlord database reachable even while a
tenant connection is active:

```php
// config/easy-multitenancy.php
'central' => [
    'enabled' => env('TENANT_CENTRAL_ENABLED', true),
    'connection' => env('TENANT_CENTRAL_CONNECTION', 'central'),
],
```

When enabled, a `central` connection is registered pointing at your application's default
connection as configured at boot. Add the `UsesCentralConnection` trait to any model that must
always query the central database, regardless of the current tenant:

```php
use Bit16\EasyMultitenancy\Traits\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use UsesCentralConnection;
}
```

> **Storage note:** when storage isolation is enabled the package routes the **default** filesystem
> disk to a per-tenant directory (and registers a `tenant` disk). Calls that target the default
> disk are tenant-scoped; explicit `Storage::disk('local')` calls are not.

### Central Routes

Declare landlord routes that must never be tenant-prefixed (marketing pages, tenant sign-up,
the landlord dashboard) with `Tenant::centralRoutes()`:

```php
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\Route;

Tenant::centralRoutes(function () {
    Route::get('/', [LandingController::class, 'index']);
    Route::get('/pricing', [PricingController::class, 'index']);
});
```

These routes keep their original URI (no `{tenant}/` prefix), run on the default/central
connection, and are skipped by tenant identification.

### Recently Visited Tenants

Enable `track_recent_tenants` to keep a per-browser list of recently visited tenants in a shared
cookie. Read it (typically from a central route) with `Tenant::getRecentTenants()`:

```php
// config/easy-multitenancy.php
'track_recent_tenants' => env('TENANT_TRACK_RECENT', true),

// Returns ['acme' => 1717000000, 'contoso' => 1716990000] (newest first)
$recent = Tenant::getRecentTenants();
```

### Queued Jobs

When `queue.tenant_aware` is enabled (default), the current tenant is automatically injected into
every queued job at dispatch and restored before the job runs — no trait required. Opt a job out of
tenant context by implementing the `GlobalJob` interface, listing it under `queue.excluded_jobs` /
`queue.excluded_patterns`, or setting a public `$tenantAware = false` property:

```php
use Bit16\EasyMultitenancy\Contracts\GlobalJob;

class BackupAllTenants implements ShouldQueue, GlobalJob
{
    // Runs in the central context, without a tenant.
}
```

### Route Configuration

By default, all routes are automatically prefixed with the tenant parameter. You can exclude specific routes:

```php
// In config/easy-multitenancy.php
'routes' => [
    'parameter' => 'tenant',
    'middleware' => ['web'],
    'auto_prefix' => env('TENANT_AUTO_PREFIX_ROUTES', true),
    'excluded_routes' => [
        'home',
    ],
    'excluded_patterns' => [
        'up',
        'horizon*',
        'telescope*',
        'api/*',
        '_debugbar/*',
        '*.js',
        '*.css',
        '*.map',
    ],
],
```

### Generating URLs

The package includes a custom URL generator that automatically includes the tenant parameter:

```php
// Generate URL to a route
url('/dashboard'); // Automatically becomes /{tenant}/dashboard

// Named routes
route('dashboard'); // Automatically includes tenant parameter

// Generate URL for a specific tenant
route('dashboard', ['tenant' => 'acme']);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

If you discover a security vulnerability, please email Mattia Trapani at mt@16bit.it.

## Credits

- [Mattia Trapani](https://github.com/zupolgec)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
