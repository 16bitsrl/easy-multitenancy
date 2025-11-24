<?php

namespace Bit16\EasyMultitenancy\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void identify(string $tenant)
 * @method static void switchDatabase(string $tenant, string $database)
 * @method static string|null current()
 * @method static string|null id()
 * @method static string|null database()
 * @method static bool exists(string $tenant)
 * @method static string|null getDatabasePath(string $tenant)
 * @method static array all()
 * @method static void forget()
 *
 * @see \Bit16\EasyMultitenancy\Managers\TenantManager
 */
class Tenant extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tenant';
    }
}
