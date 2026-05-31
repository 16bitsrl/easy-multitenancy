<?php

it('serves the default central home at / with the app name and tenant picker', function () {
    config()->set('app.name', 'Acme Suite');

    $this->get('/')
        ->assertOk()
        ->assertSee('Acme Suite')
        ->assertSee('Tenant recenti')
        ->assertSee('Nessun tenant visitato di recente.')
        ->assertSee('name="tenant"', false);
});

it('redirects to the tenant root when the submitted tenant exists', function () {
    $this->createTenant('acme');

    $this->post('/', ['tenant' => 'acme'])->assertRedirect('/acme');
    $this->post('/', ['tenant' => 'ACME'])->assertRedirect('/acme'); // case-insensitive courtesy
});

it('shows a generic "tenant non trovato" for an invalid identifier', function () {
    $this->followingRedirects()
        ->post('/', ['tenant' => 'Acme Corp'])
        ->assertOk()
        ->assertSee('Tenant non trovato');
});

it('shows a generic "tenant non trovato" for a non-existent tenant and keeps the input', function () {
    $this->followingRedirects()
        ->post('/', ['tenant' => 'ghost'])
        ->assertOk()
        ->assertSee('Tenant non trovato')
        ->assertSee('value="ghost"', false);
});
