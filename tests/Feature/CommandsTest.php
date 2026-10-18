<?php

use Bit16\EasyMultitenancy\Facades\Tenant;

it('rejects invalid tenant create names instead of stripping characters', function () {
    $this->artisan('tenant:create', [
        'name' => '../../etc/passwd',
        '--no-user' => true,
    ])->assertExitCode(1);

    expect(Tenant::exists('etcpasswd'))->toBeFalse();
});

it('rejects tenant names that collide with reserved paths', function (string $name) {
    $this->artisan('tenant:create', [
        'name' => $name,
        '--no-user' => true,
    ])->assertExitCode(1);

    expect(Tenant::exists($name))->toBeFalse();
})->with(['api', 'horizon', 'up', 'pricing']);

it('does not overwrite an existing tenant database', function () {
    $this->createTenant('acme');

    $this->artisan('tenant:create', [
        'name' => 'acme',
        '--no-user' => true,
    ])->assertExitCode(1);

    expect(Tenant::exists('acme'))->toBeTrue();
});

it('creates the tenant without a user when the configured user model is not an Eloquent model', function () {
    config()->set('auth.providers.users.model', \stdClass::class);

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsQuestion('Admin email address', 'admin@acme.test')
        ->expectsQuestion('Admin password (leave blank to generate)', '')
        ->expectsOutputToContain('User model not found')
        ->assertExitCode(0);

    expect(Tenant::exists('acme'))->toBeTrue();
});
