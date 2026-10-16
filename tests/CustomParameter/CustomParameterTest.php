<?php

use Illuminate\Support\Facades\Route;

it('prefixes routes with the configured parameter name', function () {
    expect(Route::getRoutes()->getByName('dashboard')->uri())->toBe('{workspace}/dashboard');
});

it('identifies the tenant through the configured parameter', function () {
    $this->createTenant('acme');

    $this->get('/acme/dashboard')->assertOk()->assertSee('tenant:acme');
    $this->get('/acme/links')->assertOk()->assertJsonPath('dashboard', '/acme/dashboard');
});
