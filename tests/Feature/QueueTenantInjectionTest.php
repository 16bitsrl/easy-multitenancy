<?php

use Bit16\EasyMultitenancy\Contracts\GlobalJob;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Queue\QueueTenantInjector;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;

class SampleTenantJob
{
    public string $payload = 'work';
}

class SampleGlobalJob implements GlobalJob
{
}

class SampleMailable extends Mailable
{
}

/**
 * Mirror the payload Laravel hands to payload hooks: the job object itself,
 * not yet serialized.
 */
function payloadFor(object $command): array
{
    return [
        'displayName' => get_class($command),
        'data' => [
            'commandName' => $command,
            'command' => $command,
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

it('does not inject jobs opting out through the tenantAware property', function () {
    $this->createTenant('acme');
    Tenant::identify('acme');

    $job = new class () {
        public bool $tenantAware = false;
    };

    expect($this->injector->shouldInjectTenant(payloadFor($job)))->toBeFalse();
});

it('applies job exclusions to queued mailables', function () {
    config()->set('easy-multitenancy.queue.excluded_jobs', [SampleMailable::class]);

    $this->createTenant('acme');
    Tenant::identify('acme');

    $payload = payloadFor(new SendQueuedMailable(new SampleMailable()));

    expect($this->injector->shouldInjectTenant($payload))->toBeFalse();
});
