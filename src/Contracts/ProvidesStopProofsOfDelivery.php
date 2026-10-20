<?php

namespace Nextbyte\Courier\Contracts;

use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\ProofOfDelivery;

/**
 * A courier whose one booking can deliver to several stops (e.g. a Lalamove multi-stop order),
 * each with its own proof of delivery.
 *
 * Check `$driver instanceof ProvidesStopProofsOfDelivery` before calling it. Each proof's `raw`
 * holds the stop as the courier returned it (name, phone, address, coordinates), so the caller
 * can tell which of its shipments the stop is.
 */
interface ProvidesStopProofsOfDelivery
{
    /**
     * The proofs of delivery of the booking's delivered stops, in the courier's stop order.
     * Stops not delivered yet are left out.
     *
     * @return ProofOfDelivery[]
     * @throws CourierException when the courier cannot be asked
     */
    public function getProofsOfDelivery(string $bookingId): array;
}
