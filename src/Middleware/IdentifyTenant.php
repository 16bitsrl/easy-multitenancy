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

        try {
            Tenant::identify($tenant);

            app('url')->defaults(['tenant' => $tenant]);
        } catch (TenantNotFoundException $e) {
            abort(404, $e->getMessage());
        }

        return $next($request);
    }
}
