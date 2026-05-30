<?php

namespace Bit16\EasyMultitenancy\Traits;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;

trait ChecksRouteExclusions
{
    protected function shouldExcludeRoute(?Route $route, ?string $name, ?string $uri): bool
    {
        $excludedRoutes = config('easy-multitenancy.routes.excluded_routes', []);
        $excludedPatterns = config('easy-multitenancy.routes.excluded_patterns', []);

        return $this->isRouteExcluded($route, $name, $uri, $excludedRoutes, $excludedPatterns);
    }

    protected function isRouteExcluded(?Route $route, ?string $name, ?string $uri, array $excludedRoutes, array $excludedPatterns): bool
    {
        // If route already has tenant prefix, don't exclude it
        if ($route && str_starts_with($route->uri(), '{tenant}/')) {
            return false;
        }

        // If URI already has tenant prefix, don't exclude it
        if ($uri && str_starts_with(ltrim($uri, '/'), '{tenant}/')) {
            return false;
        }

        // Check if route name is explicitly excluded
        if ($name && in_array($name, $excludedRoutes)) {
            return true;
        }

        // Parse URI to strip query params and fragments for accurate pattern matching
        if ($uri) {
            $uri = $this->parseUriPath($uri);
        }

        // Check if route name or URI matches any excluded patterns
        foreach ($excludedPatterns as $pattern) {
            if ($name && Str::is($pattern, $name)) {
                return true;
            }
            if ($uri && Str::is($pattern, ltrim($uri, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse URI to extract only the path portion (strip query params and fragments).
     */
    protected function parseUriPath(string $uri): string
    {
        // Remove query string (everything after '?')
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove fragment (everything after '#')
        if (($pos = strpos($uri, '#')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        return $uri;
    }
}
