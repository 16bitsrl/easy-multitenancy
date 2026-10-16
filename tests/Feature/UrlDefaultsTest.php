<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
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

    // Response phase: the tenant context is already forgotten, as when
    // Livewire injects its assets on RequestHandled.
    $generated = null;
    Event::listen(RequestHandled::class, function () use (&$generated) {
        $generated = [Tenant::current(), route('dashboard', [], false)];
    });

    $this->get('/acme/dashboard')->assertOk();

    expect($generated)->toBe([null, '/acme/dashboard']);
});
