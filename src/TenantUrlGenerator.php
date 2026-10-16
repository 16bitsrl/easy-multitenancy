<?php

namespace Bit16\EasyMultitenancy;

use Bit16\EasyMultitenancy\Traits\ChecksRouteExclusions;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\UrlGenerator;

class TenantUrlGenerator extends UrlGenerator
{
    use ChecksRouteExclusions;

    /**
     * Path patterns of central routes, built lazily from the route collection.
     *
     * @var array<int, string>|null
     */
    protected ?array $centralPathPatterns = null;

    public function to($path, $extra = [], $secure = null)
    {
        $tenant = app('tenant')->current();

        if ($tenant && ! $this->isValidUrl($path)) {
            $normalizedPath = ltrim($path, '/');
            $firstSegment = explode('/', $normalizedPath)[0];

            if ($firstSegment !== $tenant
                && ! $this->shouldExcludeRoute(null, null, $normalizedPath)
                && ! $this->isCentralPath($normalizedPath)) {
                $path = '/'.$tenant.'/'.$normalizedPath;
            }
        }

        return parent::to($path, $extra, $secure);
    }

    public function getDefaultParameters()
    {
        $defaults = parent::getDefaultParameters();

        $parameter = $this->tenantParameter();
        $tenant = app('tenant')->current();

        if ($tenant && ! isset($defaults[$parameter])) {
            $defaults[$parameter] = $tenant;
        }

        return $defaults;
    }

    public function toRoute($route, $parameters, $absolute)
    {
        // Central routes are never tenant-scoped.
        if ($route->getAction('central') === true) {
            return parent::toRoute($route, $parameters, $absolute);
        }

        $parameters = is_array($parameters) ? $parameters : [$parameters];

        $parameter = $this->tenantParameter();
        $tenant = $this->resolveTenant($parameter);
        $routeIsTenantScoped = str_contains($route->uri(), '{'.$parameter.'}');
        $excluded = $this->shouldExcludeRoute($route, $route->getName(), $route->uri());

        // Supply the tenant parameter for tenant-scoped routes when it wasn't
        // passed explicitly (e.g. Livewire calling toRoute() directly).
        if ($tenant && $routeIsTenantScoped && ! $excluded && ! isset($parameters[$parameter])) {
            $parameters = array_merge([$parameter => $tenant], $parameters);
        }

        $url = parent::toRoute($route, $parameters, $absolute);

        // Safety net: some packages (notably Livewire when routes are cached)
        // generate URLs from a route object captured before tenant prefixing
        // ran, so the route has no {tenant} segment. Prefix the resulting path.
        if ($tenant && ! $routeIsTenantScoped && ! $excluded) {
            $url = $this->prefixGeneratedUrl($url, $tenant);
        }

        return $url;
    }

    public function setRoutes(RouteCollectionInterface $routes)
    {
        $this->centralPathPatterns = null;

        return parent::setRoutes($routes);
    }

    protected function tenantParameter(): string
    {
        return config('easy-multitenancy.routes.parameter', 'tenant');
    }

    /**
     * Whether a path points at a route registered via Tenant::centralRoutes().
     * The root path is left out: inside a tenant, url('/') is the tenant home.
     */
    protected function isCentralPath(string $path): bool
    {
        $path = trim($this->parseUriPath($path), '/');

        if ($path === '') {
            return false;
        }

        if ($this->centralPathPatterns === null) {
            $this->centralPathPatterns = [];

            foreach ($this->routes->getRoutes() as $route) {
                $uri = trim($route->uri(), '/');

                if ($route->getAction('central') === true && $uri !== '') {
                    $this->centralPathPatterns[] = '#^'.preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote($uri, '#')).'$#';
                }
            }
        }

        foreach ($this->centralPathPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the active tenant from the request-time context, falling back to
     * the registered URL default so generation keeps working in the response
     * phase (after the tenant has been forgotten).
     */
    protected function resolveTenant(string $parameter): ?string
    {
        return app('tenant')->current()
            ?? (parent::getDefaultParameters()[$parameter] ?? null);
    }

    /**
     * Insert the tenant segment into an already generated URL's path when it is
     * missing, leaving the scheme/host (for absolute URLs) untouched.
     */
    protected function prefixGeneratedUrl(string $url, string $tenant): string
    {
        $root = '';
        $path = $url;

        if (preg_match('#^(https?://[^/]+)(/.*)?$#i', $url, $matches)) {
            $root = $matches[1];
            $path = $matches[2] ?? '/';
        } elseif (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $segment = '/'.$tenant;

        if ($path === $segment || str_starts_with($path, $segment.'/')) {
            return $url;
        }

        return $root.$segment.($path === '/' ? '' : $path);
    }
}
