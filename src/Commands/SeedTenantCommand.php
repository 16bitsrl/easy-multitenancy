<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedTenantCommand extends Command
{
    protected $signature = 'tenant:seed {tenant} {--class= : The class name of the seeder}';

    protected $description = 'Seed a specific tenant database';

    public function handle(): int
    {
        $tenant = $this->argument('tenant');

        if (! Tenant::exists($tenant)) {
            $this->error("Tenant '{$tenant}' does not exist.");

            return self::FAILURE;
        }

        Tenant::identify($tenant);

        $this->info("Seeding tenant: {$tenant}");

        $options = ['--force' => true];

        if ($class = $this->option('class')) {
            $options['--class'] = $class;
        }

        Artisan::call('db:seed', $options);

        $this->info("Tenant '{$tenant}' seeded successfully.");

        return self::SUCCESS;
    }
}
