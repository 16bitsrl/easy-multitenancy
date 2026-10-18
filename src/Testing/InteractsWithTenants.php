<?php

namespace Bit16\EasyMultitenancy\Testing;

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Tenant helpers for an application's tests.
 *
 * Tenant databases live in a temporary folder for the duration of each test,
 * so the application's real tenants are never touched. They are copied from
 * a template migrated (and seeded with `seeders.on_create`) once per process.
 */
trait InteractsWithTenants
{
    /** @var array<string, string> */
    private static array $tenantTemplates = [];

    protected ?string $tenantTestPath = null;

    protected function setUpInteractsWithTenants(): void
    {
        $this->tenantTestPath = sys_get_temp_dir().'/easy-multitenancy-'.getmypid().'-'.spl_object_id($this);

        File::ensureDirectoryExists($this->tenantTestPath);
        config()->set('easy-multitenancy.database.path', $this->tenantTestPath);
    }

    protected function tearDownInteractsWithTenants(): void
    {
        Tenant::forget();

        if ($this->tenantTestPath !== null) {
            File::deleteDirectory($this->tenantTestPath);
        }
    }

    /**
     * Create a migrated tenant database for this test.
     */
    protected function createTenant(string $tenant): void
    {
        $database = Tenant::getDatabasePath($tenant);

        if ($database === null) {
            throw new RuntimeException("'{$tenant}' is not a valid tenant identifier.");
        }

        copy($this->tenantTemplate(), $database);
    }

    /**
     * Make the tenant current for the rest of the test (creating it when
     * needed): models use its database and route() includes its segment.
     * Requests made by the test run in the tenant of their own URL.
     */
    protected function actingAsTenant(string $tenant): static
    {
        if (! Tenant::exists($tenant)) {
            $this->createTenant($tenant);
        }

        Tenant::identify($tenant);
        URL::defaults([config('easy-multitenancy.routes.parameter', 'tenant') => $tenant]);

        return $this;
    }

    /**
     * Each request starts without a tenant, as in production, so its URL alone
     * decides the tenant. The tenant the test acts as is current again after it.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $tenant = Tenant::current();

        Tenant::forget();

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            if ($tenant !== null) {
                Tenant::identify($tenant);
            }
        }
    }

    private function tenantTemplate(): string
    {
        $seeders = config('easy-multitenancy.seeders.on_create', []);
        $key = md5(serialize($seeders));

        if (isset(self::$tenantTemplates[$key]) && file_exists(self::$tenantTemplates[$key])) {
            return self::$tenantTemplates[$key];
        }

        $folder = sys_get_temp_dir().'/easy-multitenancy-template-'.getmypid().'-'.$key;
        $path = config('easy-multitenancy.database.path');

        File::ensureDirectoryExists($folder);
        config()->set('easy-multitenancy.database.path', $folder);

        try {
            $template = (string) Tenant::getDatabasePath('template');
            touch($template);

            if (Artisan::call('tenant:migrate', ['tenant' => 'template']) !== 0) {
                throw new RuntimeException('Could not migrate the tenant template: '.Artisan::output());
            }

            if ($seeders !== []) {
                Tenant::identify('template');

                try {
                    foreach ($seeders as $seeder) {
                        Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                    }
                } finally {
                    Tenant::forget();
                }
            }
        } finally {
            config()->set('easy-multitenancy.database.path', $path);
        }

        register_shutdown_function(function () use ($template, $folder) {
            @unlink($template);
            @rmdir($folder);
        });

        return self::$tenantTemplates[$key] = $template;
    }
}
