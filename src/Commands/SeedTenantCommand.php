<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;

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

        try {
            $this->info("Seeding tenant: {$tenant}");

            $options = ['--force' => true];

            if ($class = $this->option('class')) {
                $options['--class'] = $class;
            }

            $exitCode = $this->call('db:seed', $options);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Seeding failed for tenant '{$tenant}'.");

                return self::FAILURE;
            }
        } finally {
            Tenant::forget();
        }

        $this->info("Tenant '{$tenant}' seeded successfully.");

        return self::SUCCESS;
    }
}
