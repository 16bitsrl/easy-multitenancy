<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\Cache;

/*
 * Runs only against a Redis server dedicated to these tests, given as
 * EASY_MULTITENANCY_TEST_REDIS=host:port (e.g. a throwaway container).
 * Keys use a unique prefix and only those keys are removed afterwards.
 */

beforeEach(function () {
    $server = env('EASY_MULTITENANCY_TEST_REDIS');

    if (! $server || ! extension_loaded('redis')) {
        $this->markTestSkipped('Set EASY_MULTITENANCY_TEST_REDIS=host:port to run the Redis tests.');
    }

    [$host, $port] = explode(':', $server) + [1 => 6379];
    $port = (int) $port;

    config()->set('database.redis.client', 'phpredis');
    config()->set('database.redis.default', ['host' => $host, 'port' => $port, 'database' => 15]);
    config()->set('database.redis.cache', ['host' => $host, 'port' => $port, 'database' => 15]);
    config()->set('cache.stores.redis', ['driver' => 'redis', 'connection' => 'cache', 'lock_connection' => 'default']);
    config()->set('cache.default', 'redis');
    config()->set('cache.prefix', 'easy-mt-test-'.uniqid().'-');
    config()->set('session.driver', 'redis');

    $this->createTenant('acme');
    $this->createTenant('contoso');
});

afterEach(function () {
    if (config('cache.default') !== 'redis') {
        return;
    }

    Tenant::forget();
    // phpredis adds its own key prefix to patterns and keys it is given.
    $client = app('redis')->connection('cache')->client();
    $prefix = (string) $client->getOption(Redis::OPT_PREFIX);

    foreach ($client->keys(config('cache.prefix').'*') as $key) {
        $client->del(substr($key, strlen($prefix)));
    }
});

it('keeps redis cache entries apart per tenant', function () {
    Tenant::identify('acme');
    Cache::put('shared-key', 'acme-value');
    Tenant::forget();

    Tenant::identify('contoso');
    expect(Cache::get('shared-key'))->toBeNull();
    Tenant::forget();

    Tenant::identify('acme');
    expect(Cache::get('shared-key'))->toBe('acme-value');
});

it('keeps redis sessions apart per tenant', function () {
    $base = config('session.cookie');

    $sessionId = $this->get('/acme/session/put')->getCookie($base.'_acme')->getValue();

    $this->withCookie($base.'_acme', $sessionId)->get('/acme/session/get')->assertSee('who=acme');
    $this->withCookie($base.'_acme', $sessionId)->get('/contoso/session/get')->assertSee('who=null');
});
