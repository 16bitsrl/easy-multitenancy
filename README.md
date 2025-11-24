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

    'cache' => [
        'prefix_enabled' => env('TENANT_CACHE_PREFIX', true),
    ],

    'session' => [
        'prefix_enabled' => env('TENANT_SESSION_PREFIX', true),
    ],

    'storage' => [
        'prefix_enabled' => env('TENANT_STORAGE_PREFIX', true),
        'path' => env('TENANT_STORAGE_PATH', 'tenants'),
    ],

    'queue' => [
        'tenant_aware' => env('TENANT_QUEUE_AWARE', true),
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
- **Queue job tenant awareness** with the `TenantAware` trait
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

### Tenant-Aware Queue Jobs

Add the `TenantAware` trait to your jobs to ensure they run in the correct tenant context:

```php
use Bit16\EasyMultitenancy\Traits\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public function handle()
    {
        // Job automatically runs in the correct tenant context
    }
}
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
