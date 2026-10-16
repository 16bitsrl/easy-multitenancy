<?php

namespace Bit16\EasyMultitenancy\Tests\Fixtures;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Records the tenant active while the job body runs.
 */
class RecordTenantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var array<int, string|null> */
    public static array $seen = [];

    public function handle(): void
    {
        static::$seen[] = Tenant::current();

        if (Tenant::current()) {
            Post::create(['title' => 'from-job']);
        }
    }
}
