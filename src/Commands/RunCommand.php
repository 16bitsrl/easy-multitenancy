<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs an Artisan command inside each tenant, e.g. from the scheduler:
 * Schedule::command('tenant:run "reports:send --daily"')->daily().
 */
class RunCommand extends Command
{
    protected $signature = 'tenant:run
        {artisan : The command to run, with its arguments, in quotes}
        {--tenant=* : Run only in these tenants (default: all)}';

    protected $description = 'Run an Artisan command in every tenant';

    public function handle(): int
    {
        $tenants = array_values(array_filter((array) $this->option('tenant'), fn ($tenant) => is_string($tenant) && $tenant !== '')) ?: Tenant::all();

        foreach ($tenants as $tenant) {
            if (! Tenant::exists($tenant)) {
                $this->error("Tenant '{$tenant}' does not exist.");

                return self::FAILURE;
            }
        }

        $failed = [];

        foreach ($tenants as $tenant) {
            $this->components->info("Running in tenant '{$tenant}'");

            Tenant::identify($tenant);

            try {
                $exitCode = Artisan::call($this->argument('artisan'), [], $this->output);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $exitCode = self::FAILURE;
            } finally {
                Tenant::forget();
            }

            if ($exitCode !== self::SUCCESS) {
                $failed[] = $tenant;
            }
        }

        if ($failed !== []) {
            $this->components->error('Failed in: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
