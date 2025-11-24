<?php

namespace Bit16\EasyMultitenancy\Commands;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create {name? : The tenant identifier} {--no-user : Skip creating a default user}';

    protected $description = 'Create a new tenant database';

    public function handle(): int
    {
        $name = $this->argument('name') ?? text(
            label: 'What is the tenant name/identifier?',
            placeholder: 'e.g., acme, contoso, client-name',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 2 => 'The tenant name must be at least 2 characters.',
                strlen($value) > 255 => 'The tenant name is too long (max 255 characters).',
                !preg_match('/^[a-z0-9-]+$/', $value) => 'The tenant name must contain only lowercase letters, numbers, and hyphens.',
                str_contains($value, '..') => 'The tenant name contains invalid characters.',
                Tenant::exists($value) => "Tenant '{$value}' already exists.",
                default => null
            }
        );

        $name = $this->sanitizeTenantName($name);

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

            $passwordInput = text(
                label: 'Admin password (leave blank to generate)',
                placeholder: 'Leave blank to generate a random password',
                required: false,
            );

            $password = $passwordInput !== '' ? $passwordInput : null;
        }

        $database = Tenant::getDatabasePath($name);
        $path = dirname($database);

        try {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->info("Created directory: {$path}");
            }

            touch($database);
            $this->info("Created database: {$database}");

            $this->info("Running migrations for tenant '{$name}'...");
            Artisan::call('tenant:migrate', ['tenant' => $name]);
            $this->line(Artisan::output());

            $this->runConfiguredSeeders($name);

            if (!$this->option('no-user')) {
                $credentials = $this->createDefaultUser($name, $email, $password);

                if ($credentials) {
                    $this->newLine();
                    $this->components->info('Tenant created successfully!');
                    $this->newLine();
                    $this->components->twoColumnDetail('Tenant', $name);
                    $this->components->twoColumnDetail('Login URL', route('login', ['tenant' => $name]));
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

            $user = new $userClass();
            $user->name = ucfirst($tenant);
            $user->email = $email;
            $user->password = Hash::make($finalPassword);
            $user->email_verified_at = now();
            $user->save();

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

        foreach ($seeders as $seederClass) {
            if (!class_exists($seederClass)) {
                $this->warn("Seeder class not found: {$seederClass}");

                continue;
            }

            $this->line('  - ' . class_basename($seederClass));

            try {
                Artisan::call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                ]);
            } catch (\Exception $e) {
                $this->error('  Failed: ' . $e->getMessage());
            }
        }

        Tenant::forget();
    }

    protected function getUserModelClass(): ?string
    {
        $possibleClasses = [
            'App\\Models\\User',
            'App\\User',
        ];

        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    protected function sanitizeTenantName(string $name): string
    {
        $name = trim($name);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9\-]/', '', $name);
        $name = preg_replace('/\.\.+/', '', $name);
        $name = str_replace(['/', '\\', "\0"], '', $name);

        if (empty($name)) {
            throw new \InvalidArgumentException('Tenant name cannot be empty after sanitization');
        }

        return $name;
    }
}
