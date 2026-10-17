<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\URL;

/*
 * Behaviour apps (and their test suites) rely on must survive tenant
 * requests: a URL default set by the app, and a user set with actingAs().
 */

beforeEach(function () {
    $this->createTenant('acme');
});

it('keeps a url default the app set before the request', function () {
    URL::defaults(['tenant' => 'acme']);

    $this->get('/acme/dashboard')->assertOk();

    expect(route('dashboard', [], false))->toBe('/acme/dashboard');
});

it('keeps the user set with actingAs across tenant requests', function () {
    $user = new User();
    $user->id = 1;

    $this->actingAs($user);

    $this->get('/acme/account')->assertOk();
    $this->get('/acme/account')->assertOk();
});
