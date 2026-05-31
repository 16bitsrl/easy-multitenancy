<?php

namespace Bit16\EasyMultitenancy\Middleware;

use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $parameter = config('easy-multitenancy.routes.parameter', 'tenant');
        $tenant = $request->route($parameter);

        if (! $tenant) {
            return $next($request);
        }

        $identified = false;

        try {
            Tenant::identify($tenant);
            $identified = true;

            return $next($request);
        } catch (TenantNotFoundException $e) {
            abort(404, $e->getMessage());
        } finally {
            if ($identified) {
                Tenant::forget();
            }
        }
    }
}
