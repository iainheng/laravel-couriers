<?php

namespace Nextbyte\Courier\Drivers\Lalamove;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Nextbyte\Courier\Clients\Lalamove\Lalamove;
use Nextbyte\Courier\Consignment;
use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Contracts\Consignmentable;
use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Price;
use Nextbyte\Courier\Shipment;
use Nextbyte\Courier\ShipmentStatusPush;

class LalamoveDriver extends Driver
{
    /**
     * @var Lalamove
     */
    protected $client;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param Lalamove $lalamove
     */
    public function __construct(Lalamove $lalamove)
    {
        $this->client = $lalamove;
        $this->courierName = 'Lalamove';
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
        if (!is_array($trackingNumbers)) {
            $trackingNumbers = [$trackingNumbers];
        }

        return new LalamoveTrackingResponse([
            'id' => Arr::first($trackingNumbers),
        ]);
    }

    /**
     * Retrieve the current status of an order by its Lalamove order ID.
     *
     * @param string $trackingNumber  Lalamove orderId
     * @return Consignment
     */
    public function consignment($trackingNumber)
    {
        $response = $this->client->getOrder($trackingNumber);

        if (empty($response) || !data_get($response, 'data.orderId')) {
            abort(500, 'Unknown error getting order details from Lalamove');
        }

        $orderData = data_get($response, 'data');
        $status = $this->normalizeShipmentStatus(data_get($orderData, 'status', ''));

        $shipment = $this->createShipmentFromOrderData($orderData);

        return Consignment::create([
            'number' => $trackingNumber,
            'trackingNumbers' => [$trackingNumber],
            'orderNumber' => data_get($orderData, 'metadata.referenceId'),
            'status' => $status,
            'statusCode' => data_get($orderData, 'status'),
            'description' => ShipmentStatus::getDescription($status),
            'updatedAt' => Carbon::now(),
            'shipments' => collect([$shipment]),
            'rawShipments' => collect([$orderData]),
        ]);
    }

    /**
     * Get a price for a delivery without booking it.
     *
     * Takes the same attributes as createConsignment(). Pass the result back as the
     * `quotation` attribute to createConsignment() to book at this exact price before
     * it expires.
     *
     * @param array $attributes
     * @return LalamoveQuotation
     * @throws CourierException
     */
    public function quote(array $attributes): LalamoveQuotation
    {
        $quotationPayload = array_filter([
            'serviceType' => data_get($attributes, 'serviceType'),
            'language' => data_get($attributes, 'language', 'en_MY'),
            'stops' => data_get($attributes, 'stops', []),
            'scheduleAt' => data_get($attributes, 'scheduleAt'),
            'specialRequests' => data_get($attributes, 'specialRequests', []),
            'isRouteOptimized' => data_get($attributes, 'isRouteOptimized'),
        ], fn($v) => $v !== null && $v !== []);

        return LalamoveQuotation::fromResponse($this->client->createQuotation($quotationPayload) ?: []);
    }

    /**
     * Create a Lalamove order via a two-step quotation + order flow.
     *
     * Expected attributes:
     *   - serviceType (string)          e.g. 'MOTORCYCLE'
     *   - stops (array)                 [{coordinates: {lat, lng}, address}, ...]
     *   - sender (array)                {stopIndex: 0, name, phone}
     *   - recipients (array)            [{stopIndex: 1, name, phone, remarks?}, ...]
     *   - scheduleAt (string|null)      ISO 8601, omit for immediate
     *   - specialRequests (array)       optional
     *   - isRouteOptimized (bool)       optional
     *   - metadata (array)              optional, e.g. {referenceId: 'ORDER-001'}
     *   - isPODEnabled (bool)           optional
     *   - quotation (LalamoveQuotation|array|null) optional, from quote(); books at that
     *                                   price instead of fetching a new quotation
     *
     * @param array $attributes
     * @return string  Lalamove orderId
     * @throws CourierException
     */
    public function createConsignment(array $attributes)
    {
        return data_get($this->placeOrder($attributes), 'data.orderId');
    }

