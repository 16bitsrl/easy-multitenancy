<?php

use Bit16\EasyMultitenancy\Contracts\GlobalJob;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Queue\QueueTenantInjector;

class SampleTenantJob
{
    public string $payload = 'work';
}

class SampleGlobalJob implements GlobalJob
{
}

function payloadFor(object $command): array
{
    return [
        'displayName' => get_class($command),
        'data' => [
            'commandName' => get_class($command),
            'command' => serialize($command),
        ],
    ];
}

beforeEach(function () {
    $this->injector = app(QueueTenantInjector::class);
});

it('injects the current tenant into a job payload', function () {
    $this->createTenant('acme');
    Tenant::identify('acme');

    $payload = payloadFor(new SampleTenantJob());

    expect($this->injector->shouldInjectTenant($payload))->toBeTrue();

    $injected = $this->injector->injectTenant($payload);
    expect($injected['tenant_id'])->toBe('acme');
});

it('does not inject when there is no active tenant', function () {
    expect($this->injector->shouldInjectTenant(payloadFor(new SampleTenantJob())))->toBeFalse();
});

it('does not inject jobs implementing GlobalJob', function () {
    $this->createTenant('acme');
    Tenant::identify('acme');

    expect($this->injector->shouldInjectTenant(payloadFor(new SampleGlobalJob())))->toBeFalse();
});

it('does not inject jobs excluded by class name', function () {
    config()->set('easy-multitenancy.queue.excluded_jobs', [SampleTenantJob::class]);

    $this->createTenant('acme');
    Tenant::identify('acme');

    expect($this->injector->shouldInjectTenant(payloadFor(new SampleTenantJob())))->toBeFalse();
});

it('uses job metadata for class exclusions before unserializing commands', function () {
    config()->set('easy-multitenancy.queue.excluded_jobs', [SampleTenantJob::class]);

    $this->createTenant('acme');
    Tenant::identify('acme');

    $payload = [
        'displayName' => SampleTenantJob::class,
        'data' => [
            'commandName' => SampleTenantJob::class,
            'command' => 'not-a-serialized-command',
        ],
    ];

    expect($this->injector->shouldInjectTenant($payload))->toBeFalse();
});

it('restores and cleans up the tenant context for a job', function () {
    $this->createTenant('acme');

    $payload = ['tenant_id' => 'acme'];

    expect($this->injector->shouldRestoreTenant($payload))->toBeTrue();

    $this->injector->restoreTenant($payload);
    expect(Tenant::current())->toBe('acme');

    $this->injector->cleanupTenant();
    expect(Tenant::current())->toBeNull();
});

it('skips restoring for a non-existent tenant in strict mode', function () {
    config()->set('easy-multitenancy.queue.strict_mode', true);

    expect($this->injector->shouldRestoreTenant(['tenant_id' => 'ghost']))->toBeFalse();
});
