<?php

use Illuminate\Support\Facades\View;

/*
 * tenant:upgrade-check points at the code an app must change when moving to
 * v1, and exits with a failure code while anything is left to fix.
 */

beforeEach(function () {
    $this->scanPath = $this->tenantPath.'/app';
    mkdir($this->scanPath, 0755, true);
});

it('passes when there is nothing to upgrade', function () {
    app('router')->getRoutes()->getByName('legacy')->setAction(['uses' => fn ($item) => $item]);

    $this->artisan('tenant:upgrade-check', ['--path' => [$this->scanPath]])
        ->expectsOutputToContain('Nothing to upgrade')
        ->assertExitCode(0);
});

it('flags tenant routes whose action still expects the tenant argument', function () {
    $this->artisan('tenant:upgrade-check', ['--path' => [$this->scanPath]])
        ->expectsOutputToContain('{tenant}/legacy/{item} → closure in')
        ->assertExitCode(1);
});

it('flags code that reads the tenant from the route or uses removed apis', function () {
    file_put_contents($this->scanPath.'/Legacy.php', <<<'PHP'
        <?php
        $tenant = $request->route('tenant');
        $disk = Storage::disk('tenant');
        app(QueueTenantInjector::class)->cleanupTenant();
        PHP);

    app('router')->getRoutes()->getByName('legacy')->setAction(['uses' => fn ($item) => $item]);

    $this->artisan('tenant:upgrade-check', ['--path' => [$this->scanPath]])
        ->expectsOutputToContain('Legacy.php:2')
        ->expectsOutputToContain('Legacy.php:3')
        ->expectsOutputToContain('Legacy.php:4')
        ->assertExitCode(1);
});

it('flags tenant databases whose name is no longer valid', function () {
    touch($this->tenantPath.'/Old_Name.sqlite');
    app('router')->getRoutes()->getByName('legacy')->setAction(['uses' => fn ($item) => $item]);

    $this->artisan('tenant:upgrade-check', ['--path' => [$this->scanPath]])
        ->expectsOutputToContain('Old_Name')
        ->assertExitCode(1);
});

it('flags files left in the previous tenant storage folder', function () {
    $this->createTenant('acme');
    config()->set('filesystems.disks.local.root', $this->tenantPath.'/storage/app/private');
    $this->app->useStoragePath($this->tenantPath.'/storage');
    mkdir($this->tenantPath.'/storage/app/tenants/acme', 0755, true);
    touch($this->tenantPath.'/storage/app/tenants/acme/report.pdf');
    app('router')->getRoutes()->getByName('legacy')->setAction(['uses' => fn ($item) => $item]);

    $this->artisan('tenant:upgrade-check', ['--path' => [$this->scanPath]])
        ->expectsOutputToContain(storage_path('app/tenants')." to {$this->tenantPath}/storage/app/private/tenants (e.g. rsync -a")
        ->assertExitCode(1);
});

it('flags published views that predate the translatable texts', function () {
    app('router')->getRoutes()->getByName('legacy')->setAction(['uses' => fn ($item) => $item]);
    $published = $this->tenantPath.'/views/vendor/easy-multitenancy';
    mkdir($published, 0755, true);
    file_put_contents($published.'/central-home.blade.php', '<h1>Tenant recenti</h1>');
    View::prependNamespace('easy-multitenancy', $published);

    $this->artisan('tenant:upgrade-check', ['--path' => [$this->scanPath]])
        ->expectsOutputToContain('central-home.blade.php has fixed texts')
        ->assertExitCode(1);
});

it('accepts published views that use the translations', function () {
    app('router')->getRoutes()->getByName('legacy')->setAction(['uses' => fn ($item) => $item]);
    $published = $this->tenantPath.'/views/vendor/easy-multitenancy';
    mkdir($published, 0755, true);
    file_put_contents($published.'/central-home.blade.php', "<h1>{{ __('easy-multitenancy::central-home.recent') }}</h1>");
    View::prependNamespace('easy-multitenancy', $published);

    $this->artisan('tenant:upgrade-check', ['--path' => [$this->scanPath]])
        ->expectsOutputToContain('Nothing to upgrade')
        ->assertExitCode(0);
});
