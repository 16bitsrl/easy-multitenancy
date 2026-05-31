<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\URL;

/*
 * Regression: response-phase URL generation (e.g. Livewire's auto-injected
 * update endpoint, or debug middleware) runs after IdentifyTenant has forgotten
 * the tenant. Registering the tenant as a default route parameter keeps those
 * {tenant} URLs generatable even once the tenant context is gone.
 */

it('keeps generating tenant route urls after the tenant context is forgotten', function () {
    $this->createTenant('acme');

    // Mirror what IdentifyTenant does over a request: identify the tenant,
    // register it as a URL default, then forget it as the response unwinds.
    Tenant::identify('acme');
    URL::defaults(['tenant' => 'acme']);
    Tenant::forget();

    expect(Tenant::current())->toBeNull()
        ->and(route('dashboard', [], false))->toBe('/acme/dashboard');
});

it('exposes the tenant as a url default through the identify middleware', function () {
    $this->createTenant('acme');

    // The dashboard handler generates a {tenant} URL without passing the
    // parameter explicitly, proving the middleware registered the default.
    $this->get('/acme/dashboard')->assertOk();

    expect(route('dashboard', [], false))->toBe('/acme/dashboard');
});
