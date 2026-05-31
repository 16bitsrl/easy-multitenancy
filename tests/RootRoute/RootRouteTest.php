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
