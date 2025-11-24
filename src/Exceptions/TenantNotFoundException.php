<?php

namespace Bit16\EasyMultitenancy\Exceptions;

use Exception;

class TenantNotFoundException extends Exception
{
    public function __construct(string $tenant)
    {
        parent::__construct("Tenant '{$tenant}' not found. Database does not exist.");
    }
}
