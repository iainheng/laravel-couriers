<?php

namespace Nextbyte\Courier\Drivers\DhlEcommerce;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Nextbyte\Courier\Clients\DhlEcommerce\DhlEcommerce;
use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Contracts\Consignmentable;
use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Messages\RedirectResponseInterface;
use Nextbyte\Courier\Shipment;

class DhlEcommerceDriver extends Driver
{
    /**
     * Create a new DHL driver instance.
     *
     * @param DhlEcommerce $dhl
     * @param string $from
     * @return void
     */
    public function __construct(DhlEcommerce $dhl)
    {
        $this->client = $dhl;
        $this->courierName = 'DHL Ecommerce';
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
     * Redirect to external courier tracking page with tracking number
     *
     * @return RedirectResponseInterface
     */
    public function redirectTrack($trackingNumbers)
    {
        if (!is_array($trackingNumbers))
            $trackingNumbers = [$trackingNumbers];

        return new DhlEcommerceTrackingResponse($trackingNumbers);
    }

    public function consignment($trackingNumber)
    {
        $response = $this->client->getShipmentStatusDetail($trackingNumber)->toArray();

        if (!$response || !data_get($response, 'success')) {
            abort(500, data_get($response, 'message', 'Unknown error getting consignments shipment details'));
        }

        $rawShipments = collect(data_get($response, 'data.bd.shipmentItems.0.events', []));

        $shipments = $rawShipments->map(function ($data) {
            return $this->createShipmentFromResponse($data);
        });

//        $this->debug($shipments);

        $description = $shipments->first() ? $shipments->first()->getDescription() : '';

        $attributes = [
            'number' => $trackingNumber,
            'weight' => 0,
            'origin' => $shipments->last()->getLocation(),
            'destination' => $shipments->first()->getLocation(),
            'shipments' => $shipments,
            'rawShipments' => $rawShipments,
            'statusCode' => data_get($response, 'code'),
            'status' => $shipments->first()->getStatus(),
            'description' => $description,
            'updatedAt' => $shipments->first()->getDate(),
        ];

        return Consignment::create($attributes);
    }

    /**
     * @inheritDoc
     */
    public function createConsignment(array $attributes)
    {
        $response = $this->client->createConsignment($attributes);

        // if consignment is created before, DHL doesn't allow to create a new one with same shipmentId.
        // Use reprint label endpoint to retrieve label slip.
        if ($response->isShipmentIdExists()) {
            $reprintResponse = $this->client->reprintConsignmentSlip($attributes)->toArray();

            $label = Arr::first(data_get($reprintResponse, 'data.bd.shipmentItems', []));
            $consignmentNo = data_get($label, 'deliveryConfirmationNo');

            return $consignmentNo;
        }

        $response = $response->toArray();

        if (!$response || !data_get($response, 'success')) {
            abort(500, data_get($response, 'message', 'Unknown create consignment error.'));
        }

        $label = Arr::first(data_get($response, 'data.bd.labels', []));
        $consignmentNo = data_get($label, 'deliveryConfirmationNo');

        return $consignmentNo;
    }

    /**
     * @inheritDoc
     */
    public function createConsignmentWithSlip(array $attributes)
    {
        $response = $this->client->createConsignmentWithSlip($attributes);

        $orderNumber = data_get(Arr::first(data_get($attributes, 'bd.shipmentItems')), 'shipmentID');

        // if consignment is created before, DHL doesn't allow to create a new one with same shipmentId.
        // Use reprint label endpoint to retrieve label slip.
        if ($response->isShipmentIdExists()) {
            $slip = $this->reprintConsignmentSlip($attributes);

            $trackingNumber = $orderNumber;

            return Consignment::create([
                'orderNumber' => $orderNumber,
                'number' => $trackingNumber,
                'slip' => $slip,
            ]);
        }

        $response = $response->toArray();

        if (!$response || !data_get($response, 'success')) {
            throw new CourierException(data_get($response, 'message', 'Unknown create consignment error.'));
        }

        $label = Arr::first(data_get($response, 'data.bd.labels', []));
        $pieces = data_get($label, 'pieces', []);
        $trackingNumber = data_get($label, 'deliveryConfirmationNo');

        if (count($pieces) > 1) {
            $zip = new \PhpZip\ZipFile();

            $filename = "$orderNumber.zip";
            $trackingNumbers = [];

            foreach ($pieces as $i => $piece) {
                $deliveryConfirmationNo = data_get($piece, 'deliveryConfirmationNo');
                $pieceId = data_get($piece, 'shipmentPieceID');

                $trackingNumbers[] = $deliveryConfirmationNo;

                $zip->addFromString("$deliveryConfirmationNo.png", base64_decode($piece['content']));
            }

            $zipContent = $zip->outputAsString();

            $trackingNumber = Arr::first($trackingNumbers); //data_get($label, 'shipmentID');

            return Consignment::create([
                'orderNumber' => $orderNumber,
                'number' => $trackingNumber,
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
                'orderNumber' => $orderNumber,
                'number' => $trackingNumber,
                'trackingNumbers' => [$trackingNumber],
                'slip' => ConsignmentFile::create([
                    'name' => "$trackingNumber.png",
                    'extension' => 'png',
                    'body' => base64_decode(data_get($label, 'content')),
                    'response' => $response,
                ])
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentableSlip(Consignmentable $consignmentable)
    {
        $payload = $consignmentable->getQueryConsignmentSlipAttributes($this->courierName);

        return $this->reprintConsignmentSlip($payload);
    }

    /**
     * @param array $payload
     * @return ConsignmentFile
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \PhpZip\Exception\ZipException
     */
    protected function reprintConsignmentSlip(array $payload)
    {
        $shipmentId = data_get($payload, 'bd.shipmentItems.0.shipmentID');

        $response = $this->client->reprintConsignmentSlip($payload)->toArray();

        if (!$response || !data_get($response, 'success')) {
            throw new CourierException(data_get($response, 'message', 'Unknown create consignment error.'));
        }

        $consignmentNo = data_get($response, 'data.bd.shipmentItems.0.deliveryConfirmationNo', data_get($response, 'data.bd.shipmentItems.0.shipmentID'));
        $pieces = data_get($response, 'data.bd.shipmentItems', []);

        if (count($pieces) > 1) {
            $consignmentNo = data_get($response, 'data.bd.shipmentItems.0.shipmentID');

            $filename = "$consignmentNo.zip";

            $zip = new \PhpZip\ZipFile();

            foreach ($pieces as $i => $piece) {
                $deliveryConfirmationNo = data_get($piece, 'deliveryConfirmationNo');
                $pieceId = data_get($piece, 'shipmentPieceID');

                $zip->addFromString("$deliveryConfirmationNo.png", base64_decode($piece['content']));
            }

            $zipContent = $zip->outputAsString();

            return ConsignmentFile::create([
                'name' => $filename,
                'extension' => 'zip',
                'body' => $zipContent,
                'response' => $response,
            ]);
        } else {
            $shipmentPiece = Arr::first($pieces);

            $shipmentPieceId = data_get($shipmentPiece, 'shipmentID');

            return ConsignmentFile::create([
                'name' => "$consignmentNo.png",
                'extension' => 'png',
                'body' => base64_decode(data_get($shipmentPiece, 'content')),
                'response' => $response,
            ]);
        }
    }

    /** @inheritDoc */
    public function pushShipmentStatus(callable $callback, array $attributes = [])
    {
        try {
            $pushStatus = $this->client->createShipmentStatusPush($attributes, function ($statusCode) {
                return $this->normalizeShipmentStatus($statusCode);
            });

            // remove prefix to locate order in callback
            $originalOrderNumber = $pushStatus->getOrderNumber();
            $pushStatus->setOrderNumber(str_replace(config('courier.dhl-ecommerce.shipment_prefix'), '', $pushStatus->getOrderNumber()));

            /**@var $consignmentable Consignmentable */
            $consignmentable = $callback($pushStatus);

            // revert order number for response
            $pushStatus->setOrderNumber($originalOrderNumber);

            $responseBody = [
                'shipmentItems' => [
                    [
                        'shipmentID' => $pushStatus->getOrderNumber(),
                        'trackingID' => $pushStatus->getConsignmentNumber(),
                        'responseStatus' => [
                            'code' => 200,
                            'message' => 'SUCCESS',
                            'messageDetail' => null
                        ]
                    ]
                ]
            ];
        } catch (\Exception $e) {
            data_set($responseBody, 'shipmentItems.0.responseStatus', [
                'code' => 422,
                'message' => 'ERROR',
                'messageDetails' => [
                    'messageDetail' => $e->getMessage(),
                ]
            ]);
//            return response()->json($this->getWebhookResponseData($responseBody));
        }

        return response()->json($this->getWebhookResponseData($responseBody));
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentsLastShipment(array $consignmentNumbers)
    {
        $data = collect();

        foreach ($consignmentNumbers as $consignmentNumber) {
            $response = $this->client->getShipmentStatusDetail($consignmentNumber)->toArray();

            $rawShipments = collect(data_get($response, 'data.bd.shipmentItems.0.events', []));

            $data->put($consignmentNumber, $rawShipments->map(function ($data) {
                return $this->createShipmentFromResponse($data);
            }));
        }

        return $data;
    }

    /**
     * @param array $data
     * @return Shipment
     */
    protected function createShipmentFromResponse($data)
    {
        $status = $this->normalizeShipmentStatus(data_get($data, 'status'));

        return Shipment::create([
            'date' => Carbon::createFromTimestamp(strtotime(data_get($data, 'dateTime'))),
            'origin' => data_get($data, 'address.city'),
            'destination' => data_get($data, 'address.city'),
            'location' => data_get($data, 'address.city'),
            'status' => $status,
            'description' => data_get($data, 'description', ShipmentStatus::getDescription($status))
        ]);
    }

    /**
     * @param $statusCode
     * @return string
     */
    protected function normalizeShipmentStatus($statusCode)
    {
        switch ($statusCode) {
            case '71005':
            case '77123':
                return ShipmentStatus::Accepted;
            case 'order_failure':
                return ShipmentStatus::AcceptFailed;
            case '77206':
                return ShipmentStatus::Pickup;
            case '77429':
                return ShipmentStatus::PickupFailed;
            case '77013':
            case '77178':
            case '77169':
                return ShipmentStatus::ArrivedAtFacility;
            case '77014':
            case '77015':
            case '77027':
            case '77184':
                return ShipmentStatus::ProcessingAtFacility;
            case '77101':
                return ShipmentStatus::DeliveryAttempted;
            case '77032':
            case '77052':
            case '77203':
            case '77200':
            case '77235':
            case '77057':
            case '77201':
                return ShipmentStatus::InTransit;
            case '77710':
                return ShipmentStatus::HandoverToVendor;
            case '77090':
                return ShipmentStatus::OutForDelivery;
            case '77093':
                return ShipmentStatus::Delivered;
            case '77098':
                return ShipmentStatus::DeliveryRefused;
            case '77696':
                return ShipmentStatus::DeliveryAwaiting;
            case '77174':
                return ShipmentStatus::Returned;
            default:
        }

        return ShipmentStatus::Unknown;
    }

    /**
     * @return array
     */
    protected function getWebhookResponseData($body = [])
    {
        return [
            'pushTrackingResponse' => [
                'hdr' => [
                    'messageType' => 'PUSHTRACKITEM',
                    'messageDateTime' => now()->toIso8601String(),
                    'messageVersion' => '1.0',
                    'messageLanguage' => 'en',
                ],
                'bd' => $body,
                'responseStatus' => [
                    'code' => 200,
                    'message' => 'SUCCESS',
                    'messageDetails' => [
                        'messageDetail' => null,
                    ]
                ]
            ]
        ];
    }

    /**
     * @return string
     */
    protected function getTrackingMyCourierName()
    {
        return 'dhl-ecommerce';
    }
}
