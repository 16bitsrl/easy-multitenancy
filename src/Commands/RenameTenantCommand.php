<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;

class RenameTenantCommand extends Command
{
    protected $signature = 'tenant:rename
        {from : The current tenant identifier}
        {to : The new tenant identifier}
        {--force : Do not ask for confirmation}';

    protected $description = 'Rename a tenant, with its database and files';

    public function handle(): int
    {
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');

        $error = match (true) {
            ! Tenant::exists($from) => "Tenant '{$from}' does not exist.",
            ! Tenant::isValid($to) => CreateTenantCommand::INVALID_NAME,
            Tenant::isReserved($to) => "The tenant name '{$to}' is reserved by the application.",
            Tenant::exists($to) => "Tenant '{$to}' already exists.",
            default => null,
        };

        if ($error !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Rename tenant '{$from}' to '{$to}'? Its URLs change and its users have to log in again.")) {
            return self::FAILURE;
        }

        Tenant::rename($from, $to);

        $this->components->info("Tenant '{$from}' renamed to '{$to}'.");
        $this->line('Jobs already queued for the old name will fail: let the queue drain before renaming, or retry them by hand.');

        return self::SUCCESS;
    }
}
