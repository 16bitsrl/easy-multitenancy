<?php

it('serves central routes without a tenant prefix and without a tenant context', function () {
    $this->createTenant('acme');

    $this->get('/pricing')
        ->assertOk()
        ->assertSee('pricing:none');
});

it('does not expose central routes under a tenant prefix', function () {
    $this->createTenant('acme');

    $this->get('/acme/pricing')->assertNotFound();
});
