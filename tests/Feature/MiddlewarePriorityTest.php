<?php

use Bit16\EasyMultitenancy\Middleware\IdentifyTenant;
use Illuminate\Auth\Middleware\Authenticate;

/*
 * Regression: tenant identification must run before the auth middleware.
 * Laravel sorts route middleware by priority, and the auth middleware is on
 * that list while the tenant middleware is not. Without prioritising it, a
 * guest hitting a protected tenant route triggers the "{tenant}/login" redirect
 * before the tenant is known, throwing a missing-parameter error instead of
 * redirecting.
 */

it('runs tenant identification before the auth middleware', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->firstWhere(fn ($r) => $r->getName() === 'account');

    $ordered = app('router')->gatherRouteMiddleware($route);

    $tenantAt = array_search(IdentifyTenant::class, $ordered, true);
    $authAt = array_search(Authenticate::class, $ordered, true);

    expect($tenantAt)->not->toBeFalse()
        ->and($authAt)->not->toBeFalse()
        ->and($tenantAt)->toBeLessThan($authAt);
});

it('redirects an unauthenticated guest to the tenant login instead of erroring', function () {
    $this->createTenant('acme');

    $this->get('/acme/account')->assertRedirect('/acme/login');
});
