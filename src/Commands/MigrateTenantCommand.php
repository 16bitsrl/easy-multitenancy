<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateTenantCommand extends Command
{
    protected $signature = 'tenant:migrate {tenant : The tenant identifier} {--fresh : Drop all tables and re-run migrations} {--seed : Seed the database after migration}';

    protected $description = 'Run migrations for a specific tenant';

    public function handle(): int
    {
        $name = $this->argument('tenant');

        if (! Tenant::exists($name)) {
            $this->error("Tenant '{$name}' does not exist.");

            return self::FAILURE;
        }

        Tenant::identify($name);

        try {
            $this->info("Running migrations for tenant '{$name}'...");

            $exitCode = Artisan::call($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
                '--database' => config('easy-multitenancy.database.connection', 'tenant'),
                '--force' => true,
            ]);

            $this->line(Artisan::output());

            if ($exitCode !== self::SUCCESS) {
                $this->error("Migration failed for tenant '{$name}'.");

                return self::FAILURE;
            }

            if ($this->option('seed')) {
                $this->info("Seeding database for tenant '{$name}'...");

                $exitCode = Artisan::call('db:seed', [
                    '--database' => config('easy-multitenancy.database.connection', 'tenant'),
                    '--force' => true,
                ]);

                $this->line(Artisan::output());

                if ($exitCode !== self::SUCCESS) {
                    $this->error("Seeding failed for tenant '{$name}'.");

                    return self::FAILURE;
                }
            }
        } finally {
            Tenant::forget();
        }

        $this->info("Migration completed for tenant '{$name}'!");

        return self::SUCCESS;
    }
}
