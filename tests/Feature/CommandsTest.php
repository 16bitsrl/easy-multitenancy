<?php

use Bit16\EasyMultitenancy\Facades\Tenant;

it('rejects invalid tenant create names instead of stripping characters', function () {
    $this->artisan('tenant:create', [
        'name' => '../../etc/passwd',
        '--no-user' => true,
    ])->assertExitCode(1);

    expect(Tenant::exists('etcpasswd'))->toBeFalse();
});
