<?php

namespace Bit16\EasyMultitenancy\Tests\Fixtures;

use Bit16\EasyMultitenancy\Contracts\GlobalJob;
use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RecordGlobalJob implements GlobalJob, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @var array<int, string|null> */
    public static array $seen = [];

    public function handle(): void
    {
        static::$seen[] = Tenant::current();
    }
}
