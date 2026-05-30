<?php

use Bit16\EasyMultitenancy\Facades\Tenant;

it('flushes the tenant context on octane request lifecycle events', function (string $event) {
    $this->createTenant('acme');
    Tenant::identify('acme');

    expect(Tenant::current())->toBe('acme');
    expect(DB::getDefaultConnection())->toBe(config('easy-multitenancy.database.connection'));

    $this->app['events']->dispatch($event);

    expect(Tenant::current())->toBeNull();
    expect(DB::getDefaultConnection())->toBe('testing');
})->with([
    'Laravel\Octane\Events\RequestReceived',
    'Laravel\Octane\Events\RequestTerminated',
    'Laravel\Octane\Events\TaskReceived',
    'Laravel\Octane\Events\TickReceived',
]);
