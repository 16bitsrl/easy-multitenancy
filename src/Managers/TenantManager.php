<?php

namespace Bit16\EasyMultitenancy\Managers;

use Bit16\EasyMultitenancy\Events\DatabaseSwitched;
use Bit16\EasyMultitenancy\Events\TenantIdentified;
use Bit16\EasyMultitenancy\Events\TenantNotFound;
use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TenantManager
{
    protected ?string $currentTenant = null;

    protected ?string $currentDatabase = null;

    /**
     * Pristine configuration captured before the first tenant switch, so that
     * forget() can fully restore the framework state. This prevents prefixes
     * from accumulating and state from leaking across tenants in long-running
     * workers (queue/Octane).
     *
     * @var array<string, mixed>
     */
    protected array $snapshot = [];

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
        $this->captureSnapshot();

        $connection = config('easy-multitenancy.database.connection', 'tenant');

        $this->configureConnection($connection, $database);

        DB::purge($connection);
        DB::reconnect($connection);
        DB::setDefaultConnection($connection);

        // Database sessions: keep them in the tenant DB, or on the central
        // (landlord) connection so the landlord retains a shared session.
        if (config('easy-multitenancy.session.use_tenant_db', false)) {
            Config::set('session.driver', 'database');
            Config::set('session.connection', $connection);
        } elseif (config('session.driver') === 'database') {
            $central = $this->centralConnection();

            if (config("database.connections.{$central}") !== null) {
                Config::set('session.connection', $central);
            }
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

        if (config('easy-multitenancy.session.suffix_enabled', true)) {
            $this->setSessionSuffix($tenant);
        }

        if (config('easy-multitenancy.storage.suffix_enabled', true)) {
            $this->setStorageSuffix($tenant);
        }

        event(new DatabaseSwitched($tenant, $database, $connection));
    }

    /**
     * Snapshot the pristine framework state once, before any mutation.
     */
    protected function captureSnapshot(): void
    {
        if (! empty($this->snapshot)) {
            return;
        }

        $this->snapshot = [
            'database.default' => config('database.default'),
            'cache.prefix' => config('cache.prefix'),
            'session.cookie' => config('session.cookie'),
            'session.driver' => config('session.driver'),
            'session.connection' => config('session.connection'),
            'cache.stores.database.connection' => config('cache.stores.database.connection'),
            'queue.connections.database.connection' => config('queue.connections.database.connection'),
            'filesystems.default' => config('filesystems.default'),
        ];
    }

    /**
     * Build the tenant connection config, merging any user-defined connection
     * and forcing the tenant database path. Sensible SQLite defaults (WAL +
     * busy timeout) improve concurrency for the database-per-tenant model.
     */
    protected function configureConnection(string $connection, string $database): void
    {
        $existing = config("database.connections.{$connection}");
        $existing = is_array($existing) ? $existing : [];

        Config::set("database.connections.{$connection}", array_merge([
            'driver' => 'sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
        ], $existing, [
            'driver' => 'sqlite',
            'database' => $database,
        ]));
    }

    protected function setCachePrefix(string $tenant): void
    {
        $base = $this->snapshot['cache.prefix'] ?? config('cache.prefix');
        $prefix = $base.$tenant.'_';

        Config::set('cache.prefix', $prefix);

        $store = Cache::getStore();
        if (method_exists($store, 'setPrefix')) {
            $store->setPrefix($prefix);
        } else {
            Log::warning('Cache store does not support setPrefix, tenant cache isolation may not work', [
                'store' => get_class($store),
                'tenant' => $tenant,
            ]);
        }
    }

    protected function setSessionSuffix(string $tenant): void
    {
        $base = $this->snapshot['session.cookie'] ?? config('session.cookie');

        Config::set('session.cookie', $base.'_'.$tenant);
    }

    protected function setStorageSuffix(string $tenant): void
    {
        $path = config('easy-multitenancy.storage.path', 'tenants').'/'.$tenant;

        Config::set('filesystems.disks.tenant', [
            'driver' => 'local',
            'root' => storage_path('app/'.$path),
            'throw' => false,
        ]);

        Config::set('filesystems.default', 'tenant');

        Storage::forgetDisk('tenant');
    }

    public function current(): ?string
    {
        return $this->currentTenant;
    }

    /**
     * The connection name for the central (landlord) database.
     */
    public function centralConnection(): string
    {
        return config('easy-multitenancy.central.connection', 'central');
    }

    /**
     * Register routes that must NOT be tenant-prefixed (landlord/central routes).
     * Routes declared inside the callback are marked with a temporary prefix that
     * is stripped during auto-prefixing, leaving them on the default connection.
     */
    public function centralRoutes(\Closure $callback): void
    {
        app('router')->prefix('__central-route__')->group($callback);
    }

    /**
     * Recently visited tenants for the current browser (newest first),
     * read from the shared cookie maintained by the TrackRecentTenant
     * middleware. Returns a map of tenant => last-visit unix timestamp.
     *
     * @return array<string, int>
     */
    public function getRecentTenants(): array
    {
        $cookie = config('easy-multitenancy.recent_tenants.cookie', 'em_recent_tenants');

        $value = request()->cookie($cookie);

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        arsort($decoded);

        return $decoded;
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

    /**
     * Normalize a user-supplied tenant name into a valid identifier
     * (lowercase letters, numbers and hyphens only).
     */
    public function sanitize(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9\-]/', '', $name);
        $name = preg_replace('/\.\.+/', '', $name);
        $name = str_replace(['/', '\\', "\0"], '', $name);

        if ($name === '') {
            throw new \InvalidArgumentException('Tenant name cannot be empty after sanitization');
        }

        return $name;
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
        if (! empty($this->snapshot)) {
            $connection = config('easy-multitenancy.database.connection', 'tenant');

            foreach ($this->snapshot as $key => $value) {
                Config::set($key, $value);
            }

            DB::setDefaultConnection($this->snapshot['database.default']);
            DB::purge($connection);

            // Reset the active cache store prefix back to the pristine base.
            $store = Cache::getStore();
            if (method_exists($store, 'setPrefix')) {
                $store->setPrefix((string) ($this->snapshot['cache.prefix'] ?? ''));
            }
            Cache::forgetDriver('database');
            Storage::forgetDisk('tenant');

            $this->snapshot = [];
        }

        $this->currentTenant = null;
        $this->currentDatabase = null;
    }
}
