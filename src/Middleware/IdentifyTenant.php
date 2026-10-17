<?php

namespace Bit16\EasyMultitenancy\Middleware;

use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    protected const PREVIOUS_DEFAULT = 'easy-multitenancy.previous-url-default';

    public function handle(Request $request, Closure $next): Response
    {
        $parameter = config('easy-multitenancy.routes.parameter', 'tenant');
        $route = $request->route();
        $tenant = $route?->parameter($parameter);

        if (! $tenant) {
            return $next($request);
        }

        $identified = false;

        // Read before identifying: once a tenant is active it shows up as a default too.
        $request->attributes->set(self::PREVIOUS_DEFAULT, URL::getDefaultParameters()[$parameter] ?? null);

        try {
            Tenant::identify($tenant);
            $identified = true;

            // Register the tenant as a default route parameter so generating
            // any {tenant} route URL no longer requires passing it explicitly.
            // This also keeps URL generation working in the response phase
            // (e.g. Livewire's auto-injected update endpoint), which runs after
            // the tenant context below has been forgotten.
            URL::defaults([$parameter => $tenant]);

            // The tenant is available through Tenant::current(). Removing it
            // from the route parameters keeps it out of the arguments passed
            // positionally to controllers and closures.
            $route->forgetParameter($parameter);

            return $next($request);
        } catch (TenantNotFoundException $e) {
            abort(404);
        } finally {
            if ($identified) {
                Tenant::forget();
            }
        }
    }

    /**
     * Once the response has been sent, drop what was bound to the tenant (URL
     * default, session store) so it never carries over to the next request
     * handled by the same process.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Put back whatever default the app had set itself (usually none).
        URL::defaults([
            config('easy-multitenancy.routes.parameter', 'tenant') => $request->attributes->get(self::PREVIOUS_DEFAULT),
        ]);

        Tenant::flushSessionState();
    }
}
