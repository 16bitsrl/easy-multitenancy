<?php

namespace Bit16\EasyMultitenancy\Managers;

use Bit16\EasyMultitenancy\Events\DatabaseSwitched;
use Bit16\EasyMultitenancy\Events\TenantIdentified;
use Bit16\EasyMultitenancy\Events\TenantNotFound;
use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantManager
{
    protected ?string $currentTenant = null;

    protected ?string $currentDatabase = null;

    public function identify(string $tenant): void
    {
        if (! $this->exists($tenant)) {
            event(new TenantNotFound($tenant));

            throw new TenantNotFoundException($tenant);
        }

        $database = $this->getDatabasePath($tenant);

        $this->currentTenant = $tenant;
        $this->currentDatabase = $database;

        $this->switchDatabase($tenant, $database);

        event(new TenantIdentified($tenant, $database));
    }

    public function switchDatabase(string $tenant, string $database): void
    {
        $connection = config('easy-multitenancy.database.connection', 'tenant');

        Config::set("database.connections.{$connection}", [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::reconnect($connection);
        DB::setDefaultConnection($connection);

        // If session/cache/queue use database, reconnect them to use tenant connection
        if (config('session.driver') === 'database') {
            Config::set('session.connection', $connection);
        }

        if (config('cache.default') === 'database') {
            Config::set('cache.stores.database.connection', $connection);
            Cache::forgetDriver('database');
        }

        if (config('queue.default') === 'database') {
            Config::set('queue.connections.database.connection', $connection);
        }

        if (config('easy-multitenancy.cache.prefix_enabled', true)) {
            $this->setCachePrefix($tenant);
        }

        if (config('easy-multitenancy.session.prefix_enabled', true)) {
            $this->setSessionPrefix($tenant);
        }

        if (config('easy-multitenancy.storage.prefix_enabled', true)) {
            $this->setStoragePrefix($tenant);
        }

        event(new DatabaseSwitched($tenant, $database, $connection));
    }

    protected function setCachePrefix(string $tenant): void
    {
        $prefix = config('cache.prefix').$tenant.'_';
        Config::set('cache.prefix', $prefix);

        $store = Cache::getStore();
        if (method_exists($store, 'setPrefix')) {
            $store->setPrefix($prefix);
        } else {
            \Log::warning('Cache store does not support setPrefix, tenant cache isolation may not work', [
                'store' => get_class($store),
                'tenant' => $tenant,
            ]);
        }
    }

    protected function setSessionPrefix(string $tenant): void
    {
        Config::set('session.cookie', config('session.cookie').'_'.$tenant);
    }

    protected function setStoragePrefix(string $tenant): void
    {
        $path = config('easy-multitenancy.storage.path', 'tenants').'/'.$tenant;

        Config::set('filesystems.disks.tenant', [
            'driver' => 'local',
            'root' => storage_path('app/'.$path),
        ]);

        Config::set('filesystems.default', 'tenant');

        Storage::forgetDisk('local');
        Storage::extend('local', function ($app, $config) use ($path) {
            $config['root'] = storage_path('app/'.$path);

            return Storage::createLocalDriver($config);
        });
    }

    public function current(): ?string
    {
        return $this->currentTenant;
    }

    public function id(): ?string
    {
        return $this->currentTenant;
    }

    public function database(): ?string
    {
        return $this->currentDatabase;
    }

    public function exists(string $tenant): bool
    {
        $database = $this->getDatabasePath($tenant);

        return $database ? file_exists($database) : false;
    }

    public function getDatabasePath(string $tenant): ?string
    {
        try {
            $tenant = $this->sanitizeTenantName($tenant);
        } catch (TenantNotFoundException $e) {
            return null;
        }

        $path = config('easy-multitenancy.database.path', database_path('tenants'));
        $extension = config('easy-multitenancy.database.extension', '.sqlite');

        return $path.'/'.$tenant.$extension;
    }

    protected function sanitizeTenantName(string $tenant): string
    {
        $tenant = trim($tenant);

        if (empty($tenant)) {
            throw new TenantNotFoundException('Tenant name cannot be empty');
        }

        if (strlen($tenant) > 255) {
            throw new TenantNotFoundException('Tenant name is too long');
        }

        if (! preg_match('/^[a-z0-9\-]+$/', $tenant)) {
            throw new TenantNotFoundException('Invalid tenant name format. Only lowercase letters, numbers, and hyphens are allowed.');
        }

        return $tenant;
    }

    public function all(): array
    {
        $path = config('easy-multitenancy.database.path', database_path('tenants'));
        $extension = config('easy-multitenancy.database.extension', '.sqlite');

        if (! is_dir($path)) {
            return [];
        }

        $files = glob($path.'/*'.$extension);

        return array_map(function ($file) use ($extension) {
            return str_replace($extension, '', basename($file));
        }, $files);
    }

    public function forget(): void
    {
        $this->currentTenant = null;
        $this->currentDatabase = null;

        DB::setDefaultConnection(config('database.default'));
    }
}
