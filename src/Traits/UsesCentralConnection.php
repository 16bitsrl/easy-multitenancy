<?php

namespace Bit16\EasyMultitenancy\Traits;

/**
 * Forces an Eloquent model to always use the central (landlord) connection,
 * regardless of the currently active tenant.
 *
 * Requires `easy-multitenancy.central.enabled` to be true (or a connection
 * matching `easy-multitenancy.central.connection` to be defined).
 */
trait UsesCentralConnection
{
    public function getConnectionName(): ?string
    {
        return config('easy-multitenancy.central.connection', 'central');
    }
}
