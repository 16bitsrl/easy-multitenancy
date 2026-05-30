<?php

use Bit16\EasyMultitenancy\Facades\Tenant;
use Bit16\EasyMultitenancy\Traits\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CentralAccount extends Model
{
    use UsesCentralConnection;

    protected $table = 'accounts';

    public $timestamps = false;

    protected $guarded = [];
}

it('registers the central connection from the default connection at boot', function () {
    expect(config('database.connections.central'))
        ->toBe(config('database.connections.testing'));
});

it('exposes the central connection name via the facade', function () {
    expect(Tenant::centralConnection())->toBe('central');
});

it('keeps central models on the central database while a tenant is active', function () {
    Schema::connection('central')->create('accounts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    CentralAccount::create(['name' => 'Landlord Inc']);

    $this->createTenant('acme');
    Tenant::identify('acme');

    // Default connection is now the tenant DB (which has no `accounts` table),
    // yet the central model must still resolve to the central database.
    expect(CentralAccount::count())->toBe(1);
    expect(CentralAccount::first()->name)->toBe('Landlord Inc');
});
