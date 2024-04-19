<?php

namespace Nextbyte\Courier\Drivers\BestExpress;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Nextbyte\Courier\Clients\BestExpress\BestExpress;
use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Contracts\Consignmentable;
use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Shipment;

class BestExpressDriver extends Driver
{
    /**
     * The BestExpress client.
     *
     * @var BestExpress
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
     * Create a new BestExpress driver instance.
     *
     * @param BestExpress $bestExpress
     * @param string $from
     * @return void
     */
    public function __construct(BestExpress $bestExpress)
    {
        $this->client = $bestExpress;
        $this->courierName = 'Best Express';
    }

    /**
     * @inheritDoc
     */
    public function config(array $config)
    {
        $this->client->config($config);

        return parent::config($config);
    }

    /**
     * @inheritDoc
     */
    public function redirectTrack($trackingNumbers)
    {
        if (!is_array($trackingNumbers))
            $trackingNumbers = [$trackingNumbers];

        return new BestExpressTrackingResponse([
            'id' => implode("\n", $trackingNumbers)
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
        $response = $this->client->createConsignment($attributes);

        if (!$response || !data_get($response, 'success')) {
            abort(500, data_get($response, 'errorDescription', 'Unknown create consignment error.'));
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
        return data_get($response, 'mailNo');
    }

    /**
     * @inheritDoc
     */
    public function createConsignmentWithSlip(array $attributes)
    {
        $response = $this->client->createConsignmentWithPdf($attributes);

        if (!$response || !data_get($response, 'success')) {
            throw new CourierException(data_get($response, 'errorDescription', 'Unknown create consignment error.'));
        }

        $consignmentNo = data_get($response, 'mailNo');

        $childMailNos = data_get($response, 'childMailNo.mailNo');

        if (!empty($childMailNos)) {
            $trackingNumbers = [];
            $filename = "$consignmentNo.zip";

            $zip = new \PhpZip\ZipFile();

            foreach ($childMailNos as $i => $childMailNo) {
                $trackingNumbers[] = $childMailNo;

                $zip->addFromString("$childMailNo.pdf", base64_decode(data_get($response, 'pdfStreamList.'. $i)));
            }

            $zipContent = $zip->outputAsString();

            $trackingNumber = Arr::first($trackingNumbers); //data_get($label, 'shipmentID');

            return Consignment::create([
                'orderNumber' =>  data_get($response, 'txLogisticId'),
                'number' => $consignmentNo,
                'trackingNumbers' => $trackingNumbers,
                'slip' => ConsignmentFile::create([
                    'name' => $filename,
                    'extension' => 'zip',
                    'body' => $zipContent,
                    'response' => $response,
                ])
            ]);
        } else {
            return Consignment::create([
                'orderNumber' =>  data_get($response, 'txLogisticId'),
                'number' => $consignmentNo,
                'slip' => ConsignmentFile::create([
                    'name' => "$consignmentNo.pdf",
                    'extension' => 'pdf',
                    'body' => base64_decode(data_get($response, 'pdfStream')),
                    'response' => $response,
                ])
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentSlip($consignmentNumber)
    {
        throw new \BadMethodCallException('Get consignment slip method is not implemented. Please use createConsignmentWithSlip() to create consignment and save slip locally.');
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentableSlip(Consignmentable $consignmentable)
    {
        $consignment = $this->createConsignmentWithSlip($consignmentable->getQueryConsignmentSlipAttributes($this->courierName));

        return $consignment->slip;
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

    /** @inheritDoc */
    public function pushShipmentStatus(callable $callback, array $attributes = [])
    {
//        $bizData = data_get($attributes, 'bizData');
//
//        $status = data_get($bizData, 'packageStatusCode');
//
//        if (!empty($status))
//            $status = $this->normalizeShipmentStatus($status);
//
//        $pushStatus = ShipmentStatusPush::create([
//            'orderNumber' => data_get($bizData, 'txLogisticId'),
//            'consignmentNumber' => data_get($bizData, 'mailNo'),
//            'status' => $status,
//            'description' => ShipmentStatus::getDescription($status),
//            'date' => Carbon::createFromTimestamp(strtotime(data_get($bizData, 'pushTime'))),
//            'currentCity' => data_get($bizData, 'currentCity'),
//            'nextCity' => data_get($bizData, 'nextCity'),
//            'remarks' => data_get($bizData, 'remarks'),
//        ]);

        // unserialize bizData if it is string
        if (is_string(data_get($attributes, 'bizData')))
            $attributes['bizData'] = json_decode(data_get($attributes, 'bizData'));

        $orderNumber = data_get($attributes, 'bizData.txLogisticId');

        try {
            $pushStatus = $this->client->createShipmentStatusPush($attributes, function ($statusCode) {
                return $this->normalizeShipmentStatus($statusCode);
            });

            /**@var $consignmentable Consignmentable */
            $consignmentable = $callback($pushStatus);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'remark' => $orderNumber,
                'errorCode' => $e->getCode(),
                'errorDescription' => $e->getMessage(),
            ]);
        }

        $success = $consignmentable instanceof Consignmentable;

        return response()->json([
            'result' => $success,
            'remark' => $consignmentable->getOrderNumber(),
            'errorCode' => '',
            'errorDescription' => ''
        ]);
    }

    /**
     * @param $statusCode
     * @return string
     */
    protected function normalizeShipmentStatus($statusCode)
    {
        switch ($statusCode) {
            case 'order_success':
                return ShipmentStatus::Accepted;
            case 'order_failure':
                return ShipmentStatus::AcceptFailed;
            case 'pickup_success':
                return ShipmentStatus::Pickup;
            case 'pickup_failure':
                return ShipmentStatus::PickupFailed;
            case 'arrive_station':
            case 'send_from_station':
            case 'arrive_hub':
            case 'send_from_hub':
                return ShipmentStatus::InTransit;
            case 'out_for_delivery':
                return ShipmentStatus::OutForDelivery;
            case 'delivered':
                return ShipmentStatus::Delivered;
            case 'package_return':
                return ShipmentStatus::ReturnStart;
            case 'return_success':
                return ShipmentStatus::Returned;
            case 'hold_in_station':
                return ShipmentStatus::OnHold;
            case 'specialPOD':
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
            'date' => Carbon::createFromTimestamp(strtotime(data_get($data, 'pushTime'))),
            'origin' => data_get($data, 'currentCity'),
            'destination' => data_get($data, 'nextCity'),
            'location' => data_get($data, 'currentCity'),
            'status' => $status,
            'description' => ShipmentStatus::getDescription($status)
        ]);
    }

    /**
     * @return string
     */
    protected function getTrackingMyCourierName()
    {
        return 'best';
    }
}
