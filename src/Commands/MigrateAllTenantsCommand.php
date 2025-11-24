<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateAllTenantsCommand extends Command
{
    protected $signature = 'tenant:migrate-all {--fresh : Drop all tables and re-run migrations} {--seed : Seed the database after migration}';

    protected $description = 'Run migrations for all tenants';

    public function handle(): int
    {
        $tenants = Tenant::all();

        if (empty($tenants)) {
            $this->info('No tenants found.');

            return self::SUCCESS;
        }

        $this->info('Migrating '.count($tenants).' tenant(s)...');

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("Migrating tenant: {$tenant}");

            $options = ['tenant' => $tenant];

            if ($this->option('fresh')) {
                $options['--fresh'] = true;
            }

            if ($this->option('seed')) {
                $options['--seed'] = true;
            }

            Artisan::call('tenant:migrate', $options);
            $this->line(Artisan::output());
        }

        $this->info('All tenants migrated successfully!');

        return self::SUCCESS;
    }
}
