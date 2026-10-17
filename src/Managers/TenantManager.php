<?php

namespace Bit16\EasyMultitenancy\Managers;

use Bit16\EasyMultitenancy\Events\DatabaseSwitched;
use Bit16\EasyMultitenancy\Events\TenantIdentified;
use Bit16\EasyMultitenancy\Events\TenantNotFound;
use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Original directory of a file cache store, while it is tenant-scoped.
     */
    protected ?string $cacheDirectory = null;

    public function identify(string $tenant): void
    {
        if (! $this->exists($tenant)) {
            event(new TenantNotFound($tenant));

            throw new TenantNotFoundException($tenant);
        }

        $database = $this->getDatabasePath($tenant);

        try {
            $this->currentTenant = $tenant;
            $this->currentDatabase = $database;

            $this->switchDatabase($tenant, $database);

            event(new TenantIdentified($tenant, $database));
        } catch (\Throwable $e) {
            // Never leave a half-applied tenant context behind.
            $this->forget();

            throw $e;
        }
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

        $this->pinDatabaseQueuesToCentral();

        if (config('easy-multitenancy.cache.prefix_enabled', true)) {
            $this->setCachePrefix($tenant);
        }

        if (config('easy-multitenancy.session.suffix_enabled', true)) {
            $this->setSessionSuffix($tenant);
        }

        if (config('easy-multitenancy.storage.suffix_enabled', true)) {
            $this->setStorageSuffix($tenant);
        }

        $this->refreshUnstartedSession();

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

        $connection = config('easy-multitenancy.database.connection', 'tenant');

        $this->snapshot = [
            'database.default' => config('database.default'),
            "database.connections.{$connection}" => config("database.connections.{$connection}"),
            'queue.connections' => config('queue.connections'),
            'cache.prefix' => config('cache.prefix'),
            'session.cookie' => config('session.cookie'),
            'session.driver' => config('session.driver'),
            'session.connection' => config('session.connection'),
            'cache.stores.database.connection' => config('cache.stores.database.connection'),
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

        // A connection URL (or read/write overrides) would replace the tenant
        // database path when the connection is parsed, pointing every tenant
        // at the same database.
        unset($existing['url'], $existing['read'], $existing['write']);

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

        if ($store instanceof FileStore) {
            $this->cacheDirectory ??= $store->getDirectory();
            $store->setDirectory($this->cacheDirectory.'/'.$tenant);
        } elseif ($store instanceof ArrayStore) {
            // In-memory and keyless: start every tenant context from a fresh store.
            Cache::forgetDriver(config('cache.default'));
        } elseif (method_exists($store, 'setPrefix')) {
            $store->setPrefix($prefix);
        } else {
            Log::warning('Cache store does not support setPrefix, tenant cache isolation may not work', [
                'store' => get_class($store),
                'tenant' => $tenant,
            ]);
        }
    }

    /**
     * Keep queues backed by the database on the central connection. A queue
     * connection without an explicit database connection would otherwise
     * follow the default connection into the tenant database, where workers
     * never look for jobs.
     */
    protected function pinDatabaseQueuesToCentral(): void
    {
        foreach ((array) config('queue.connections') as $name => $queue) {
            if (is_array($queue) && ($queue['driver'] ?? null) === 'database' && empty($queue['connection'])) {
                Config::set("queue.connections.{$name}.connection", $this->snapshot['database.default']);
            }
        }
    }

    /**
     * Drop session stores that have not been started yet or that belong to
     * another context, so the session is opened with the tenant's cookie name
     * and connection. A started store of the same tenant (e.g. when a sync
     * job restores the request's tenant) is left alone.
     */
    protected function refreshUnstartedSession(): void
    {
        if (! app()->resolved('session')) {
            return;
        }

        $session = app('session');
        $stale = false;

        foreach ($session->getDrivers() as $store) {
            if (! $store->isStarted() || $store->getName() !== config('session.cookie')) {
                $stale = true;
            }
        }

        if (! $stale) {
            return;
        }

        $session->forgetDrivers();
        app()->forgetInstance('session.store');

        if (app()->resolved('auth')) {
            app('auth')->forgetGuards();
        }
    }

    protected function setSessionSuffix(string $tenant): void
    {
        $base = $this->snapshot['session.cookie'] ?? config('session.cookie');

        Config::set('session.cookie', $base.'_'.$tenant);
    }

    /**
     * Scope every configured disk to the tenant: local disks get a tenant
     * folder (and matching public URL), remote disks such as S3 a tenant
     * object prefix. The default disk setting is left untouched.
     */
    protected function setStorageSuffix(string $tenant): void
    {
        $segment = trim(config('easy-multitenancy.storage.path', 'tenants'), '/').'/'.$tenant;

        foreach ((array) config('easy-multitenancy.storage.disks', ['local', 'public']) as $disk) {
            $key = "filesystems.disks.{$disk}";
            $config = array_key_exists($key, $this->snapshot) ? $this->snapshot[$key] : config($key);

            if (! is_array($config)) {
                continue;
            }

            $this->snapshot[$key] = $config;

            if (($config['driver'] ?? null) === 'local') {
                $config['root'] = rtrim($config['root'] ?? storage_path('app'), '/').'/'.$segment;

                if (isset($config['url'])) {
                    $config['url'] = rtrim($config['url'], '/').'/'.$segment;
                }
            } else {
                // Remote adapters already include the root in generated URLs.
                $config['root'] = ltrim(trim($config['root'] ?? '', '/').'/'.$segment, '/');
            }

            Config::set($key, $config);
            Storage::forgetDisk($disk);
        }
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

    /**
     * Whether a name is a well-formed tenant identifier (does not check existence).
     */
    /**
     * Tenant identifiers are 2 to 63 characters of lowercase letters, numbers
     * and single hyphens, neither leading nor trailing (like a DNS label).
     */
    public const NAME_PATTERN = '/^(?=.{2,63}$)[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    public function isValid(string $name): bool
    {
        return (bool) preg_match(self::NAME_PATTERN, $name);
    }

    /**
     * Whether a name would collide with a path the application already serves
     * outside tenants: an excluded pattern (e.g. "api/*", "up") or the first
     * segment of a central route. Such a tenant would be unreachable.
     */
    public function isReserved(string $name): bool
    {
        foreach (config('easy-multitenancy.routes.excluded_patterns', []) as $pattern) {
            if (is_string($pattern) && (Str::is($pattern, $name) || Str::is($pattern, $name.'/'))) {
                return true;
            }
        }

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if ($route->getAction('central') === true && explode('/', ltrim($route->uri(), '/'))[0] === $name) {
                return true;
            }
        }

        return false;
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
        $name = preg_replace('/[\s_]+/', '-', $name);
        $name = preg_replace('/[^a-z0-9-]/', '', $name);
        $name = trim(preg_replace('/-+/', '-', $name), '-');

        if (! $this->isValid($name)) {
            throw new \InvalidArgumentException("Cannot turn the given name into a valid tenant identifier ('{$name}').");
        }

        return $name;
    }

    protected function sanitizeTenantName(string $tenant): string
    {
        if (! $this->isValid($tenant)) {
            throw new TenantNotFoundException('Invalid tenant identifier.');
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

        $files = glob($path.'/*'.$extension) ?: [];

        $names = array_map(fn ($file) => basename($file, $extension), $files);

        // Files whose name is not a valid identifier are not reachable tenants.
        return array_values(array_filter($names, fn ($name) => $this->isValid($name)));
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

            // Reset the active cache store back to its pristine namespace.
            $store = Cache::getStore();
            if ($store instanceof FileStore && $this->cacheDirectory !== null) {
                $store->setDirectory($this->cacheDirectory);
                $this->cacheDirectory = null;
            } elseif ($store instanceof ArrayStore) {
                Cache::forgetDriver(config('cache.default'));
            } elseif (method_exists($store, 'setPrefix')) {
                $store->setPrefix((string) ($this->snapshot['cache.prefix'] ?? ''));
            }
            Cache::forgetDriver('database');

            foreach (array_keys($this->snapshot) as $key) {
                if (str_starts_with($key, 'filesystems.disks.')) {
                    Storage::forgetDisk(substr($key, strlen('filesystems.disks.')));
                }
            }

            $this->snapshot = [];
        }

        $this->currentTenant = null;
        $this->currentDatabase = null;
    }
}