    /**
     * Create an order and return a Consignment object (no waybill slip for Lalamove).
     *
     * The consignment's `price` is what Lalamove charged for the order.
     *
     * @param array $attributes  See createConsignment()
     * @return Consignment
     * @throws CourierException
     */
    public function createConsignmentWithSlip(array $attributes)
    {
        $response = $this->placeOrder($attributes);
        $orderId = data_get($response, 'data.orderId');

        return Consignment::create([
            'number' => $orderId,
            'trackingNumbers' => [$orderId],
            'orderNumber' => data_get($attributes, 'metadata.referenceId'),
            'slips' => [],
            'price' => Price::fromLalamove(data_get($response, 'data.priceBreakdown')),
        ]);
    }

    /**
     * Book the order with the given quotation, or a fresh one, and return Lalamove's response.
     *
     * @param array $attributes  See createConsignment()
     * @return array
     * @throws CourierException
     */
    protected function placeOrder(array $attributes): array
    {
        $quotation = $this->resolveQuotation($attributes);

        logger()->info('Lalamove createConsignment', Arr::except($attributes, 'quotation') + ['quotationId' => $quotation->id]);

        $orderAttributes = $this->buildOrderAttributes($attributes, $quotation->id, $quotation->stopIds);

        logger()->info('Lalamove createConsignment orderAttributes', $orderAttributes);
        $response = $this->client->createOrder($orderAttributes);

        if (empty($response) || !data_get($response, 'data.orderId')) {
            throw new CourierException(data_get($response, 'message', 'Unknown Lalamove create order error.'));
        }

        return $response;
    }

    /**
     * Use the caller's quotation when given, otherwise quote now.
     *
     * @throws CourierException when the given quotation has expired
     */
    protected function resolveQuotation(array $attributes): LalamoveQuotation
    {
        $quotation = data_get($attributes, 'quotation');

        if (is_array($quotation)) {
            $quotation = LalamoveQuotation::fromArray($quotation);
        }

        if (!$quotation instanceof LalamoveQuotation) {
            return $this->quote($attributes);
        }

        if ($quotation->isExpired()) {
            throw new CourierException('Lalamove quotation ' . $quotation->id . ' has expired. Get a new quote and try again.');
        }

        return $quotation;
    }

    /**
     * @inheritDoc
     * Lalamove does not issue waybill labels; slips are not available.
     */
    public function getConsignmentSlip($consignmentNumber)
    {
        throw new \BadMethodCallException('Lalamove does not issue waybill labels. No consignment slip available.');
    }

    /**
     * @inheritDoc
     * Lalamove does not issue waybill labels; slips are not available.
     */
    public function getConsignmentableSlip(Consignmentable $consignmentable)
    {
        throw new \BadMethodCallException('Lalamove does not issue waybill labels. No consignment slip available.');
    }

    /**
     * @inheritDoc
     * Lalamove does not issue waybill labels; slips are not available.
     */
    public function getConsignmentableSlips(Consignmentable $consignmentable)
    {
        throw new \BadMethodCallException('Lalamove does not issue waybill labels. No consignment slip available.');
    }

    /**
     * @inheritDoc
     */
    public function getConsignmentsLastShipment(array $consignmentNumbers)
    {
        return collect($consignmentNumbers)->mapWithKeys(function ($orderId) {
            $response = $this->client->getOrder($orderId);
            $orderData = data_get($response, 'data', []);

            return [$orderId => $this->createShipmentFromOrderData($orderData)];
        });
    }

