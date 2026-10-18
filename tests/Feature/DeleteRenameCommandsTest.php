<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Tests\Fixtures\Post;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('filesystems.disks.local.root', $this->tenantPath.'/storage/private');
    config()->set('filesystems.disks.public.root', $this->tenantPath.'/storage/public');
    config()->set('easy-multitenancy.storage.disks', ['local', 'public']);

    $this->createTenant('acme');

    Tenant::identify('acme');
    Post::create(['title' => 'Kept']);
    Storage::disk('local')->put('invoice.pdf', 'pdf');
    Tenant::forget();
});

function backupsOf(string $tenantPath): array
{
    return glob($tenantPath.'/backups/acme-*.sqlite') ?: [];
}

function titlesIn(string $database): array
{
    return (new PDO('sqlite:'.$database))->query('select title from posts')->fetchAll(PDO::FETCH_COLUMN);
}

it('deletes a tenant only after confirmation, keeping a backup of its database', function () {
    $this->artisan('tenant:delete', ['tenant' => 'acme'])
        ->expectsConfirmation("Delete tenant 'acme'? Its users lose access immediately.", 'no')
        ->assertExitCode(1);

    expect(Tenant::exists('acme'))->toBeTrue();

    $this->artisan('tenant:delete', ['tenant' => 'acme'])
        ->expectsConfirmation("Delete tenant 'acme'? Its users lose access immediately.", 'yes')
        ->expectsOutputToContain('Backup:')
        ->assertExitCode(0);

    expect(Tenant::exists('acme'))->toBeFalse()
        ->and(backupsOf($this->tenantPath))->toHaveCount(1)
        ->and(titlesIn(backupsOf($this->tenantPath)[0]))->toBe(['Kept'])
        ->and(Tenant::all())->toBe([]);
});

it('keeps tenant files unless asked to delete them too', function () {
    $this->artisan('tenant:delete', ['tenant' => 'acme', '--force' => true])
        ->expectsOutputToContain('Files of the tenant were kept')
        ->assertExitCode(0);

    expect(file_exists($this->tenantPath.'/storage/private/tenants/acme/invoice.pdf'))->toBeTrue();

    $this->createTenant('acme');

    $this->artisan('tenant:delete', ['tenant' => 'acme', '--force' => true, '--files' => true, '--no-backup' => true])
        ->assertExitCode(0);

    expect(is_dir($this->tenantPath.'/storage/private/tenants/acme'))->toBeFalse()
        ->and(backupsOf($this->tenantPath))->toHaveCount(1);
});

it('refuses to delete a tenant that does not exist', function () {
    $this->artisan('tenant:delete', ['tenant' => 'ghost', '--force' => true])
        ->expectsOutputToContain("Tenant 'ghost' does not exist.")
        ->assertExitCode(1);
});

it('renames a tenant with its database and files', function () {
    $this->artisan('tenant:rename', ['from' => 'acme', 'to' => 'acme-corp'])
        ->expectsConfirmation("Rename tenant 'acme' to 'acme-corp'? Its URLs change and its users have to log in again.", 'yes')
        ->assertExitCode(0);

    expect(Tenant::exists('acme'))->toBeFalse()
        ->and(Tenant::exists('acme-corp'))->toBeTrue();

    Tenant::identify('acme-corp');

    expect(Post::pluck('title')->all())->toBe(['Kept'])
        ->and(Storage::disk('local')->get('invoice.pdf'))->toBe('pdf');
});

it('refuses to rename onto an invalid, reserved or existing tenant', function (string $to, string $message) {
    $this->createTenant('globex');

    $this->artisan('tenant:rename', ['from' => 'acme', 'to' => $to, '--force' => true])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    expect(Tenant::exists('acme'))->toBeTrue();
})->with([
    ['Acme Corp', 'must be 2-63 characters'],
    ['pricing', 'is reserved'],
    ['globex', "Tenant 'globex' already exists."],
]);
