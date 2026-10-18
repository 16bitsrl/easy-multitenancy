<?php

namespace Bit16\EasyMultitenancy\Middleware;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the visited tenant in a shared, browser-scoped cookie so the
 * landlord can list recently visited tenants (e.g. a tenant switcher).
 * The cookie is set at the root path so it is shared across every tenant.
 */
class TrackRecentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Read the tenant after the request is handled, so this works
        // regardless of where IdentifyTenant sits in the middleware order.
        $tenant = Tenant::current();

        if (! config('easy-multitenancy.track_recent_tenants', false) || ! $tenant) {
            return $response;
        }

        $cookieName = config('easy-multitenancy.recent_tenants.cookie', 'em_recent_tenants');
        $max = (int) config('easy-multitenancy.recent_tenants.max', 5);
        $lifetime = (int) config('easy-multitenancy.recent_tenants.lifetime', 43200);

        $recent = Tenant::getRecentTenants();
        $recent[$tenant] = now()->timestamp;

        arsort($recent);
        $recent = array_slice($recent, 0, max(1, $max), true);

        $response->headers->setCookie(
            cookie($cookieName, json_encode($recent, JSON_THROW_ON_ERROR), $lifetime, '/')
        );

        return $response;
    }
}
