<?php

namespace Bit16\EasyMultitenancy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantNotFound
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $tenant
    ) {
    }
}
