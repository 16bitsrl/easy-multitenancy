<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Routing\Exceptions\UrlGenerationException;

beforeEach(function () {
    $this->createTenant('acme');
});

it('generates tenant and central urls correctly from a tenant request', function () {
    $links = $this->get('/acme/links')->assertOk()->json();

    expect($links)
        ->dashboard->toBe('/acme/dashboard')
        ->pricing_route->toBe('/pricing')
        ->pricing_url->toBe('http://localhost/pricing')
        ->mailto->toBe('mailto:hello@example.com')
        ->fragment->toBe('#top')
        ->lookalike->toBe('http://localhost/acme/acme-docs/intro')
        ->already_prefixed->toBe('http://localhost/acme/dashboard');
});

it('does not carry the tenant url default over to later requests', function () {
    $this->get('/acme/dashboard')->assertOk();

    expect(Tenant::current())->toBeNull();
    expect(fn () => route('dashboard'))->toThrow(UrlGenerationException::class);
});

it('clears the tenant url default on octane request boundaries', function () {
    Tenant::identify('acme');
    URL::defaults(['tenant' => 'acme']);

    $this->app['events']->dispatch('Laravel\Octane\Events\RequestReceived');

    expect(fn () => route('dashboard'))->toThrow(UrlGenerationException::class);
});

it('keeps tenant-prefixed group routes working once routes are cached', function () {
    $router = app('router');
    $router->setCompiledRoutes($router->getRoutes()->compile());

    $this->get('/acme/admin/review')->assertOk()->assertSee('review:acme');
    $this->get('/acme/dashboard')->assertOk()->assertSee('tenant:acme');
    $this->get('/pricing')->assertOk()->assertSee('pricing:none');
});
