<?php

namespace Nextbyte\Courier\Contracts;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

interface Courier
{
    /**
     * Set courier configs dynamically
     *
     * @param array $config
     * @return $this
     */
    public function config(array $config);

    /**
     * Redirect to external courier tracking page with tracking number
     *
     * @param string|array  $trackingNumbers
     * @return RedirectResponseInterface
     */
    public function redirectTrack($trackingNumbers);

    /**
     * @param string $trackingNumber
     * @param array $options
     * @return View
     */
    public function embedTrackingMy($trackingNumber, array $options = []);

    /**
     * @return array
     */
    public function getTrackingMyEmbedOptions();

    /**
     * Get all shipment status history of current tracking number
     *
     * @param string $trackingNumber
     * @return \Nextbyte\Courier\Consignment
     */
    public function consignment($trackingNumber);

    /**
     * Create a new consignment with attributes
     *
     * @param array $attributes
     * @return string   Consignment note number
     */
    public function createConsignment(array $attributes);

    /**
     * Get consignment note images/slip
     *
     * @param string $consignmentNumber
     * @return ConsignmentFile
     */
    public function getConsignmentSlip($consignmentNumber);

    /**
     * Get a latest shipment detail
     *
     * @param array $consignmentNumbers
     * @return Collection|\Nextbyte\Courier\Shipment[]
     */
    public function getConsignmentsLastShipment(array $consignmentNumbers);
}
