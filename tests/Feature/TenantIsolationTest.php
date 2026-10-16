<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Tests\Fixtures\Post;

/*
 * Each tenant must get its own session: the session is started with the
 * tenant-specific cookie name, so a browser session opened on one tenant
 * (including the logged-in user id) is never picked up by another tenant.
 */

beforeEach(function () {
    config()->set('session.driver', 'file');
    config()->set('session.files', $this->tenantPath.'/sessions');
    mkdir($this->tenantPath.'/sessions', 0755, true);

    $this->createTenant('acme');
    $this->createTenant('contoso');
});

it('starts the session under the tenant-specific cookie name', function () {
    $base = config('session.cookie');

    $this->get('/acme/session/put')
        ->assertOk()
        ->assertCookie($base.'_acme')
        ->assertCookieMissing($base);
});

it('does not share a session between tenants', function () {
    $base = config('session.cookie');

    $sessionId = $this->get('/acme/session/put')->getCookie($base.'_acme')->getValue();

    // The same browser sends every cookie it holds to any tenant path.
    $this->withCookie($base.'_acme', $sessionId)
        ->get('/acme/session/get')
        ->assertSee('who=acme');

    $this->withCookie($base.'_acme', $sessionId)
        ->get('/contoso/session/get')
        ->assertSee('who=null');
});

it('keeps the tenant parameter out of the route action arguments', function () {
    $this->get('/acme/posts/42')->assertOk()->assertSee('post:42');
});

it('resolves route model bindings against the tenant database', function () {
    Tenant::identify('acme');
    Post::create(['title' => 'hello from acme']);
    Tenant::forget();

    $this->get('/acme/bound-posts/1')->assertOk()->assertSee('title:hello from acme');
    $this->get('/contoso/bound-posts/1')->assertNotFound();
});

it('does not echo the requested tenant name in the not found response', function () {
    $this->getJson('/ghost-tenant/dashboard')->assertNotFound()->assertDontSee('ghost-tenant');
});
