<?php

namespace Nextbyte\Courier\Drivers;

use Illuminate\Support\Arr;
use Illuminate\View\View;
use Nextbyte\Courier\Concerns\EmbedTrackingMy;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Contracts\Courier;
use Nextbyte\Courier\Exceptions\CourierException;

abstract class Driver implements Courier
{
    use EmbedTrackingMy;

    /**
     * The tracking numbers to send.
     *
     * @var array
    */
    protected $trackingNumbers;

//    /**
//     * {@inheritdoc}
//     */
//    abstract public function send();

    /**
     * @inheritDoc
     */
    public function track($trackingNumbers)
    {
        throw_if(is_null($trackingNumbers), CourierException::class, 'Tracking numbers cannot be empty');

        if (!is_array($trackingNumbers))
            $trackingNumbers = [$trackingNumbers];

        $this->trackingNumbers = $trackingNumbers;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function config(array $config)
    {
        // TODO: Implement config() method.
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
    public function getConsignmentSlip($consignmentNumber)
    {
        // TODO: Implement getConsignmentSlip() method.
    }
}
