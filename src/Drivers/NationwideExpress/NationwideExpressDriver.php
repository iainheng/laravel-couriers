<?php

namespace Nextbyte\Courier\Drivers\NationwideExpress;

use GuzzleHttp\ClientInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nextbyte\Courier\Clients\NationwideExpress\NationwideExpress;
use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Enums\ConsignmentStatus;
use Nextbyte\Courier\Shipment;

class NationwideExpressDriver extends Driver
{
    /**
     * The NationwideExpress client.
     *
     * @var NationwideExpress
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
     * Create a new NationwideExpress driver instance.
     *
     * @param NationwideExpress $nationwide
     * @param string $from
     * @return void
     */
    public function __construct(NationwideExpress $nationwide, ClientInterface $httpClient, $endpoint = null)
    {
        $this->client = $nationwide;
        $this->httpClient = $httpClient;
    }

    /**
     * @inheritDoc
     */
    public function redirectTrack($trackingNumbers)
    {
        if (!is_array($trackingNumbers))
            $trackingNumbers = [$trackingNumbers];

        return new NationwideExpressTrackingResponse([
            'CNNO' => implode("\n", $trackingNumbers),
            'searchtype' => 'CN'
        ]);
    }

    /**
     * @param string $trackingNumber
     * @return Consignment
     */
    public function consignment($trackingNumber)
    {
        $shipments = $this->client->shipments($trackingNumber, $this->payload());

        $originShipment = null;
        $latestShipment = null;

        if ($shipments && !empty($shipments)) {
            $originShipment = Arr::first($shipments);
            $latestShipment = Arr::last($shipments);
        }

        $attributes = [
            'number' => $trackingNumber,
            'weight' => 0,
            'origin' => 'Unknown origin',
            'destination' => 'Unknown destination',
            'shipments' => $this->transformShipments($shipments),
            'rawShipments' => $shipments,
            'status' => ConsignmentStatus::Delivering,
            'description' => 'Delivering'
        ];

        if ($latestShipment) {
            $attributes['weight'] = $latestShipment->cn_weight;
            $attributes['origin'] = Arr::get($this->client->getLocation($latestShipment->cn_origin), 'name');
            $attributes['destination'] = Arr::get($this->client->getLocation($latestShipment->cn_dest), 'name');
            $attributes['pickedAt'] = $this->parseDatetimeString($latestShipment->pu_dt_tm);
            $attributes['updatedAt'] = $this->parseDatetimeString($latestShipment->update_dt_tm);
            $attributes['statusCode'] = $latestShipment->status_code;
            $attributes['description'] = $this->client->getStatus($latestShipment->status_code);
            $attributes['status'] = $this->getConsignmentStatus($shipments);
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
     * @param array $shipments
     * @return string
     */
    protected function getConsignmentStatus(array $shipments)
    {
        $shipments = array_reverse($shipments);

        foreach ($shipments as $shipment) {
            $status = $this->getStatusName($shipment->status_code);

            if (in_array($status, [ConsignmentStatus::Delivered, ConsignmentStatus::Cancelled, ConsignmentStatus::Returned])) {
                return $status;
            }
        }

        return ConsignmentStatus::Delivering;
    }

    /**
     * @param $statusCode
     * @return string
     */
    protected function getStatusName($statusCode)
    {
        $status = ConsignmentStatus::Delivering;

        switch($statusCode) {
            case 'POD':
                $status = ConsignmentStatus::Delivered;
                break;
            case 'CAN':
                $status = ConsignmentStatus::Cancelled;
                break;
            case 'RDO':
                $status = ConsignmentStatus::Returned;
                break;
            default:
        }

        return $status;
    }

    /**
     * @param array $json
     * @return \Illuminate\Support\Collection|Shipment[]
     */
    protected function transformShipments(array $json)
    {
        $shipments = collect();

        foreach ($json as $jsonShipment) {
            $shipments->push(Shipment::create([
                'date' => $this->parseDatetimeString($jsonShipment->update_dt_tm),
                'origin' => Arr::get($this->client->getLocation($jsonShipment->cn_origin), 'name'),
                'destination' => Arr::get($this->client->getLocation($jsonShipment->cn_dest), 'name'),
                'description' => $this->client->getStatus($jsonShipment->status_code)
            ]));
        }

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
        return 'nationwide';
    }
}
