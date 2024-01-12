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

        $this->debug($shipments);

        $description = $shipments->first() ? $shipments->first()->getDescription() : '';

        $attributes = [
            'number' => $trackingNumber,
            'weight' => 0,
            'origin' => $shipments->last()->getLocation(),
            'destination' => $shipments->first()->getLocation(),
            'shipments' => $shipments,
            'rawShipments' => $rawShipments,
            'statusCode' => data_get($response, 'code'),
            'status' => $shipments->last()->getStatus(),
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

            $consignmentNo = $orderNumber;

            return Consignment::create([
                'orderNumber' => $orderNumber,
                'number' => $consignmentNo,
                'slip' => $slip,
                ]);
        }

        $response = $response->toArray();

        if (!$response || !data_get($response, 'success')) {
            throw new CourierException(data_get($response, 'message', 'Unknown create consignment error.'));
        }

        $label = Arr::first(data_get($response, 'data.bd.labels', []));
        $pieces = data_get($label, 'pieces', []);
        $consignmentNo = data_get($label, 'deliveryConfirmationNo');

        if (count($pieces) > 1) {
            $filename = "$consignmentNo.zip";

            $zip = new \PhpZip\ZipFile();

            foreach ($pieces as $i => $piece) {
                $pieceId = data_get($piece, 'shipmentPieceID');

                $zip->addFromString("$consignmentNo-$pieceId.pdf", base64_decode($piece['content']));
            }

            $zipContent = $zip->outputAsString();

            return Consignment::create([
                'orderNumber' => $orderNumber,
                'number' => $consignmentNo,
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
                'number' => $consignmentNo,
                'slip' => ConsignmentFile::create([
                    'name' => "$consignmentNo.png",
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

        $consignmentNo = data_get($response, 'bd.shipmentItems.0.deliveryConfirmationNo');
        $pieces = data_get($response, 'data.bd.shipmentItems', []);

        if (count($pieces) > 1) {
            $filename = "$consignmentNo.zip";

            $zip = new \PhpZip\ZipFile();

            foreach ($pieces as $i => $piece) {
                $shipmentPieceId = data_get($piece, 'shipmentID');

                $zip->addFromString("$shipmentPieceId.png", base64_decode($piece['content']));
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
                'name' => "$shipmentPieceId.png",
                'extension' => 'png',
                'body' => base64_decode(data_get($shipmentPiece, 'content')),
                'response' => $response,
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentsLastShipment(array $consignmentNumbers)
    {
        // TODO: Implement getConsignmentsLastShipment() method.
    }

    /**
     * @param array $data
     * @return Shipment
     */
    protected function createShipmentFromResponse($data)
    {
        $status = $this->normalizeShipmentStatus(data_get($data, 'status'));

        return Shipment::create([
            'date' => Carbon::createFromTimestamp(strtotime(data_get($data, 'pushTime'))),
            'origin' => data_get($data, 'address.city'),
            'destination' => data_get($data, 'address.city'),
            'location' => data_get($data, 'address.city'),
            'status' => $status,
            'description' => ShipmentStatus::getDescription($status)
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
            case 'pickup_failure':
                return ShipmentStatus::PickupFailed;
            case '77014':
            case '77015':
            case '77032':
            case '77052':
            case '77203':
            case '77200':
            case '77235':
            case '77057':
            case '77201':
                return ShipmentStatus::InTransit;
            case '77090':
                return ShipmentStatus::OutForDelivery;
            case '77093':
                return ShipmentStatus::Delivered;
            default:
        }

        return ShipmentStatus::Unknown;
    }

    /**
     * @return string
     */
    protected function getTrackingMyCourierName()
    {
        return 'dhl-ecommerce';
    }
}
