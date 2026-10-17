<?php

namespace Bit16\EasyMultitenancy;

use Bit16\EasyMultitenancy\Commands\CreateTenantCommand;
use Bit16\EasyMultitenancy\Commands\ListTenantsCommand;
use Bit16\EasyMultitenancy\Commands\MigrateAllTenantsCommand;
use Bit16\EasyMultitenancy\Commands\MigrateTenantCommand;
use Bit16\EasyMultitenancy\Commands\SeedAllTenantsCommand;
use Bit16\EasyMultitenancy\Commands\SeedTenantCommand;
use Bit16\EasyMultitenancy\Commands\UpgradeCheckCommand;
use Bit16\EasyMultitenancy\Http\Controllers\CentralHomeController;
use Bit16\EasyMultitenancy\Managers\TenantManager;
use Bit16\EasyMultitenancy\Middleware\IdentifyTenant;
use Bit16\EasyMultitenancy\Middleware\TrackRecentTenant;
use Bit16\EasyMultitenancy\Queue\QueueTenantInjector;
use Bit16\EasyMultitenancy\Traits\ChecksRouteExclusions;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EasyMultitenancyServiceProvider extends PackageServiceProvider
{
    use ChecksRouteExclusions;
    public function configurePackage(Package $package): void
    {
        $package
            ->name('easy-multitenancy')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasCommands([
                ListTenantsCommand::class,
                CreateTenantCommand::class,
                MigrateTenantCommand::class,
                MigrateAllTenantsCommand::class,
                SeedTenantCommand::class,
                SeedAllTenantsCommand::class,
                UpgradeCheckCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton('tenant', function () {
            return new TenantManager();
        });

        $this->app->alias('tenant', TenantManager::class);

        $this->app->singleton(QueueTenantInjector::class);

        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();

            $url = new TenantUrlGenerator(
                $routes,
                $app->rebinding('request', function ($app, $request) {
                    $app['url']->setRequest($request);
                }),
                $app['config']['app.asset_url']
            );

            $url->setSessionResolver(function () {
                return $this->app['session'] ?? null;
            });

            $url->setKeyResolver(function () {
                return $this->app->make('config')->get('app.key');
            });

            $app->rebinding('routes', function ($app, $routes) {
                $app['url']->setRoutes($routes);
            });

            return $url;
        });
    }

    public function bootingPackage(): void
    {
        $this->registerCentralConnection();
        $this->registerOctaneResetListeners();

        $this->app->booted(function () {
            $router = $this->app->make(Router::class);
            $router->aliasMiddleware('tenant', IdentifyTenant::class);
        });

        $this->prioritizeTenantMiddleware();

        $this->rewriteRoutes();

        $this->registerDefaultCentralHome();

        $this->reindexRoutesAfterRewrite();

        if (config('easy-multitenancy.queue.tenant_aware', true)) {
            $this->registerQueueTenantInjection();
        }
    }

    /**
     * Ensure tenant identification runs before the session is started (and so
     * before Laravel's Authenticate middleware).
     *
     * Route middleware is executed in the order of a global priority list.
     * StartSession and Authenticate are on that list while the tenant
     * middleware is not, so they could otherwise run first: the session would
     * be opened with the shared cookie name (leaking the session, and the
     * logged-in user, across tenants) and a guest's "{tenant}/login" redirect
     * would be generated before the tenant is known.
     */
    protected function prioritizeTenantMiddleware(): void
    {
        $this->app->booted(function () {
            // Resolve the application's HTTP kernel singleton. Every standard
            // Laravel kernel extends the foundation kernel, which exposes the
            // middleware-priority API used below.
            $kernel = $this->app->make(HttpKernel::class);

            // Placed before the earliest of these that is on the list. The auth
            // middleware is listed by its contract, with the concrete class kept
            // as a fallback for apps that reference it directly.
            $kernel->addToMiddlewarePriorityBefore(
                [StartSession::class, AuthenticatesRequests::class, Authenticate::class],
                IdentifyTenant::class,
            );
        });
    }

    /**
     * Rebuild the route collection once all URI rewriting (tenant prefixing and
     * central-home registration) has happened, so every route is re-indexed
     * under its current URI, with tenant routes after all the others.
     *
     * Prefixing mutates a route's URI in place (e.g. "/" becomes "{tenant}")
     * but the collection keeps indexing it under its original key. When the
     * collection is later compiled for the route cache, generating the missing
     * names re-adds the central "/" route onto the stale "/" slot and evicts the
     * prefixed root route, dropping it from the cache. Re-adding every route to
     * a fresh collection recomputes the keys and prevents the collision.
     */
    protected function reindexRoutesAfterRewrite(): void
    {
        // Cached routes are already correctly indexed in the cache file.
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->app->booted(function () {
            $router = $this->app->make(Router::class);

            $rebuilt = new RouteCollection();
            $placeholder = '{'.$this->tenantParameter().'}';
            $tenantRoutes = [];

            // Routes are matched in order: register tenant routes last, so a
            // catch-all such as "{tenant}" (an app root) cannot shadow central
            // or excluded routes registered after it (e.g. "/pricing").
            foreach ($router->getRoutes()->getRoutes() as $route) {
                $uri = $route->uri();

                if ($uri === $placeholder || str_starts_with($uri, $placeholder.'/')) {
                    $tenantRoutes[] = $route;
                } else {
                    $rebuilt->add($route);
                }
            }

            foreach ($tenantRoutes as $route) {
                $rebuilt->add($route);
            }

            $router->setRoutes($rebuilt);
        });
    }

    /**
     * Register a default central home at "/" when enabled and the host app has
     * not already defined a root route. Runs after auto-prefixing so it can
     * detect an app-defined central "/" and step aside.
     */
    protected function registerDefaultCentralHome(): void
    {
        if (! config('easy-multitenancy.central.default_home', true)) {
            return;
        }

        // When routes are cached the central home is already baked into the
        // cache; re-registering it at runtime would duplicate the route.
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->app->booted(function () {
            $router = $this->app->make(Router::class);
            $routes = $router->getRoutes();

            foreach ($routes->getRoutes() as $route) {
                if (ltrim($route->uri(), '/') === '') {
                    return; // a root route already exists — don't override it
                }
            }

            // Reuse the central-routes marker so the routes land on their own
            // collection slot (auto-prefixed app roots keep their "/" slot, so
            // adding "/" directly would evict them), then strip the marker.
            $added = [];
            $this->app->make('tenant')->centralRoutes(function () use ($router, &$added) {
                $added[] = $router->get('/', [CentralHomeController::class, 'show'])->middleware('web');
                $added[] = $router->post('/', [CentralHomeController::class, 'submit'])->middleware('web');
            });

            foreach ($added as $route) {
                $this->processCentralRoute($route);
            }

            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }

    /**
     * Inject the current tenant into queued jobs at dispatch and restore it
     * before each job runs, then put the previous context back. Jobs can opt
     * out via the GlobalJob interface, config exclusions or a
     * `tenantAware = false` property.
     */
    protected function registerQueueTenantInjection(): void
    {
        $injector = $this->app->make(QueueTenantInjector::class);

        Queue::createPayloadUsing(function ($connection, $queue, $payload) use ($injector) {
            return $injector->shouldInjectTenant($payload)
                ? $injector->injectTenant($payload)
                : $payload;
        });

        Event::listen(JobProcessing::class, fn ($event) => $injector->beginJob($event->job));

        // Every way an attempt can end: success, an exception (the job may be
        // released for a retry), or failure.
        Event::listen(
            [JobProcessed::class, JobExceptionOccurred::class, JobFailed::class],
            fn ($event) => $injector->endJob($event->job),
        );
    }

    /**
     * Reset the tenant context between requests/tasks on long-running workers
     * (Octane). The application instance is reused there, so tenant state and
     * the mutated configuration must be flushed at each lifecycle boundary to
     * avoid leaking across tenants. Listeners are keyed by the Octane event
     * class names as strings, so they are harmless no-ops when Octane is absent.
     */
    protected function registerOctaneResetListeners(): void
    {
        // Referenced as strings so PHPStan/autoloading don't require Octane.
        $events = [
            'Laravel\Octane\Events\RequestReceived',
            'Laravel\Octane\Events\RequestTerminated',
            'Laravel\Octane\Events\TaskReceived',
            'Laravel\Octane\Events\TickReceived',
        ];

        foreach ($events as $event) {
            $this->app['events']->listen($event, function () {
                if ($this->app->resolved('tenant')) {
                    $this->app->make('tenant')->forget();
                    $this->app->make('tenant')->flushSessionState();
                }

                if ($this->app->resolved('url')) {
                    $this->app->make('url')->defaults([$this->tenantParameter() => null]);
                }
            });
        }
    }

    /**
     * Register a stable "central" connection that always points at the
     * landlord database (the default connection as configured at boot),
     * so it stays reachable even while a tenant connection is active.
     */
    protected function registerCentralConnection(): void
    {
        if (! config('easy-multitenancy.central.enabled', false)) {
            return;
        }

        $central = config('easy-multitenancy.central.connection', 'central');

        // Respect an explicitly user-defined central connection.
        if (config("database.connections.{$central}") !== null) {
            return;
        }

        $default = config('database.default');
        $defaultConfig = config("database.connections.{$default}");

        if (is_array($defaultConfig)) {
            config()->set("database.connections.{$central}", $defaultConfig);
        }
    }

    /**
     * Strip the central-route marker from routes declared via
     * Tenant::centralRoutes() and, when auto-prefixing is enabled, prefix every
     * other route with the tenant parameter.
     */
    protected function rewriteRoutes(): void
    {
        // When routes are cached the rewriting is already baked into the cache
        // file (it ran while the cache was being built). Re-running it at
        // runtime would double-prefix routes loaded from the cache.
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->app->booted(function () {
            $this->rewriteRouteCollection($this->app->make(Router::class)->getRoutes());
        });

        $this->app->rebinding('routes', function ($app, $routes) {
            $this->rewriteRouteCollection($routes);
        });
    }

    protected function rewriteRouteCollection(RouteCollectionInterface $routes): void
    {
        $autoPrefix = config('easy-multitenancy.routes.auto_prefix', true);
        $excludedRoutes = config('easy-multitenancy.routes.excluded_routes', []);
        $excludedPatterns = config('easy-multitenancy.routes.excluded_patterns', []);

        foreach ($routes->getRoutes() as $route) {
            // Central routes: strip the marker prefix and skip tenant prefixing.
            if ($this->processCentralRoute($route)) {
                continue;
            }

            if ($autoPrefix && $this->shouldPrefixRoute($route, $excludedRoutes, $excludedPatterns)) {
                $this->prefixRoute($route);
            }
        }

        $routes->refreshNameLookups();
        $routes->refreshActionLookups();
    }

    protected function shouldPrefixRoute(Route $route, array $excludedRoutes, array $excludedPatterns): bool
    {
        // Central routes are never tenant-prefixed.
        if ($route->getAction('central') === true) {
            return false;
        }

        $name = $route->getName();
        $uri = $route->uri();
        $placeholder = '{'.$this->tenantParameter().'}';

        // Already tenant-scoped (re-processing, or declared explicitly).
        if ($uri === $placeholder || str_starts_with($uri, $placeholder.'/')) {
            return false;
        }

        // Use the shared exclusion logic from trait (inverted logic)
        return !$this->isRouteExcluded($route, $name, $uri, $excludedRoutes, $excludedPatterns);
    }

    /**
     * Detect a route registered via Tenant::centralRoutes(): strip the marker
     * prefix and flag it as central so it is never tenant-prefixed.
     */
    protected function processCentralRoute(Route $route): bool
    {
        $uri = $route->uri();

        if ($uri !== '__central-route__' && !str_starts_with($uri, '__central-route__/')) {
            return false;
        }

        $cleanUri = $uri === '__central-route__' ? '/' : substr($uri, strlen('__central-route__/'));
        $route->setUri($cleanUri === '' ? '/' : $cleanUri);

        $action = $route->getAction();
        $action['central'] = true;
        // Drop the marker prefix from the action: the URI has already been
        // cleaned above, and a lingering 'prefix' is re-applied to the URI when
        // the route is rebuilt from the route cache, resurfacing the marker.
        unset($action['prefix']);
        $route->setAction($action);

        return true;
    }

    protected function tenantParameter(): string
    {
        return config('easy-multitenancy.routes.parameter', 'tenant');
    }

    protected function prefixRoute(Route $route): void
    {
        // prefix() also records the segment in the action's "prefix", which
        // the route cache relies on to rebuild the URI of grouped routes.
        $route->prefix('{'.$this->tenantParameter().'}');

        // Only well-formed identifiers match, so other paths fall through to
        // the routes registered after the tenant ones.
        $route->where($this->tenantParameter(), '[a-z0-9]+(?:-[a-z0-9]+)*');

        $action = $route->getAction();
        if (!isset($action['middleware'])) {
            $action['middleware'] = [];
        }

        if (!is_array($action['middleware'])) {
            $action['middleware'] = [$action['middleware']];
        }

        // Identify the tenant first: the session, route model bindings and
        // auth must all be resolved within the tenant context.
        if (! in_array('tenant', $action['middleware'], true)) {
            array_unshift($action['middleware'], 'tenant');
        }

        // Track the visited tenant (self-guards on the config flag).
        if (! in_array(TrackRecentTenant::class, $action['middleware'], true)) {
            $action['middleware'][] = TrackRecentTenant::class;
        }

        $route->setAction($action);
    }
}
