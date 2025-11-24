<?php

namespace Bit16\EasyMultitenancy;

use Bit16\EasyMultitenancy\Commands\CreateTenantCommand;
use Bit16\EasyMultitenancy\Commands\ListTenantsCommand;
use Bit16\EasyMultitenancy\Commands\MigrateAllTenantsCommand;
use Bit16\EasyMultitenancy\Commands\MigrateTenantCommand;
use Bit16\EasyMultitenancy\Commands\SeedAllTenantsCommand;
use Bit16\EasyMultitenancy\Commands\SeedTenantCommand;
use Bit16\EasyMultitenancy\Managers\TenantManager;
use Bit16\EasyMultitenancy\Middleware\IdentifyTenant;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EasyMultitenancyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('easy-multitenancy')
            ->hasConfigFile()
            ->hasCommands([
                ListTenantsCommand::class,
                CreateTenantCommand::class,
                MigrateTenantCommand::class,
                MigrateAllTenantsCommand::class,
                SeedTenantCommand::class,
                SeedAllTenantsCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton('tenant', function () {
            return new TenantManager();
        });

        $this->app->alias('tenant', TenantManager::class);

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
        $this->app->booted(function () {
            $router = $this->app->make(Router::class);
            $router->aliasMiddleware('tenant', IdentifyTenant::class);

            // Add IdentifyTenant BEFORE auth middleware by prepending to web group
            $router->prependMiddlewareToGroup('web', IdentifyTenant::class);
        });

        if (config('easy-multitenancy.routes.auto_prefix', true)) {
            $this->autoPrefixRoutes();
        }
    }

    protected function autoPrefixRoutes(): void
    {
        $prefixed = [];

        $this->app->booted(function () use (&$prefixed) {
            $router = $this->app->make(Router::class);
            $excludedRoutes = config('easy-multitenancy.routes.excluded_routes', []);
            $excludedPatterns = config('easy-multitenancy.routes.excluded_patterns', []);

            foreach ($router->getRoutes()->getRoutes() as $route) {
                $routeKey = spl_object_id($route);

                if (!in_array($routeKey, $prefixed) && $this->shouldPrefixRoute($route, $excludedRoutes, $excludedPatterns)) {
                    $this->prefixRoute($route);
                    $prefixed[] = $routeKey;
                }
            }

            $router->getRoutes()->refreshNameLookups();
            $router->getRoutes()->refreshActionLookups();
        });

        $this->app->rebinding('routes', function ($app, $routes) use (&$prefixed) {
            $excludedRoutes = config('easy-multitenancy.routes.excluded_routes', []);
            $excludedPatterns = config('easy-multitenancy.routes.excluded_patterns', []);

            foreach ($routes as $route) {
                $routeKey = spl_object_id($route);

                if (!in_array($routeKey, $prefixed) && $this->shouldPrefixRoute($route, $excludedRoutes, $excludedPatterns)) {
                    $this->prefixRoute($route);
                    $prefixed[] = $routeKey;
                }
            }

            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }

    protected function shouldPrefixRoute(Route $route, array $excludedRoutes, array $excludedPatterns): bool
    {
        $name = $route->getName();
        $uri = $route->uri();

        if (str_starts_with($uri, '{tenant}/')) {
            return false;
        }

        if ($name && in_array($name, $excludedRoutes)) {
            return false;
        }

        foreach ($excludedPatterns as $pattern) {
            if ($name && Str::is($pattern, $name)) {
                return false;
            }
            if (Str::is($pattern, $uri)) {
                return false;
            }
        }

        return true;
    }

    protected function prefixRoute(Route $route): void
    {
        $currentUri = $route->uri();
        $currentUri = ltrim($currentUri, '/');

        if ($currentUri === '') {
            $route->setUri('{tenant}');
        } else {
            $route->setUri('{tenant}/' . $currentUri);
        }

        $action = $route->getAction();
        if (!isset($action['middleware'])) {
            $action['middleware'] = [];
        }

        if (!is_array($action['middleware'])) {
            $action['middleware'] = [$action['middleware']];
        }

        if (!in_array('tenant', $action['middleware'])) {
            $action['middleware'][] = 'tenant';
        }

        $route->setAction($action);
    }
}
