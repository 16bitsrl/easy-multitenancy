<?php

/*
 * Regression: registering the default central home must not evict an app root
 * route that was auto-prefixed to "{tenant}" (they share the "/" collection
 * slot until the index is rebuilt).
 */

it('keeps the app root route reachable under its tenant prefix', function () {
    $this->createTenant('acme');

    $this->get('/acme')
        ->assertOk()
        ->assertSee('app-root:acme');
});

it('still serves the default central home alongside a prefixed app root', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Tenant recenti');
});

it('keeps the prefixed root route when compiling for the route cache', function () {
    // compile() is what `route:cache` runs. It generates names for the unnamed
    // central "/" routes and re-adds them, which used to evict the prefixed app
    // root route from the collection and drop its name from the cached routes.
    $compiled = app('router')->getRoutes()->compile();

    expect($compiled['attributes'])->toHaveKey('approot')
        ->and($compiled['attributes']['approot']['uri'])->toBe('{tenant}');

    // The central home "/" still makes it into the compiled routes too.
    expect(collect($compiled['attributes'])->pluck('uri'))->toContain('/');

    // Name-based generation resolves the prefixed root route from the cache.
    expect(route('approot', ['tenant' => 'acme'], false))->toBe('/acme');
});
