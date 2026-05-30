<?php

use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\DB;

it('isolates data between tenants', function () {
    $this->createTenant('acme');
    $this->createTenant('contoso');

    Tenant::identify('acme');
    DB::table('posts')->insert(['title' => 'acme post']);
    expect(DB::table('posts')->count())->toBe(1);
    Tenant::forget();

    Tenant::identify('contoso');
    expect(DB::table('posts')->count())->toBe(0);
});

it('throws when identifying a non-existent tenant', function () {
    Tenant::identify('ghost');
})->throws(TenantNotFoundException::class);

it('reports existence based on the database file', function () {
    expect(Tenant::exists('acme'))->toBeFalse();

    $this->createTenant('acme');

    expect(Tenant::exists('acme'))->toBeTrue();
});

it('lists all tenants from the database directory', function () {
    $this->createTenant('acme');
    $this->createTenant('contoso');

    expect(Tenant::all())->toEqualCanonicalizing(['acme', 'contoso']);
});

it('normalizes user-supplied tenant names', function () {
    expect(Tenant::sanitize('  ACME Corp '))->toBe('acmecorp');
    expect(Tenant::sanitize('Client-Name'))->toBe('client-name');
    expect(Tenant::sanitize('../../etc/passwd'))->toBe('etcpasswd');
});

it('throws when a tenant name is empty after sanitization', function () {
    Tenant::sanitize('!!!');
})->throws(InvalidArgumentException::class);

it('rejects tenant names with invalid characters', function () {
    expect(Tenant::getDatabasePath('../../etc/passwd'))->toBeNull();
    expect(Tenant::getDatabasePath('Foo Bar'))->toBeNull();
    expect(Tenant::getDatabasePath('acme'))->not->toBeNull();
});

it('restores the default connection after forget', function () {
    $original = config('database.default');

    $this->createTenant('acme');
    Tenant::identify('acme');
    expect(DB::getDefaultConnection())->toBe(config('easy-multitenancy.database.connection'));

    Tenant::forget();
    expect(DB::getDefaultConnection())->toBe($original);
    expect(Tenant::current())->toBeNull();
});

/*
 * Regression: switching tenants must NOT accumulate the cache prefix.
 * Before the snapshot/restore fix this produced `base_acme_contoso_`.
 */
it('does not accumulate the cache prefix across tenant switches', function () {
    $base = config('cache.prefix');

    $this->createTenant('acme');
    $this->createTenant('contoso');

    Tenant::identify('acme');
    Tenant::forget();
    Tenant::identify('contoso');

    expect(config('cache.prefix'))->toBe($base.'contoso_');
});

/*
 * Regression: the session cookie name must not accumulate either.
 */
it('does not accumulate the session cookie across tenant switches', function () {
    $base = config('session.cookie');

    $this->createTenant('acme');
    $this->createTenant('contoso');

    Tenant::identify('acme');
    Tenant::forget();
    Tenant::identify('contoso');

    expect(config('session.cookie'))->toBe($base.'_contoso');
});

/*
 * Regression: forget() must restore cache/session config to the pristine
 * state, otherwise long-running workers leak state between tenants.
 */
it('restores cache and session config after forget', function () {
    $baseCache = config('cache.prefix');
    $baseCookie = config('session.cookie');
    $baseDisk = config('filesystems.default');

    $this->createTenant('acme');

    Tenant::identify('acme');
    Tenant::forget();

    expect(config('cache.prefix'))->toBe($baseCache);
    expect(config('session.cookie'))->toBe($baseCookie);
    expect(config('filesystems.default'))->toBe($baseDisk);
});
