<?php

namespace Nextbyte\Courier\Drivers\Gdex;

use Carbon\Carbon;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\File;
use Illuminate\Support\Arr;
use Nextbyte\Courier\Clients\Gdex\Gdex;
use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Contracts\Courier;
use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Enums\ConsignmentStatus;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\Shipment;

class GdexDriver extends Driver
{
    /**
     * The Gdex client.
     *
     * @var Gdex
     */
    protected $client;

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * The Nationwide Express API endpoint.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * @var array|mixed
     */
    protected $config;

    /**
     * Create a new Gdex driver instance.
     *
     * @param Gdex $gdex
     * @param string $from
     * @return void
     */
    public function __construct(Gdex $gdex)
    {
        $this->client = $gdex;
    }

    /**
     * @inheritDoc
     */
    public function config(array $config)
    {
        $this->client->config($config);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function redirectTrack($trackingNumbers)
    {
        if (!is_array($trackingNumbers))
            $trackingNumbers = [$trackingNumbers];

        return new GdexTrackingResponse([
            'id' => 'GDEX',
            'input' => implode("\n", $trackingNumbers)
        ]);
    }

    /**
     * @param string $trackingNumber
     * @return Consignment
     */
    public function consignment($trackingNumber)
    {
        $response = $this->client->getShipmentStatusDetail($trackingNumber);

        if (!$response || !data_get($response, 'success')) {
            abort(500, data_get($response, 'e', 'Unknown error getting consignments shipment details'));
        }

        $rawShipments = collect(data_get($response, 'r.cnDetailStatusList', []));

        $shipments = $rawShipments->map(function ($data) {
            return $this->createShipmentFromResponse($data);
        });

        $description = $shipments->first() ? $shipments->first()->getDescription() : '';

        $attributes = [
            'number' => $trackingNumber,
            'weight' => 0,
            'origin' => data_get($rawShipments->last(), 'location'),
            'destination' => data_get($rawShipments->first(), 'location'),
            'shipments' => $shipments,
            'rawShipments' => $rawShipments,
            'statusCode' => data_get($response, 'r.latestEnumStatus'),
            'status' => $this->normalizeShipmentStatus(data_get($response, 'r.latestEnumStatus')),
            'description' => $description,
        ];

        return Consignment::create($attributes);
    }

    /**
     * @inheritDoc
     */
    public function createConsignment(array $attributes)
    {
        $response = $this->client->createConsignment([$attributes]);

//        $response = [
//            "success" => true,
//            "s" => "success",
//            "r" => [
//                0 => "TCN1001182"
//            ],
//            "e" => ""
//        ];

        if (!$response || !data_get($response, 'success')) {
            abort(500, data_get($response, 'e', 'Unknown create consignment error.'));
        }

        /**
         * Response sample
         *
         * [
         *   "s": "success",
         *   "r": [
         *     0 => "TCN1001182"
         *   ],
         *   "e": ""
         * ]
         */
        return Arr::first(data_get($response, 'r'));
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentSlip($consignmentNumber)
    {
        $response = $this->client->getZippedConsignmentImage($consignmentNumber);

        if (!$response || $response->getStatusCode() !== 200) {
            abort(500, data_get($response, 'message', 'Unknown api error.'));
        }

        return ConsignmentFile::create([
            'name' => 'ConsignmentNote.zip',
            'extension' => 'zip',
            'body' => $response->getBody(),
            'response' => $response,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentsLastShipment(array $consignmentNumbers)
    {
        $response = $this->client->getLastShipmentStatus($consignmentNumbers);

        if (!$response || !data_get($response, 'success')) {
            abort(500, data_get($response, 'e', 'Unknown error getting consignments last shipment.'));
        }

        return collect(data_get($response, 'r'))->keyBy('consignmentNote')->map(function ($data) {
            return $this->createShipmentFromResponse($data);
        });
    }

    /**
     * @param $statusCode
     * @return string
     */
    protected function normalizeShipmentStatus($statusCode)
    {
        switch ($statusCode) {
            case 0:
                return ShipmentStatus::Pending;
            case 1:
                return ShipmentStatus::Pickup;
            case 2:
                return ShipmentStatus::InTransit;
            case 3:
                return ShipmentStatus::OutForDelivery;
            case 4:
                return ShipmentStatus::Delivered;
            case 5:
                return ShipmentStatus::Returned;
            case 6:
                return ShipmentStatus::Claim;
            case 7:
                return ShipmentStatus::Undelivered;
            default:
        }

        return ShipmentStatus::Unknown;
    }

    /**
     * @param $data
     * @return Shipment
     */
    protected function createShipmentFromResponse($data)
    {
        $status = $this->normalizeShipmentStatus(data_get($data, 'enumStatus'));

        return Shipment::create([
            'date' => Carbon::createFromTimestamp(strtotime(data_get($data, 'latestScanDateTime'))),
            'origin' => data_get($data, 'location'),
            'destination' => data_get($data, 'location'),
            'location' => data_get($data, 'location'),
            'status' => $status,
            'description' => ShipmentStatus::getDescription($status)
        ]);
    }

    /**
     * @return string
     */
    protected function getTrackingMyCourierName()
    {
        return 'gdex';
    }
}
