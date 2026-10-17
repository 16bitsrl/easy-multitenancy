<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Illuminate\Support\Facades\Storage;

/*
 * Every disk listed in storage.disks is scoped to the current tenant: files
 * land in a tenant folder (or object prefix) of that same disk, and the
 * default disk keeps pointing where the app configured it.
 */

beforeEach(function () {
    config()->set('filesystems.disks.local.root', $this->tenantPath.'/storage/private');
    config()->set('filesystems.disks.public.root', $this->tenantPath.'/storage/public');
    config()->set('filesystems.disks.public.url', 'http://localhost/storage');
    config()->set('filesystems.disks.s3', ['driver' => 's3', 'bucket' => 'files', 'root' => 'uploads']);
    config()->set('easy-multitenancy.storage.disks', ['local', 'public', 's3']);

    $this->createTenant('acme');
    $this->createTenant('contoso');
});

it('stores files of each tenant in its own folder of the same disk', function () {
    Tenant::identify('acme');
    Storage::disk('local')->put('report.txt', 'acme');
    Tenant::forget();

    expect(file_get_contents($this->tenantPath.'/storage/private/tenants/acme/report.txt'))->toBe('acme');

    Tenant::identify('contoso');
    expect(Storage::disk('local')->exists('report.txt'))->toBeFalse();
});

it('keeps the default disk unchanged', function () {
    $default = config('filesystems.default');

    Tenant::identify('acme');

    expect(config('filesystems.default'))->toBe($default)
        ->and(config('filesystems.disks.tenant'))->toBeNull();
});

it('generates public urls inside the tenant folder', function () {
    Tenant::identify('acme');

    expect(Storage::disk('public')->url('logo.png'))->toBe('http://localhost/storage/tenants/acme/logo.png');
});

it('scopes remote disks with an object prefix', function () {
    Tenant::identify('acme');

    expect(config('filesystems.disks.s3.root'))->toBe('uploads/tenants/acme');
});

it('restores every disk on forget', function () {
    Tenant::identify('acme');
    Tenant::forget();

    expect(config('filesystems.disks.local.root'))->toBe($this->tenantPath.'/storage/private')
        ->and(config('filesystems.disks.s3.root'))->toBe('uploads')
        ->and(Storage::disk('public')->url('logo.png'))->toBe('http://localhost/storage/logo.png');
});
