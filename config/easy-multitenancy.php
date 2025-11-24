<?php

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
            '*.js',
            '*.css',
            '*.map',
        ],
    ],

];
