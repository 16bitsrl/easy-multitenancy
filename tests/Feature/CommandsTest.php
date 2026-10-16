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
