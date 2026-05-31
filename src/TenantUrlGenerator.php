<?php

namespace Bit16\EasyMultitenancy;

use Bit16\EasyMultitenancy\Traits\ChecksRouteExclusions;
use Illuminate\Routing\UrlGenerator;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class TenantUrlGenerator extends UrlGenerator
{
    use ChecksRouteExclusions;

    public function to($path, $extra = [], $secure = null)
    {
        $tenant = app('tenant')->current();

        if ($tenant && !str_starts_with($path, 'http')) {
            // Normalize path for checking
            $normalizedPath = ltrim($path, '/');

            // Check if this path should be excluded from tenant prefixing
            if (!$this->shouldExcludeRoute(null, null, $normalizedPath)) {
                if (str_starts_with($path, '/')) {
                    if (!str_starts_with($path, '/' . $tenant)) {
                        $path = '/' . $tenant . $path;
                    }
                } else {
                    $path = $tenant . '/' . $path;
                }
            }
        }

        return parent::to($path, $extra, $secure);
    }

    public function getDefaultParameters()
    {
        $defaults = parent::getDefaultParameters();

        $tenant = app('tenant')->current();
        if ($tenant && !isset($defaults['tenant'])) {
            $defaults['tenant'] = $tenant;
        }

        return $defaults;
    }

    public function toRoute($route, $parameters, $absolute)
    {
        $parameters = is_array($parameters) ? $parameters : [$parameters];

        $parameter = config('easy-multitenancy.routes.parameter', 'tenant');
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

    public function route($name, $parameters = [], $absolute = true)
    {
        if (!is_null($route = $this->routes->getByName($name))) {
            $tenant = app('tenant')->current();

            $parametersArray = is_array($parameters) ? $parameters : [$parameters];

            // Only add tenant parameter if route has {tenant} and is not excluded
            if (!isset($parametersArray['tenant']) && str_contains($route->uri(), '{tenant}')) {
                if ($tenant && !$this->shouldExcludeRoute($route, $name, $route->uri())) {
                    $parametersArray = array_merge(['tenant' => $tenant], $parametersArray);
                }
            }

            return $this->toRoute($route, $parametersArray, $absolute);
        }

        throw new RouteNotFoundException("Route [{$name}] not defined.");
    }
}