    /**
     * Handle an incoming Lalamove webhook and invoke the callback with a ShipmentStatusPush.
     *
     * Supports three Lalamove event types, each with a different payload shape:
     *
     *   ORDER_CREATED          data.order.status = 'ASSIGNING_DRIVER'
     *   DRIVER_ASSIGNED        data.driver = {driverId, name, phone, photo, plateNumber}
     *                          data.location = {lat, lng}
     *   ORDER_STATUS_CHANGED   data.order.status = 'ON_GOING' | 'PICKED_UP' | 'COMPLETED' etc.
     *
     * @inheritDoc
     */
    public function pushShipmentStatus(callable $callback, array $attributes = [])
    {
        try {
            $eventType = data_get($attributes, 'eventType', '');

            // Events unrelated to shipment status — acknowledge and ignore.
            if (in_array($eventType, ['WALLET_BALANCE_CHANGED'])) {
                logger()->debug('Lalamove webhook ignored: ' . $eventType, $attributes);
                return response()->json(['success' => true], 200);
            }

            $orderData = data_get($attributes, 'data.order', []);
            $driverData = data_get($attributes, 'data.driver');
            $updatedAt = data_get($attributes, 'data.updatedAt', now());

            // POD_STATUS_CHANGED carries per-stop POD data. The order-level status
            // field is unreliable (reflects current state, not a transition). Instead,
            // invoke the callback once per delivery stop that has a signed POD image,
            // passing ShipmentStatus::Delivered and the stop-level POD URL + recipient.
            if ($eventType === 'POD_STATUS_CHANGED') {
                $orderId = data_get($orderData, 'orderId');
                $orderNumberKey = data_get($this->config, 'order_number_key', 'referenceId');
                $orderNumber = data_get($orderData, "metadata.{$orderNumberKey}") ?? $orderId;

                $stops = data_get($orderData, 'stops', []);
                foreach (array_slice($stops, 1) as $stop) { // skip stop[0] = pickup
                    $podImageUrl = data_get($stop, 'POD.image');
                    $podStatus   = data_get($stop, 'POD.status');

                    if (empty($podImageUrl) || $podStatus !== 'SIGNED') {
                        continue;
                    }

                    $deliveredAt = Carbon::parse(data_get($stop, 'POD.deliveredAt', $updatedAt));

                    $pushStatus = ShipmentStatusPush::create([
                        'orderNumber'       => $orderNumber,
                        'consignmentNumber' => $orderId,
                        'statusCode'        => 'POD_SIGNED',
                        'status'            => ShipmentStatus::Delivered,
                        'description'       => ShipmentStatus::getDescription(ShipmentStatus::Delivered),
                        'date'              => $deliveredAt,
                        'podImageUrl'       => $podImageUrl,
                        'recipientName'     => data_get($stop, 'name'),
                    ]);

                    $callback($pushStatus);
                }

                return response()->json(['success' => true], 200);
            }

            // DRIVER_ASSIGNED carries no order status field — derive it from the event type
            $statusCode = $eventType === 'DRIVER_ASSIGNED'
                ? 'DRIVER_ASSIGNED'
                : data_get($orderData, 'status', '');

            // ASSIGNING_DRIVER with a previousStatus means a driver rejected and Lalamove
            // is reassigning — distinguish this from the initial dispatch (no previousStatus).
            $status = ($statusCode === 'ASSIGNING_DRIVER' && !empty(data_get($orderData, 'previousStatus')))
                ? ShipmentStatus::DriverRejected
                : $this->normalizeShipmentStatus($statusCode);

            logger()->debug('status in LalamoveDriver', [$status]);

            $orderId = data_get($orderData, 'orderId');

            $orderNumberKey = data_get($this->config, 'order_number_key', 'referenceId');
            $orderNumber = data_get($orderData, "metadata.{$orderNumberKey}") ?? $orderId;

            $pushStatusData = [
                'orderNumber' => $orderNumber,
                'consignmentNumber' => $orderId,
                'statusCode' => $statusCode,
                'status' => $status,
                'description' => ShipmentStatus::getDescription($status),
                'date' => Carbon::parse($updatedAt),
            ];

            if (!empty($driverData)) {
                $pushStatusData['driverDetails'] = array_filter([
                    'driverId' => data_get($driverData, 'driverId'),
                    'name' => data_get($driverData, 'name'),
                    'phone' => data_get($driverData, 'phone'),
                    'photo' => data_get($driverData, 'photo'),
                    'plateNumber' => data_get($driverData, 'plateNumber'),
                    'location' => data_get($attributes, 'data.location'),
                ]);
            }

            $pushStatus = ShipmentStatusPush::create($pushStatusData);

            $callback($pushStatus);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 200);
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * Cancel a Lalamove order. Only eligible in ASSIGNING_DRIVER state or shortly after driver match.
     *
     * @param string $consignmentNumber  Lalamove orderId
     * @return bool
     */
    public function cancelConsignment(string $consignmentNumber): bool
    {
        return $this->client->cancelOrder($consignmentNumber);
    }

    /**
     * Get the assigned driver's current location and details for an order.
     *
     * @param string $orderId
     * @param string $driverId
     * @return array
     */
    public function getDriverLocation(string $orderId, string $driverId): array
    {
        return $this->client->getDriver($orderId, $driverId);
    }

    /**
     * Register or update the webhook URL that Lalamove will POST status updates to.
     *
     * @param string $url
     * @return array
     */
    public function setWebhookUrl(string $url): array
    {
        return $this->client->setWebhook($url);
    }

    /**
     * @param string $status  Lalamove order status string
     * @return string         ShipmentStatus constant value
     */
    protected function normalizeShipmentStatus(string $status): string
    {
        switch ($status) {
            case 'ASSIGNING_DRIVER':
                return ShipmentStatus::Pending;
            case 'DRIVER_ASSIGNED':
                return ShipmentStatus::DriverAssigned;
            case 'ON_GOING':
                return ShipmentStatus::Accepted;
            case 'PICKED_UP':
                return ShipmentStatus::Pickup;
            case 'COMPLETED':
                return ShipmentStatus::Delivered;
            case 'CANCELED':
                return ShipmentStatus::ReturnStart;
            case 'REJECTED':
                return ShipmentStatus::Rejected;
            case 'EXPIRED':
                return ShipmentStatus::OnHold;
        }

        return ShipmentStatus::Unknown;
    }

    /**
     * @param array $orderData  Raw Lalamove order data from API
     * @return Shipment
     */
    protected function createShipmentFromOrderData(array $orderData): Shipment
    {
        $status = $this->normalizeShipmentStatus(data_get($orderData, 'status', ''));

        $pickupStop = Arr::first(data_get($orderData, 'stops', []));
        $deliveryStop = Arr::last(data_get($orderData, 'stops', []));

        return Shipment::create([
            'date' => Carbon::now(),
            'origin' => data_get($pickupStop, 'address'),
            'destination' => data_get($deliveryStop, 'address'),
            'location' => data_get($pickupStop, 'address'),
            'status' => $status,
            'description' => ShipmentStatus::getDescription($status),
        ]);
    }

    /**
     * Get a quotation and return [quotationId, stopIds indexed by original stop index].
     *
     * @deprecated Use quote(), which also returns the price and expiry.
     * @param array $attributes
     * @return array  [quotationId, stopIds]
     */
    protected function fetchQuotation(array $attributes): array
    {
        $quotation = $this->quote($attributes);

        return [$quotation->id, $quotation->stopIds];
    }

    /**
     * Map the original stop indices to the Lalamove stopIds returned by quotation.
     *
     * @param array $attributes    Original createConsignment attributes
     * @param string $quotationId
     * @param array $stopIds       Ordered list of stopIds matching $attributes['stops']
     * @return array               Attributes ready for createOrder API call
     */
    protected function buildOrderAttributes(array $attributes, string $quotationId, array $stopIds): array
    {
        $senderStopIndex = data_get($attributes, 'sender.stopIndex', 0);
        $sender = [
            'stopId' => $stopIds[$senderStopIndex] ?? Arr::first($stopIds),
            'name' => data_get($attributes, 'sender.name'),
            'phone' => data_get($attributes, 'sender.phone'),
        ];

        $recipients = array_map(function ($recipient) use ($stopIds) {
            $stopIndex = data_get($recipient, 'stopIndex', 1);
            return array_filter([
                'stopId' => $stopIds[$stopIndex] ?? Arr::last($stopIds),
                'name' => data_get($recipient, 'name'),
                'phone' => data_get($recipient, 'phone'),
                'remarks' => data_get($recipient, 'remarks'),
            ], fn($v) => $v !== null);
        }, data_get($attributes, 'recipients', []));

        return array_filter([
            'quotationId' => $quotationId,
            'sender' => $sender,
            'recipients' => $recipients,
            'isPODEnabled' => data_get($attributes, 'isPODEnabled'),
            'metadata' => data_get($attributes, 'metadata'),
        ], fn($v) => $v !== null);
    }

    /**
     * @return string
     */
    protected function getTrackingMyCourierName(): string
    {
        return 'lalamove';
    }
}
