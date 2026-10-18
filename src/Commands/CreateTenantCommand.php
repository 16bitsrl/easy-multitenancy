<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create {name? : The tenant identifier} {--no-user : Skip creating a default user}';

    protected $description = 'Create a new tenant database';

    public const INVALID_NAME = 'The tenant name must be 2-63 characters of lowercase letters, numbers and single hyphens, not starting or ending with a hyphen.';

    public function handle(): int
    {
        $name = $this->argument('name') ?? text(
            label: 'What is the tenant name/identifier?',
            placeholder: 'e.g., acme, contoso, client-name',
            required: true,
            validate: fn (string $value) => match (true) {
                ! Tenant::isValid($value) => self::INVALID_NAME,
                Tenant::isReserved($value) => "The tenant name '{$value}' is reserved by the application.",
                Tenant::exists($value) => "Tenant '{$value}' already exists.",
                default => null
            }
        );

        $name = strtolower(trim((string) $name));
        $database = Tenant::getDatabasePath($name);

        if ($database === null) {
            $this->error(self::INVALID_NAME);

            return self::FAILURE;
        }

        if (Tenant::isReserved($name)) {
            $this->error("The tenant name '{$name}' is reserved by the application.");

            return self::FAILURE;
        }

        if (Tenant::exists($name)) {
            $this->error("Tenant '{$name}' already exists.");

            return self::FAILURE;
        }

        $email = null;
        $password = null;

        if (!$this->option('no-user')) {
            $email = text(
                label: 'Admin email address',
                placeholder: 'admin@' . $name . '.com',
                default: 'admin@' . $name . '.local',
                required: true,
                validate: fn (string $value) => match (true) {
                    !filter_var($value, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
                    default => null
                }
            );

            $passwordInput = password(
                label: 'Admin password (leave blank to generate)',
                placeholder: 'Leave blank to generate a random password',
                required: false,
            );

            $password = $passwordInput !== '' ? $passwordInput : null;
        }

        $path = dirname($database);

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            $this->info("Created directory: {$path}");
        }

        // Create the file exclusively: if another process created the same
        // tenant in the meantime, stop instead of sharing (or later deleting)
        // its database.
        $handle = @fopen($database, 'x');

        if ($handle === false) {
            $this->error("Tenant '{$name}' already exists.");

            return self::FAILURE;
        }

        fclose($handle);

        try {
            $this->info("Created database: {$database}");

            $this->info("Running migrations for tenant '{$name}'...");
            $exitCode = Artisan::call('tenant:migrate', ['tenant' => $name]);
            $this->line(Artisan::output());

            if ($exitCode !== self::SUCCESS) {
                throw new \RuntimeException("Migrations failed for tenant '{$name}'.");
            }

            $this->runConfiguredSeeders($name);

            if ($email !== null) {
                $credentials = $this->createDefaultUser($name, $email, $password);

                if ($credentials) {
                    $this->newLine();
                    $this->components->info('Tenant created successfully!');
                    $this->newLine();
                    $this->components->twoColumnDetail('Tenant', $name);
                    if (Route::has('login')) {
                        $this->components->twoColumnDetail('Login URL', route('login', [config('easy-multitenancy.routes.parameter', 'tenant') => $name]));
                    }
                    $this->components->twoColumnDetail('Email', $credentials['email']);
                    $this->components->twoColumnDetail('Password', $credentials['password']);
                    $this->newLine();
                }
            } else {
                $this->info("Tenant '{$name}' created successfully!");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Tenant creation failed: ' . $e->getMessage());

            if (file_exists($database)) {
                unlink($database);
                $this->info('Rolled back: Deleted database file');
            }

            Tenant::forget();

            return self::FAILURE;
        }
    }

    /** @return array<string, string> */
    protected function createDefaultUser(string $tenant, string $email, ?string $password = null): ?array
    {
        $this->newLine();
        $this->components->info('Creating admin user...');

        $finalPassword = $password !== null ? $password : Str::random(12);

        try {
            Tenant::identify($tenant);

            $userClass = $this->getUserModelClass();

            if (!$userClass) {
                $this->warn('User model not found. Skipping user creation.');

                return null;
            }

            (new $userClass())->forceFill([
                'name' => ucfirst($tenant),
                'email' => $email,
                'password' => Hash::make($finalPassword),
                'email_verified_at' => now(),
            ])->save();

            return [
                'email' => $email,
                'password' => $finalPassword,
            ];
        } catch (\Exception $e) {
            $this->error('Could not create default user: ' . $e->getMessage());

            return null;
        } finally {
            Tenant::forget();
        }
    }

    protected function runConfiguredSeeders(string $tenant): void
    {
        $seeders = config('easy-multitenancy.seeders.on_create', []);

        if (empty($seeders)) {
            return;
        }

        $this->info('Running configured seeders...');

        Tenant::identify($tenant);

        try {
            foreach ($seeders as $seederClass) {
                if (! class_exists($seederClass)) {
                    $this->warn("Seeder class not found: {$seederClass}");

                    continue;
                }

                $this->line('  - '.class_basename($seederClass));

                $exitCode = Artisan::call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                ]);

                $this->line(Artisan::output());

                if ($exitCode !== self::SUCCESS) {
                    throw new \RuntimeException("Seeder {$seederClass} failed.");
                }
            }
        } finally {
            Tenant::forget();
        }
    }

    /** @return class-string<Model>|null */
    protected function getUserModelClass(): ?string
    {
        // The model the app authenticates users with.
        $class = config('auth.providers.users.model');

        return is_string($class) && is_subclass_of($class, Model::class) ? $class : null;
    }
}
