<?php

namespace Nextbyte\Courier\Drivers;

use Illuminate\Support\Arr;
use Nextbyte\Courier\Contracts\Courier;
use Nextbyte\Courier\Exceptions\CourierException;

abstract class Driver implements Courier
{
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
}