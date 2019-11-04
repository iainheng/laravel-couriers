<?php

namespace Nextbyte\Courier\Contracts;

use Nextbyte\Courier\Messages\RedirectResponseInterface;

interface Courier
{
    /**
     * Redirect to external courier tracking page with tracking number
     *
     * @param string|array  $trackingNumbers
     * @return RedirectResponseInterface
     */
    public function redirectTrack($trackingNumbers);

    /**
     * Get all shipment status history of current tracking number
     *
     * @param string $trackingNumber
     * @return \Nextbyte\Courier\Consignment
     */
    public function consignment($trackingNumber);
}