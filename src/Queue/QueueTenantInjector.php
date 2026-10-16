<?php

namespace Bit16\EasyMultitenancy\Queue;

use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueueTenantInjector
{
    /**
     * Tenant that was active before each job in progress started, keyed by the
     * job instance, so it can be restored once the job is done. This keeps a
     * worker from leaking a tenant into the next job, and a sync job from
     * dropping the tenant of the request that dispatched it.
     *
     * @var array<int, string|null>
     */
    protected array $previousTenants = [];

    /**
     * Determine if the current tenant should be injected into the job payload.
     *
     * Runs from the queue's payload hook, before the job is serialized: the
     * payload's "command" is still the job object at this point.
     *
     * @param  array<string, mixed>  $payload
     */
    public function shouldInjectTenant(array $payload): bool
    {
        if (! config('easy-multitenancy.queue.tenant_aware', true)) {
            return false;
        }

        if (! Tenant::current()) {
            return false;
        }

        $command = $payload['data']['command'] ?? null;

        if (! is_object($command)) {
            return false;
        }

        $excludedInterface = config('easy-multitenancy.queue.exclusion_interface');

        foreach ($this->jobTargets($command) as $target) {
            $class = is_object($target) ? get_class($target) : $target;

            if ($this->isJobClassExcluded($class)) {
                return false;
            }

            // Interface-based exclusion
            if ($excludedInterface && is_a($target, $excludedInterface, true)) {
                return false;
            }

            // Property-based opt-out
            if (is_object($target) && property_exists($target, 'tenantAware') && $target->tenantAware === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * The job itself plus what it wraps: queued mailables, notifications,
     * listeners and broadcast events are pushed inside framework job classes,
     * so exclusions must also apply to the wrapped class.
     *
     * @return array<int, object|string>
     */
    protected function jobTargets(object $command): array
    {
        $inner = match (true) {
            $command instanceof SendQueuedMailable => $command->mailable,
            $command instanceof SendQueuedNotifications => $command->notification,
            $command instanceof CallQueuedListener => $command->class,
            $command instanceof BroadcastEvent => $command->event,
            default => null,
        };

        return $inner === null ? [$command] : [$command, $inner];
    }

    protected function isJobClassExcluded(string $jobClass): bool
    {
        if (in_array($jobClass, config('easy-multitenancy.queue.excluded_jobs', []), true)) {
            return true;
        }

        foreach (config('easy-multitenancy.queue.excluded_patterns', []) as $pattern) {
            if (is_string($pattern) && Str::is($pattern, $jobClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inject the current tenant id into the job payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function injectTenant(array $payload): array
    {
        $tenantId = Tenant::current();
        $payload['tenant_id'] = $tenantId;

        if (config('easy-multitenancy.queue.debug_logging', false)) {
            Log::debug('Injected tenant into job payload', [
                'tenant_id' => $tenantId,
                'job' => $payload['displayName'] ?? 'unknown',
                'queue' => $payload['queue'] ?? 'default',
            ]);
        }

        return $payload;
    }

    /**
     * Set up the tenant context before a job runs: the job's tenant when it
     * carries one, the central context otherwise.
     *
     * In strict mode a job whose tenant no longer exists is failed before its
     * body runs; otherwise it runs in the central context.
     *
     * @throws TenantNotFoundException
     */
    public function beginJob(Job $job): void
    {
        $this->previousTenants[spl_object_id($job)] = Tenant::current();

        $payload = $job->payload();
        $tenantId = config('easy-multitenancy.queue.tenant_aware', true)
            ? ($payload['tenant_id'] ?? null)
            : null;

        if (empty($tenantId)) {
            $this->leaveTenant();

            return;
        }

        if (! Tenant::exists($tenantId)) {
            Log::warning('Job queued for non-existent tenant', [
                'tenant_id' => $tenantId,
                'job' => $payload['displayName'] ?? 'unknown',
            ]);

            $this->leaveTenant();

            if (config('easy-multitenancy.queue.strict_mode', true)) {
                $exception = new TenantNotFoundException($tenantId);

                $job->fail($exception);

                throw $exception;
            }

            return;
        }

        if (Tenant::current() !== $tenantId) {
            Tenant::identify($tenantId);
        }

        if (config('easy-multitenancy.queue.debug_logging', false)) {
            Log::debug('Restored tenant context for job', [
                'tenant_id' => $tenantId,
                'job' => $payload['displayName'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Restore the tenant context that was active before the job started. Safe
     * to call for every outcome event of the same job: only the first call
     * does anything.
     */
    public function endJob(Job $job): void
    {
        $key = spl_object_id($job);

        if (! array_key_exists($key, $this->previousTenants)) {
            return;
        }

        $previous = $this->previousTenants[$key];
        unset($this->previousTenants[$key]);

        if (Tenant::current() === $previous) {
            return;
        }

        if ($previous === null) {
            $this->leaveTenant();
        } else {
            Tenant::identify($previous);
        }
    }

    protected function leaveTenant(): void
    {
        if (! Tenant::current()) {
            return;
        }

        if (config('easy-multitenancy.queue.debug_logging', false)) {
            Log::debug('Cleaned up tenant context around job', [
                'tenant_id' => Tenant::current(),
            ]);
        }

        Tenant::forget();
    }
}
