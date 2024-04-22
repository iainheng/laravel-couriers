<?php

namespace Nextbyte\Courier\Contracts;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Messages\RedirectResponseInterface;
use Nextbyte\Courier\ShipmentStatusPush;

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
     * Create a new consignment and get consignment slip/waybill
     *
     * @param array $attributes
     * @return Consignment
     */
    public function createConsignmentWithSlip(array $attributes);

    /**
     * Get consignment note images/slip
     *
     * @param string $consignmentNumber
     * @throws \BadMethodCallException
     * @return ConsignmentFile
     */
    public function getConsignmentSlip($consignmentNumber);

    /**
     * Get consignment note slip/waybill based on consignmentable
     *
     * @param Consignmentable $consignmentable
     * @return ConsignmentFile
     */
    public function getConsignmentableSlip(Consignmentable $consignmentable);

    /**
     * Get array of consignment note slip/waybill based on consignmentable
     *
     * @param Consignmentable $consignmentable
     * @return ConsignmentFile[]
     */
    public function getConsignmentableSlips(Consignmentable $consignmentable);


    /**
     * Get a latest shipment detail
     *
     * @param array $consignmentNumbers
     * @return Collection|\Nextbyte\Courier\Shipment[]
     */
    public function getConsignmentsLastShipment(array $consignmentNumbers);

    /**
     * Transform shipment status pushed from courier API
     *
     * @param array $attributes
     * @return Response
     */
    public function pushShipmentStatus(callable $callback, array $attributes = []);
}
