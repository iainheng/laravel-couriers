<?php

namespace Nextbyte\Courier\Drivers\PosLaju;

use GuzzleHttp\ClientInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nextbyte\Courier\Clients\PosLaju\PosLaju;
use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Enums\ConsignmentStatus;
use Nextbyte\Courier\Shipment;

class PosLajuDriver extends Driver
{
    /**
     * The PosLaju client.
     *
     * @var PosLaju
     */
    protected $client;

    /**
     * Create a new PosLaju driver instance.
     *
     * @param PosLaju $posLaju
     * @param string $from
     * @return void
     */
    public function __construct(PosLaju $posLaju, ClientInterface $httpClient, $endpoint = null)
    {
        $this->client = $posLaju;
        $this->httpClient = $httpClient;
    }

    /**
     * @inheritDoc
     */
    public function redirectTrack($trackingNumbers)
    {
        if (!is_array($trackingNumbers))
            $trackingNumbers = [$trackingNumbers];

        return new PosLajuTrackingResponse([
            'trackingNo03' => implode("\n", $trackingNumbers),
            'hvtrackNoHeader03' => '',
            'hvfromheader03' => 0,
        ]);
    }

    /**
     * @param string $trackingNumber
     * @return Consignment
     */
    public function consignment($trackingNumber)
    {
        $responseShipments = $this->client->shipments($trackingNumber, $this->payload());

        $shipments = ($responseShipments) ? $this->transformShipments($responseShipments) : collect();

        $attributes = [
            'number' => $trackingNumber,
            'weight' => 0,
            'origin' => '',
            'destination' => '',
            'shipments' => $shipments,
            'rawShipments' => $responseShipments,
            'status' => ConsignmentStatus::Delivering,
            'description' => 'Delivering'
        ];

        $latestShipment = null;

        if ($shipments && !empty($shipments)) {
            $firstShipment = $shipments->first();
            $latestShipment = $shipments->last();
        }

        if ($latestShipment) {
            $attributes['destination'] = $latestShipment->destination;
            $attributes['pickedAt'] = $firstShipment->date;
            $attributes['updatedAt'] = $latestShipment->date;
            $attributes['description'] = $latestShipment->description;
            $attributes['status'] = $this->getConsignmentStatus($attributes['shipments']);
        }

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

    /**
     * @param Collection|Shipment[]
     * @return string
     */
    protected function getConsignmentStatus($shipments)
    {
        $status = ConsignmentStatus::Delivering;

        if ($shipments && !empty($shipments)) {
            $latestShipment = $shipments->last();

            if (stripos($latestShipment->description, 'delivered') !== false) {
                $status = ConsignmentStatus::Delivered;
            }
        }

        return $status;
    }

    /**
     * @param array $response
     * @return \Illuminate\Support\Collection|Shipment[]
     */
    protected function transformShipments(array $json)
    {
        $shipments = collect();

        if (count($json)) {
            foreach ($json as $jsonShipment) {
                $shipments->push(Shipment::create([
                    'date' => $this->parseDatetimeString($jsonShipment['date_time']),
                    'origin' => '',
                    'destination' => $jsonShipment['event'],
                    'description' => $jsonShipment['process']
                ]));
            }
        }

        $shipments = $shipments->reverse();

        return $shipments;
    }

    protected function parseDatetimeString($datetimeString)
    {
//        if (!empty($datetimeString)) {
//            $tokens = explode('T', $datetimeString);
//
//            return implode(' ', $tokens);
//        }

        return $datetimeString;
    }

    /**
     * Get the HTTP payload for request shipments.
     *
     * @return array
     */
    protected function payload()
    {
        return [
//            'auth' => [
//                'api',
//                $this->key,
//            ],
//            'multipart' => [
//                [
//                    'name' => 'to',
//                    'contents' => $to,
//                ],
//                [
//                    'name' => 'message',
//                    'contents' => $message->toString(),
//                    'filename' => 'message.mime',
//                ],
//            ],
        ];
    }

    /**
     * @return string
     */
    protected function getTrackingMyCourierName()
    {
        return 'poslaju';
    }
}
