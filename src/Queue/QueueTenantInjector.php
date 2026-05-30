<?php

namespace Bit16\EasyMultitenancy\Queue;

use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueueTenantInjector
{
    /**
     * Determine if the current tenant should be injected into the job payload.
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

        if (! isset($payload['data']['command'])) {
            return false;
        }

        try {
            $command = unserialize($payload['data']['command']);
        } catch (\Throwable $e) {
            Log::warning('Failed to unserialize job command for tenant injection', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $jobClass = get_class($command);

        // Exact class exclusion
        if (in_array($jobClass, config('easy-multitenancy.queue.excluded_jobs', []))) {
            return false;
        }

        // Pattern-based exclusion
        foreach (config('easy-multitenancy.queue.excluded_patterns', []) as $pattern) {
            if (Str::is($pattern, $jobClass)) {
                return false;
            }
        }

        // Interface-based exclusion
        $excludedInterface = config('easy-multitenancy.queue.exclusion_interface');
        if ($excludedInterface && $command instanceof $excludedInterface) {
            return false;
        }

        // Property-based opt-out
        if (property_exists($command, 'tenantAware') && $command->tenantAware === false) {
            return false;
        }

        return true;
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
     * Determine if a tenant context should be restored for the job.
     *
     * @param  array<string, mixed>  $payload
     */
    public function shouldRestoreTenant(array $payload): bool
    {
        if (! config('easy-multitenancy.queue.tenant_aware', true)) {
            return false;
        }

        if (empty($payload['tenant_id'])) {
            return false;
        }

        $strictMode = config('easy-multitenancy.queue.strict_mode', true);
        if ($strictMode && ! Tenant::exists($payload['tenant_id'])) {
            Log::warning('Job queued for non-existent tenant', [
                'tenant_id' => $payload['tenant_id'],
                'job' => $payload['displayName'] ?? 'unknown',
            ]);

            return false;
        }

        return true;
    }

    /**
     * Restore the tenant context from the job payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function restoreTenant(array $payload): void
    {
        $tenantId = $payload['tenant_id'];

        try {
            Tenant::identify($tenantId);

            if (config('easy-multitenancy.queue.debug_logging', false)) {
                Log::debug('Restored tenant context for job', [
                    'tenant_id' => $tenantId,
                    'job' => $payload['displayName'] ?? 'unknown',
                ]);
            }
        } catch (TenantNotFoundException $e) {
            Log::error('Failed to restore tenant for job', [
                'tenant_id' => $tenantId,
                'job' => $payload['displayName'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            if (config('easy-multitenancy.queue.strict_mode', true)) {
                throw $e;
            }
        }
    }

    /**
     * Clean up the tenant context after a job finishes.
     */
    public function cleanupTenant(): void
    {
        if (Tenant::current()) {
            if (config('easy-multitenancy.queue.debug_logging', false)) {
                Log::debug('Cleaned up tenant context after job', [
                    'tenant_id' => Tenant::current(),
                ]);
            }

            Tenant::forget();
        }
    }
}
