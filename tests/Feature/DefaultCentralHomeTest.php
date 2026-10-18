<?php

it('serves the default central home at / with the app name and tenant picker', function () {
    config()->set('app.name', 'Acme Suite');

    $this->get('/')
        ->assertOk()
        ->assertSee('Acme Suite')
        ->assertSee('Recent tenants')
        ->assertSee('No recently visited tenants.')
        ->assertSee('<html lang="en">', false)
        ->assertSee('name="tenant"', false);
});

it('renders the central home in the application locale', function () {
    app()->setLocale('it');

    $this->get('/')
        ->assertOk()
        ->assertSee('Tenant recenti')
        ->assertSee('Nessun tenant visitato di recente.')
        ->assertSee('<html lang="it">', false);

    $this->followingRedirects()
        ->post('/', ['tenant' => 'ghost'])
        ->assertSee('Tenant non trovato.');
});

it('redirects to the tenant root when the submitted tenant exists', function () {
    $this->createTenant('acme');

    $this->post('/', ['tenant' => 'acme'])->assertRedirect('/acme');
    $this->post('/', ['tenant' => 'ACME'])->assertRedirect('/acme'); // case-insensitive courtesy
});

it('shows a generic "tenant not found" for an invalid identifier', function () {
    $this->followingRedirects()
        ->post('/', ['tenant' => 'Acme Corp'])
        ->assertOk()
        ->assertSee('Tenant not found.');
});

it('shows a generic "tenant not found" for a non-existent tenant and keeps the input', function () {
    $this->followingRedirects()
        ->post('/', ['tenant' => 'ghost'])
        ->assertOk()
        ->assertSee('Tenant not found.')
        ->assertSee('value="ghost"', false);
});

it('lists only existing tenants from the recent-tenants cookie and ignores malformed entries', function () {
    $this->createTenant('acme');

    $this->withCookie('em_recent_tenants', json_encode([
        'acme' => 100,
        'deleted' => 200,
        'Not Valid' => 300,
        'globex' => 'yesterday',
    ]))
        ->get('/')
        ->assertOk()
        ->assertSee('/acme', false)
        ->assertDontSee('/deleted', false)
        ->assertDontSee('Not Valid')
        ->assertDontSee('globex');
});

it('shows when each recent tenant was visited in the application locale', function () {
    $this->createTenant('acme');
    app()->setLocale('it');

    $this->withCookie('em_recent_tenants', json_encode(['acme' => now()->subHours(2)->timestamp]))
        ->get('/')
        ->assertOk()
        ->assertSee('2 ore fa');
});
