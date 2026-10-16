<?php

namespace Bit16\EasyMultitenancy\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Always throws, so the worker releases it for another attempt.
 */
class FailingTenantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function handle(): void
    {
        throw new \RuntimeException('boom');
    }
}
