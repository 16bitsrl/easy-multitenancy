<?php

namespace Bit16\EasyMultitenancy\Traits;

use Bit16\EasyMultitenancy\Exceptions\TenantNotFoundException;
use Bit16\EasyMultitenancy\Facades\Tenant;

trait TenantAware
{
    protected ?string $tenantId = null;

    public function __construct()
    {
        $this->tenantId = Tenant::current();
    }

    public function middleware(): array
    {
        return [
            function ($job, $next) {
                if ($this->tenantId) {
                    if (! Tenant::exists($this->tenantId)) {
                        \Log::warning('Job attempted to run for non-existent tenant', [
                            'tenant' => $this->tenantId,
                            'job' => get_class($job),
                        ]);

                        return;
                    }

                    try {
                        Tenant::identify($this->tenantId);
                    } catch (TenantNotFoundException $e) {
                        \Log::error('Failed to identify tenant for job', [
                            'tenant' => $this->tenantId,
                            'job' => get_class($job),
                            'error' => $e->getMessage(),
                        ]);

                        return;
                    }
                }

                $next($job);

                if ($this->tenantId) {
                    Tenant::forget();
                }
            },
        ];
    }
}
