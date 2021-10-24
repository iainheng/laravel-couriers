<?php

namespace Nextbyte\Courier\Drivers\Best;

use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Enums\ConsignmentStatus;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class BestDriver extends Driver
{
    /**
     * Redirect to external courier tracking page with tracking number
     *
     * @return RedirectResponseInterface
     */
    public function redirectTrack($trackingNumbers)
    {
        if (!is_array($trackingNumbers))
            $trackingNumbers = [$trackingNumbers];

        return new BestTrackingResponse($trackingNumbers);
    }

    public function consignment($trackingNumber)
    {
        $attributes = [
            'number' => $trackingNumber,
            'weight' => 0,
            'origin' => '',
            'destination' => '',
            'shipments' => collect(),
            'rawShipments' => [],
            'status' => ConsignmentStatus::Delivering,
            'description' => 'Delivering'
        ];

        return Consignment::create($attributes);
    }

    /**
     * @inheritDoc
     */
    public function createConsignment(array $attributes)
    {
        // TODO: Implement createConsignment() method.
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentsLastShipment(array $consignmentNumbers)
    {
        // TODO: Implement getConsignmentsLastShipment() method.
    }
}
