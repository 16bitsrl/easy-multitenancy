<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Cookie\CookieValuePrefix;

it('does not set the recent-tenants cookie when tracking is disabled', function () {
    config()->set('easy-multitenancy.track_recent_tenants', false);
    $this->createTenant('acme');

    $response = $this->get('/acme/dashboard')->assertOk();

    $names = collect($response->headers->getCookies())->map->getName();
    expect($names)->not->toContain('em_recent_tenants');
});

it('writes the visited tenant into an encrypted shared cookie', function () {
    config()->set('easy-multitenancy.track_recent_tenants', true);
    $this->createTenant('acme');

    $response = $this->get('/acme/dashboard')->assertOk();

    $cookie = collect($response->headers->getCookies())
        ->firstWhere(fn ($c) => $c->getName() === 'em_recent_tenants');

    expect($cookie)->not->toBeNull();
    expect($cookie->getPath())->toBe('/'); // shared across all tenants

    // The cookie is encrypted by Laravel's EncryptCookies middleware.
    $plain = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
    $data = json_decode($plain, true);

    expect($data)->toHaveKey('acme');
});

it('reads and sorts recent tenants from the cookie, newest first', function () {
    request()->cookies->set('em_recent_tenants', json_encode([
        'acme' => 100,
        'contoso' => 300,
        'globex' => 200,
    ]));

    expect(Tenant::getRecentTenants())->toBe([
        'contoso' => 300,
        'globex' => 200,
        'acme' => 100,
    ]);
});

it('returns an empty list when no recent-tenants cookie is present', function () {
    expect(Tenant::getRecentTenants())->toBe([]);
});

it('routes sessions to the tenant database when use_tenant_db is enabled', function () {
    $originalDriver = config('session.driver');
    $originalConnection = config('session.connection');

    config()->set('easy-multitenancy.session.use_tenant_db', true);
    $this->createTenant('acme');

    Tenant::identify('acme');
    expect(config('session.driver'))->toBe('database');
    expect(config('session.connection'))->toBe(config('easy-multitenancy.database.connection'));

    Tenant::forget();
    expect(config('session.driver'))->toBe($originalDriver);
    expect(config('session.connection'))->toBe($originalConnection);
});
