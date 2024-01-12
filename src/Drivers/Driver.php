<?php

namespace Nextbyte\Courier\Drivers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Nextbyte\Courier\Concerns\EmbedTrackingMy;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Contracts\Consignmentable;
use Nextbyte\Courier\Contracts\Courier;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Exceptions\UnsupportedCourierMethodException;

abstract class Driver implements Courier
{
    use EmbedTrackingMy;

    /**
     * @var array
     */
    protected $config;

    /**
     * The tracking numbers to send.
     *
     * @var array
    */
    protected $trackingNumbers;

    /**
     * General name of this courier
     *
     * @var string
     */
    protected $courierName;

    /**
     * @var bool
     */
    protected $debug = false;

//    /**
//     * {@inheritdoc}
//     */
//    abstract public function send();

    /**
     * @inheritDoc
     */
    public function config(array $config)
    {
        $this->config = $config;

        $this->debug = data_get($config, 'debug', $this->debug);

        return $this;
    }

    public function debug($message)
    {
        if ($this->debug) {
            if (App::runningInConsole()) {
                dump($message);
            } else {
                if (!is_string($message))
                    $message = json_encode($message);

                Log::debug($message);
            }
        }
    }

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
    public function createConsignment(array $attributes)
    {
        // TODO: Implement createConsignment() method.
    }

    public function createConsignmentWithSlip(array $attributes)
    {
        // TODO: Implement createConsignmentWithSlip() method.
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentSlip($consignmentNumber)
    {
        // TODO: Implement getConsignmentSlip() method.
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentableSlip(Consignmentable $consignmentable)
    {
        // TODO: Implement getConsignmentableSlip() method.
    }

    /**
     * @inheritDoc
     */
    public function pushShipmentStatus(callable $callback, array $attributes = [])
    {
        throw new UnsupportedCourierMethodException("Courier API doesn't support shipment status push.");
    }
}
