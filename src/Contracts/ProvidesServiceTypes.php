<?php

namespace Nextbyte\Courier\Contracts;

use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\ServiceType;

/**
 * A courier that can list the vehicles or services it offers, with their limits.
 *
 * Only drivers whose courier publishes this implement it, so check
 * `$driver instanceof ProvidesServiceTypes` before calling serviceTypes().
 */
interface ProvidesServiceTypes
{
    /**
     * The service types offered where deliveries start.
     *
     * Pass a ServiceType's `key` as the `serviceType` attribute when quoting or booking.
     *
     * @param string|null $city The courier's code for the city, or null for the configured one
     * @return ServiceType[]
     * @throws CourierException when the courier cannot list them, or does not serve the city
     */
    public function serviceTypes(?string $city = null): array;
}
