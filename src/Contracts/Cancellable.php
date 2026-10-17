<?php

namespace Nextbyte\Courier\Contracts;

use Nextbyte\Courier\Exceptions\CourierException;

/**
 * A courier whose API can cancel a booked consignment.
 *
 * Only drivers whose courier supports it implement this, so check
 * `$driver instanceof Cancellable` before calling. Couriers only allow it
 * before they collect the parcel.
 */
interface Cancellable
{
    /**
     * Cancel a consignment, or explain why the courier refused.
     *
     * The reference is whatever the courier cancels by: Lalamove's orderId, or the
     * shipmentID a DHL eCommerce label was created with (not its tracking number).
     *
     * @throws CourierException with the courier's reason when it refuses
     */
    public function cancelConsignmentOrFail(string $consignmentNumber): void;
}
