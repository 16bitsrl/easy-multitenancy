<?php

use Bit16\EasyMultitenancy\Events\DatabaseSwitched;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->createTenant('acme');
    $this->createTenant('contoso');
});

it('ignores a url on the tenant connection so every tenant keeps its own database', function () {
    config()->set('database.connections.tenant', [
        'driver' => 'sqlite',
        'url' => 'sqlite:///'.$this->tenantPath.'/shared.sqlite',
    ]);

    Tenant::identify('acme');

    expect(DB::connection('tenant')->getDatabaseName())->toBe(Tenant::getDatabasePath('acme'));
});

it('isolates cache stores without key prefix support', function (string $store) {
    config()->set('cache.default', $store);
    config()->set('cache.stores.file.path', $this->tenantPath.'/cache');

    Tenant::identify('acme');
    Cache::put('shared-key', 'acme-value');
    Tenant::forget();

    Tenant::identify('contoso');
    expect(Cache::get('shared-key'))->toBeNull();
    Tenant::forget();

    Tenant::identify('acme');
    expect(Cache::get('shared-key'))->toBe($store === 'file' ? 'acme-value' : null);
})->with(['file', 'array']);

it('restores the tenant connection configuration on forget', function () {
    expect(config('database.connections.tenant'))->toBeNull();

    Tenant::identify('acme');
    Tenant::forget();

    expect(config('database.connections.tenant'))->toBeNull();
});

it('rolls back a partially applied tenant switch when identification fails', function () {
    Event::listen(DatabaseSwitched::class, fn () => throw new RuntimeException('listener failed'));

    expect(fn () => Tenant::identify('acme'))->toThrow(RuntimeException::class);

    expect(Tenant::current())->toBeNull()
        ->and(DB::getDefaultConnection())->toBe('testing');
});
