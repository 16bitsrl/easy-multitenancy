<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedAllTenantsCommand extends Command
{
    protected $signature = 'tenant:seed-all {--class= : The class name of the seeder}';

    protected $description = 'Seed all tenant databases';

    public function handle(): int
    {
        $tenants = Tenant::all();

        if (empty($tenants)) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        $this->info('Seeding all tenants...');

        foreach ($tenants as $tenant) {
            Tenant::identify($tenant);

            $this->line("Seeding: {$tenant}");

            $options = ['--force' => true];

            if ($class = $this->option('class')) {
                $options['--class'] = $class;
            }

            Artisan::call('db:seed', $options);

            Tenant::forget();
        }

        $this->info('All tenants seeded successfully.');

        return self::SUCCESS;
    }
}
