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

        $this->info("Running migrations for tenant '{$name}'...");

        if ($this->option('fresh')) {
            Artisan::call('migrate:fresh', [
                '--database' => config('easy-multitenancy.database.connection', 'tenant'),
                '--force' => true,
            ]);
        } else {
            Artisan::call('migrate', [
                '--database' => config('easy-multitenancy.database.connection', 'tenant'),
                '--force' => true,
            ]);
        }

        $this->line(Artisan::output());

        if ($this->option('seed')) {
            $this->info("Seeding database for tenant '{$name}'...");
            Artisan::call('db:seed', [
                '--database' => config('easy-multitenancy.database.connection', 'tenant'),
                '--force' => true,
            ]);
            $this->line(Artisan::output());
        }

        Tenant::forget();

        $this->info("Migration completed for tenant '{$name}'!");

        return self::SUCCESS;
    }
}
