<?php

namespace Bit16\EasyMultitenancy\Contracts;

/**
 * Marker interface for jobs that should run globally, without tenant context.
 *
 * Jobs implementing this interface are excluded from automatic tenant
 * injection and run in the central application context.
 */
interface GlobalJob
{
}
