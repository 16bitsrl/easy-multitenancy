<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;

class DeleteTenantCommand extends Command
{
    protected $signature = 'tenant:delete
        {tenant : The tenant identifier}
        {--files : Also delete the tenant folder on every tenant-scoped disk}
        {--no-backup : Do not keep a copy of the database}
        {--force : Do not ask for confirmation}';

    protected $description = 'Delete a tenant, keeping a backup of its database';

    public function handle(): int
    {
        $tenant = (string) $this->argument('tenant');

        if (! Tenant::exists($tenant)) {
            $this->error("Tenant '{$tenant}' does not exist.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete tenant '{$tenant}'? Its users lose access immediately.")) {
            return self::FAILURE;
        }

        if (! $this->option('no-backup')) {
            $this->line('Backup: '.Tenant::backup($tenant));
        }

        Tenant::delete($tenant, (bool) $this->option('files'));

        $this->components->info("Tenant '{$tenant}' deleted.");

        if (! $this->option('files')) {
            $this->line("Files of the tenant were kept in the tenant folder of each tenant-scoped disk (use --files to delete them with the tenant).");
        }

        return self::SUCCESS;
    }
}
