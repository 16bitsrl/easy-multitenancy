<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;

class ListTenantsCommand extends Command
{
    protected $signature = 'tenant:list';

    protected $description = 'List all tenants';

    public function handle(): int
    {
        $tenants = Tenant::all();

        if (empty($tenants)) {
            $this->info('No tenants found.');

            return self::SUCCESS;
        }

        $this->table(['Tenant', 'Database'], array_map(function ($tenant) {
            return [$tenant, Tenant::getDatabasePath($tenant)];
        }, $tenants));

        return self::SUCCESS;
    }
}
