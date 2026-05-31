<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;

/*
 * Regression: packages such as Livewire generate URLs via url()->toRoute()
 * from a route object captured before tenant prefixing ran (notably when
 * routes are cached). The generator must still produce a tenant-scoped path,
 * otherwise the request 404s (e.g. POST /livewire/update instead of
 * /{tenant}/livewire/update).
 */

it('prefixes generated route urls that lack the tenant segment', function () {
    $this->createTenant('acme');
    Tenant::identify('acme');

    $route = new Route('POST', 'livewire/update', []);

    expect(url()->toRoute($route, [], false))->toBe('/acme/livewire/update');

    Tenant::forget();
});

it('prefixes via the URL default once the tenant context is forgotten', function () {
    $this->createTenant('acme');

    // Mirror the request lifecycle: identify, register the default, forget.
    Tenant::identify('acme');
    URL::defaults(['tenant' => 'acme']);
    Tenant::forget();

    $route = new Route('POST', 'livewire/update', []);

    expect(url()->toRoute($route, [], false))->toBe('/acme/livewire/update');
});

it('does not prefix excluded routes', function () {
    $this->createTenant('acme');
    Tenant::identify('acme');

    expect(url()->toRoute(new Route('GET', 'app.js', []), [], false))->toBe('/app.js');

    Tenant::forget();
});

it('does not double-prefix already tenant-scoped routes', function () {
    $this->createTenant('acme');
    Tenant::identify('acme');

    $route = new Route('GET', '{tenant}/dashboard', []);

    expect(url()->toRoute($route, [], false))->toBe('/acme/dashboard');

    Tenant::forget();
});
