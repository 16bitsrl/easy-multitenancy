<?php

use Bit16\EasyMultitenancy\Events\TenantIdentified;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\Event;

it('identifies the tenant from the url prefix', function () {
    $this->createTenant('acme');

    $this->get('/acme/dashboard')
        ->assertOk()
        ->assertSee('tenant:acme');
});

it('returns 404 for an unknown tenant', function () {
    $this->get('/acme/dashboard')->assertNotFound();
});

it('keeps excluded routes unprefixed and without a tenant', function () {
    $this->get('/home')
        ->assertOk()
        ->assertSee('central:none');
});

it('dispatches TenantIdentified when resolving a tenant route', function () {
    $this->createTenant('acme');

    Event::fake([TenantIdentified::class]);

    $this->get('/acme/dashboard')->assertOk();

    Event::assertDispatched(TenantIdentified::class, fn ($event) => $event->tenant === 'acme');
    Event::assertDispatchedTimes(TenantIdentified::class, 1);
});

it('isolates two tenants across separate requests', function () {
    $this->createTenant('acme');
    $this->createTenant('contoso');

    $this->get('/acme/dashboard')->assertSee('tenant:acme');
    expect(Tenant::current())->toBeNull();

    $this->get('/contoso/dashboard')->assertSee('tenant:contoso');
    expect(Tenant::current())->toBeNull();
});
