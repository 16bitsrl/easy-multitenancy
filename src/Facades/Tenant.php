<?php

namespace Bit16\EasyMultitenancy\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void identify(string $tenant)
 * @method static void switchDatabase(string $tenant, string $database)
 * @method static string|null current()
 * @method static string centralConnection()
 * @method static void centralRoutes(\Closure $callback)
 * @method static array<string, int> getRecentTenants()
 * @method static string|null id()
 * @method static string|null database()
 * @method static bool exists(string $tenant)
 * @method static bool isValid(string $name)
 * @method static bool isReserved(string $name)
 * @method static string sanitize(string $name)
 * @method static string|null getDatabasePath(string $tenant)
 * @method static array<int, string> all()
 * @method static void forget()
 * @method static void flushSessionState()
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
