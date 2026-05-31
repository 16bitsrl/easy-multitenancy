<?php

return [

    'database' => [
        'path' => env('TENANT_DB_PATH', database_path('tenants')),
        'connection' => env('TENANT_DB_CONNECTION', 'tenant'),
        'extension' => '.sqlite',
    ],

    /*
     * Optional "central" (landlord) connection. When enabled, a stable
     * connection name is registered pointing at the application's default
     * connection as it was at boot time. Models using the
     * `UsesCentralConnection` trait (or `$connection = 'central'`) always
     * query the central database, even while a tenant is active.
     */
    'central' => [
        'enabled' => env('TENANT_CENTRAL_ENABLED', false),
        'connection' => env('TENANT_CENTRAL_CONNECTION', 'central'),

        // Serve a default central home (tenant picker) at "/" when the app
        // has not defined its own root route. Publish the view to customize it.
        'default_home' => env('TENANT_CENTRAL_DEFAULT_HOME', true),
    ],

    'cache' => [
        'prefix_enabled' => env('TENANT_CACHE_PREFIX', true),
    ],

    'session' => [
        'suffix_enabled' => env('TENANT_SESSION_SUFFIX', true),

        // Store database sessions in the tenant database instead of the central one.
        'use_tenant_db' => env('TENANT_SESSION_USE_DB', false),
    ],

    'storage' => [
        'suffix_enabled' => env('TENANT_STORAGE_SUFFIX', true),
        'path' => env('TENANT_STORAGE_PATH', 'tenants'),
    ],

    // Track recently visited tenants in a shared cookie (readable via Tenant::getRecentTenants()).
    'track_recent_tenants' => env('TENANT_TRACK_RECENT', false),

    'recent_tenants' => [
        'cookie' => env('TENANT_RECENT_COOKIE', 'em_recent_tenants'),
        'max' => (int) env('TENANT_RECENT_MAX', 5),
        'lifetime' => (int) env('TENANT_RECENT_LIFETIME', 43200), // minutes (~30 days)
    ],

    'queue' => [
        // Automatically inject the current tenant into queued jobs and restore it on processing.
        'tenant_aware' => env('TENANT_QUEUE_AWARE', true),

        // Strict mode: fail jobs whose tenant no longer exists at processing time.
        'strict_mode' => env('TENANT_QUEUE_STRICT', true),

        // Verbose logging for queue tenant injection.
        'debug_logging' => env('TENANT_QUEUE_DEBUG', false),

        // Jobs that must NOT receive tenant context (exact class names).
        'excluded_jobs' => [
            // \App\Jobs\BackupAllTenants::class,
        ],

        // Pattern-based exclusions (Str::is wildcards against the job class).
        'excluded_patterns' => [
            // 'App\\Jobs\\System\\*',
        ],

        // Jobs implementing this interface are excluded from tenant injection.
        'exclusion_interface' => \Bit16\EasyMultitenancy\Contracts\GlobalJob::class,
    ],

    'seeders' => [
        'on_create' => [
            // eventual Seeder to run after new tenant database creation
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
            '_boost/*',
            // Livewire's testing harness registers a temporary route here for
            // the initial render; prefixing it would break component tests.
            'livewire-unit-test-endpoint/*',
            '*.js',
            '*.css',
            '*.map',
        ],
    ],

];
