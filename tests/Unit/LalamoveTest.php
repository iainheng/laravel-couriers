<?php

namespace Nextbyte\Tests\Courier\Unit;

use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Mockery as m;
use Nextbyte\Courier\Clients\Lalamove\Lalamove;
use Nextbyte\Courier\Contracts\Quotable;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveDriver;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveQuotation;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveTrackingResponse;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Price;
use Nextbyte\Courier\Quotation;
use Nextbyte\Courier\ShipmentStatusPush;
use Nextbyte\Tests\Courier\Fixtures\LalamoveOrder;
use Nextbyte\Tests\Courier\TestCase;

class LalamoveTest extends TestCase
{
    protected $driverName = 'lalamove';

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    protected function makeOrder(string $orderNumber = 'ORD-LA-0001', string $name = 'Michal'): LalamoveOrder
    {
        return new LalamoveOrder($orderNumber, $name);
    }

    public function test_it_return_correct_driver()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->assertInstanceOf(LalamoveDriver::class, $courier);
    }

    public function test_it_can_redirect_track()
    {
        $courier = $this->makeDriver($this->driverName);

        $orderId = 'ord-abc123def456';

        $response = $courier->redirectTrack($orderId);

        $this->assertInstanceOf(LalamoveTrackingResponse::class, $response);
        $this->assertStringContainsString($orderId, $response->getRedirectUrl());
        $this->assertStringContainsString('lalamove.com', $response->getRedirectUrl());
    }

    public function test_redirect_track_accepts_array()
    {
        $courier = $this->makeDriver($this->driverName);

        $orderId = 'ord-abc123def456';

        $response = $courier->redirectTrack([$orderId, 'ord-second']);

        $this->assertInstanceOf(LalamoveTrackingResponse::class, $response);
        $this->assertStringContainsString($orderId, $response->getRedirectUrl());
    }

    public function test_it_throws_exception_when_getting_consignment_slip()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->expectException(\BadMethodCallException::class);

        $courier->getConsignmentSlip('ord-abc123');
    }

    public function test_it_throws_exception_when_getting_consignmentable_slip()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->expectException(\BadMethodCallException::class);

        $courier->getConsignmentableSlip($this->makeOrder());
    }

    public function test_it_throws_exception_when_getting_consignmentable_slips()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->expectException(\BadMethodCallException::class);

        $courier->getConsignmentableSlips($this->makeOrder());
    }

    public function test_it_can_push_order_created_webhook()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeOrder('13993-000');

        $payload = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/webhooks/order_created.json'),
            true
        );

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order) {
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $payload);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(ShipmentStatus::Pending, $order->getShipmentStatus());
        $this->assertTrue(data_get(json_decode($response->content(), true), 'success'));
    }

    public function test_it_can_push_driver_assigned_webhook()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeOrder('13993-000');

        $payload = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/webhooks/driver_assigned.json'),
            true
        );

        $capturedPush = null;

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order, &$capturedPush) {
            $capturedPush = $push;
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(ShipmentStatus::DriverAssigned, $order->getShipmentStatus());

        $this->assertTrue($capturedPush->hasDriverDetails());
        $driver = $capturedPush->getDriverDetails();
        $this->assertEquals('80039', data_get($driver, 'driverId'));
        $this->assertEquals('TestDriver 44111', data_get($driver, 'name'));
        $this->assertEquals('+6011144111', data_get($driver, 'phone'));
        $this->assertEquals('VP2381474', data_get($driver, 'plateNumber'));
        $this->assertNotEmpty(data_get($driver, 'location'));
    }

    public function test_it_can_push_order_status_changed_webhook()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeOrder('13993-000');

        $payload = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/webhooks/order_status_changed_on_going.json'),
            true
        );

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order) {
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(ShipmentStatus::Accepted, $order->getShipmentStatus());
    }

    public function test_push_shipment_status_resolves_order_number_from_metadata()
    {
        $courier = $this->makeDriver($this->driverName);

        $resolvedOrderNumber = null;

        $payload = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/webhooks/order_created.json'),
            true
        );

        $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use (&$resolvedOrderNumber) {
            $resolvedOrderNumber = $push->getOrderNumber();
            return null;
        }, $payload);

        $this->assertEquals('13993-000', $resolvedOrderNumber);
    }

    public function test_push_shipment_status_falls_back_to_order_id_when_no_metadata()
    {
        $courier = $this->makeDriver($this->driverName);

        $resolvedOrderNumber = null;

        $payload = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/webhooks/order_status_changed_on_going.json'),
            true
        );

        $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use (&$resolvedOrderNumber) {
            $resolvedOrderNumber = $push->getOrderNumber();
            return null;
        }, $payload);

        // ON_GOING payload has no metadata, so falls back to orderId
        $this->assertEquals('3487384979687051627', $resolvedOrderNumber);
    }

    public function test_it_can_push_driver_rejected_order_webhook()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeOrder('13993-000');

        $payload = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/webhooks/driver_rejected_order.json'),
            true
        );

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order) {
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(ShipmentStatus::DriverRejected, $order->getShipmentStatus());
    }

    public function test_it_can_push_order_rejected_closed_webhook()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeOrder('13993-000');

        $payload = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/webhooks/order_rejected_closed.json'),
            true
        );

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order) {
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(ShipmentStatus::Rejected, $order->getShipmentStatus());
    }

    public function test_wallet_balance_changed_webhook_is_ignored()
    {
        $courier = $this->makeDriver($this->driverName);

        $callbackInvoked = false;

        $payload = [
            'eventType' => 'WALLET_BALANCE_CHANGED',
            'data' => [
                'wallet' => ['balance' => '100.00', 'currency' => 'MYR'],
            ],
        ];

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use (&$callbackInvoked) {
            $callbackInvoked = true;
        }, $payload);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($callbackInvoked, 'Callback should not be invoked for WALLET_BALANCE_CHANGED events');
        $this->assertTrue(data_get(json_decode($response->content(), true), 'success'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lalamoveStatusProvider')]
    public function test_it_normalises_lalamove_status_correctly(string $rawStatus, string $expectedStatus)
    {
        $courier = $this->makeDriver($this->driverName);

        $capturedStatus = null;

        $payload = [
            'eventType' => 'ORDER_STATUS_CHANGED',
            'data' => [
                'order' => [
                    'orderId' => 'ord-status-test',
                    'status' => $rawStatus,
                ],
                'updatedAt' => now()->toIso8601String(),
            ],
        ];

        $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use (&$capturedStatus) {
            $capturedStatus = $push->getStatus();
            return null;
        }, $payload);

        $this->assertEquals($expectedStatus, $capturedStatus);
    }

    public static function lalamoveStatusProvider(): array
    {
        return [
            'assigning_driver maps to Pending'       => ['ASSIGNING_DRIVER', ShipmentStatus::Pending],
            'on_going maps to Accepted'              => ['ON_GOING', ShipmentStatus::Accepted],
            'picked_up maps to Pickup'               => ['PICKED_UP', ShipmentStatus::Pickup],
            'completed maps to Delivered'            => ['COMPLETED', ShipmentStatus::Delivered],
            'canceled maps to Cancelled'             => ['CANCELED', ShipmentStatus::Cancelled],
            'rejected maps to Rejected'              => ['REJECTED', ShipmentStatus::Rejected],
            'expired maps to Expired'                => ['EXPIRED', ShipmentStatus::Expired],
            'unknown status maps to Unknown'         => ['SOME_UNKNOWN_STATUS', ShipmentStatus::Unknown],
        ];
    }

    public function test_consignment_populates_tracking_numbers()
    {
        $orderId = '3487384979687051627';
        $fixture = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/get_order_response.json'),
            true
        );

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('getOrder')->with($orderId)->andReturn($fixture);

        $driver = new LalamoveDriver($mockClient);
        $consignment = $driver->consignment($orderId);

        $this->assertEquals([$orderId], $consignment->trackingNumbers);
        $this->assertEquals($orderId, $consignment->number);
        $this->assertEquals('13993-000', $consignment->orderNumber);
    }

    public function test_create_consignment_with_slip_populates_tracking_numbers()
    {
        $orderId = '3487384979687051627';
        $quotationFixture = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/create_quotation_response.json'),
            true
        );

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload));
        $mockClient->shouldReceive('createOrder')->once()->andReturnUsing(fn (array $order) => $this->orderResponse($order));

        $driver = new LalamoveDriver($mockClient);
        $consignment = $driver->createConsignmentWithSlip($this->makeOrder('13993-000')->toConsignmentableArray('lalamove'));

        $this->assertEquals([$orderId], $consignment->trackingNumbers);
        $this->assertEquals($orderId, $consignment->number);
        $this->assertEquals('13993-000', $consignment->orderNumber);
        $this->assertIsArray($consignment->slips);
        $this->assertEmpty($consignment->slips);
    }

    /**
     * Production reports a delivery photo's POD as DELIVERED (the sandbox sends SIGNED), so a POD
     * counts when it has a photo and isn't PENDING or FAILED.
     */
    public function test_a_pod_webhook_counts_every_delivered_stop_with_a_photo()
    {
        $courier = $this->makeDriver($this->driverName);
        $pushes = [];

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use (&$pushes) {
            $pushes[] = $push;
        }, $this->fixture('webhooks/pod_status_changed_production'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(['SITI AMINAH', 'KUMAR A/L RAJU'], array_map(fn (ShipmentStatusPush $push) => $push->getRecipientName(), $pushes));
        $this->assertSame('http://sg-oimg.lalamove.com/appdriver/prd/2026-10-20/stop-1.jpeg', $pushes[0]->getPodImageUrl());
        $this->assertSame(ShipmentStatus::Delivered, $pushes[0]->getStatus());
        $this->assertSame('3609685653014667595', $pushes[0]->getConsignmentNumber());
    }

    public function test_the_proofs_of_delivery_of_a_multi_stop_booking_are_fetched_per_stop()
    {
        $client = m::mock(Lalamove::class);
        $client->shouldReceive('getOrder')->with('3609685653014667595')->andReturn($this->fixture('order_details_pods'));
        $driver = new LalamoveDriver($client);

        $this->assertInstanceOf(\Nextbyte\Courier\Contracts\ProvidesStopProofsOfDelivery::class, $driver);

        $pods = $driver->getProofsOfDelivery('3609685653014667595');

        $this->assertSame(['SITI AMINAH', 'KUMAR A/L RAJU'], array_map(fn ($pod) => $pod->recipientName, $pods), 'Pending and failed stops are left out.');
        $this->assertSame('http://sg-oimg.lalamove.com/appdriver/prd/2026-10-20/stop-3.jpeg', $pods[1]->imageUrl);
        $this->assertSame('+60120000003', data_get($pods[1]->raw, 'phone'));
        $this->assertSame('3.0738000', data_get($pods[1]->raw, 'coordinates.lat'));
        $this->assertSame('2026-10-20 14:05:00', $pods[1]->deliveredAt->format('Y-m-d H:i:s'));

        $this->assertNull($driver->getProofOfDelivery('3609685653014667595'), 'A multi-stop booking has no single proof of delivery.');
    }

    public function test_the_proof_of_delivery_of_a_one_stop_booking()
    {
        $order = $this->fixture('order_details_pods');
        $order['data']['stops'] = array_slice($order['data']['stops'], 0, 2);
        $client = m::mock(Lalamove::class);
        $client->shouldReceive('getOrder')->andReturn($order);

        $pod = (new LalamoveDriver($client))->getProofOfDelivery('3609685653014667595');

        $this->assertSame('SITI AMINAH', $pod->recipientName);
        $this->assertTrue($pod->hasImage());

        $order['data']['stops'][1]['POD'] = ['status' => 'PENDING'];
        $pending = m::mock(Lalamove::class);
        $pending->shouldReceive('getOrder')->andReturn($order);
        $this->assertNull((new LalamoveDriver($pending))->getProofOfDelivery('3609685653014667595'), 'Not delivered yet: the caller tries again later.');
    }

    protected function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/' . $name . '.json'), true);
    }

    /**
     * The sample quotation response, with its stops replaced by the requested ones
     * (Lalamove echoes each stop's coordinates and address, adding a stopId).
     *
     * @param int[]|null $drivingOrder requested stop indexes in the order Lalamove returns them
     */
    protected function quotationFor(array $payload, ?array $drivingOrder = null): array
    {
        $response = $this->fixture('create_quotation_response');
        $stops = array_values($payload['stops']);
        $drivingOrder ??= array_keys($stops);

        $response['data']['stops'] = array_map(fn (int $i) => [
            'stopId' => 'STOP-' . $i,
            'coordinates' => isset($stops[$i]['coordinates']) ? [
                'lat' => number_format((float) $stops[$i]['coordinates']['lat'], 7, '.', ''),
                'lng' => number_format((float) $stops[$i]['coordinates']['lng'], 7, '.', ''),
            ] : ['lat' => '3.0000000', 'lng' => '101.0000000'],
            'address' => $stops[$i]['address'],
        ], $drivingOrder);

        $this->quotedStops = array_column($response['data']['stops'], null, 'stopId');

        return $response;
    }

    /**
     * Stops by stop ID from the last quotation, as Lalamove knows them.
     *
     * @var array<string, array>
     */
    protected array $quotedStops = [];

    /**
     * Lalamove's create order response for this order: each stop it was booked for, with the
     * recipient it was given there (as the real API echoes back).
     */
    protected function orderResponse(array $order): array
    {
        $response = $this->fixture('create_order_response');
        $response['data']['quotationId'] = $order['quotationId'];
        $response['data']['stops'] = array_map(fn (array $person) => array_merge(
            $this->quotedStops[$person['stopId']] ?? ['stopId' => $person['stopId']],
            ['name' => $person['name'] ?? null, 'phone' => $person['phone'] ?? null],
        ), array_merge([$order['sender']], $order['recipients']));

        return $response;
    }

    /**
     * Where a stored quotation's stops are, for orderResponse(), when it was not quoted in this test.
     */
    protected function rememberQuotedStops(LalamoveQuotation $quotation, array $stops): void
    {
        $this->quotedStops = [];

        foreach (array_values($stops) as $i => $stop) {
            $this->quotedStops[$quotation->stopIds[$i]] = ['stopId' => $quotation->stopIds[$i]] + $stop;
        }
    }

    protected function freshQuotation(): LalamoveQuotation
    {
        return new LalamoveQuotation(
            '3487460355004146198',
            ['3487460355004146199', '3487460355004146200'],
            Carbon::now()->addMinutes(5),
            new Price('24.6', 'MYR', ['base' => '4.0', 'extraMileage' => '20.6']),
            'MOTORCYCLE',
            36728,
        );
    }

    public function test_it_can_quote_without_booking()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload));
        $mockClient->shouldNotReceive('createOrder');

        $quotation = (new LalamoveDriver($mockClient))->quote($this->makeOrder('13993-000')->toConsignmentableArray('lalamove'));

        $this->assertSame('3487460355004146198', $quotation->id);
        $this->assertSame(['STOP-0', 'STOP-1'], $quotation->stopIds);
        $this->assertSame([0, 1], $quotation->routeSequence);
        $this->assertSame('24.6', $quotation->price->total);
        $this->assertSame('MYR', $quotation->price->currency);
        $this->assertSame(['base' => '4.0', 'extraMileage' => '20.6', 'totalExcludePriorityFee' => '24.6'], $quotation->price->breakdown);
        $this->assertSame(36728, $quotation->distanceMeters);
        $this->assertSame('MOTORCYCLE', $quotation->serviceType);
        $this->assertTrue($quotation->expiresAt->equalTo(Carbon::parse('2026-05-06T10:05:00Z')));
    }

    public function test_quote_throws_lalamove_error_message_when_there_is_no_quotation()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturn(['message' => 'ERR_INVALID_FIELD']);

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('ERR_INVALID_FIELD');

        (new LalamoveDriver($mockClient))->quote($this->makeOrder()->toConsignmentableArray('lalamove'));
    }

    public function test_create_consignment_books_with_the_given_quotation()
    {
        $quotation = $this->freshQuotation();

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldNotReceive('createQuotation');
        $mockClient->shouldReceive('createOrder')->once()
            ->withArgs(function (array $order) use ($quotation) {
                return $order['quotationId'] === $quotation->id
                    && $order['sender']['stopId'] === $quotation->stopIds[0]
                    && $order['recipients'][0]['stopId'] === $quotation->stopIds[1];
            })
            ->andReturnUsing(fn (array $order) => $this->orderResponse($order));

        $attributes = $this->makeOrder('13993-000')->toConsignmentableArray('lalamove');
        $this->rememberQuotedStops($quotation, $attributes['stops']);
        $orderId = (new LalamoveDriver($mockClient))->createConsignment($attributes + ['quotation' => $quotation]);

        $this->assertSame('3487384979687051627', $orderId);
    }

    public function test_create_consignment_accepts_a_stored_quotation_array()
    {
        $quotation = $this->freshQuotation();

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldNotReceive('createQuotation');
        $mockClient->shouldReceive('createOrder')->once()
            ->withArgs(fn (array $order) => $order['quotationId'] === $quotation->id)
            ->andReturnUsing(fn (array $order) => $this->orderResponse($order));

        $attributes = $this->makeOrder('13993-000')->toConsignmentableArray('lalamove');
        $this->rememberQuotedStops($quotation, $attributes['stops']);
        $orderId = (new LalamoveDriver($mockClient))->createConsignment($attributes + ['quotation' => $quotation->toArray()]);

        $this->assertSame('3487384979687051627', $orderId);
    }

    public function test_an_expired_quotation_is_refused_without_calling_lalamove()
    {
        $expired = new LalamoveQuotation('3487460355004146198', ['a', 'b'], Carbon::now()->subSecond(), null);

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldNotReceive('createQuotation');
        $mockClient->shouldNotReceive('createOrder');

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('has expired');

        $attributes = $this->makeOrder()->toConsignmentableArray('lalamove');
        (new LalamoveDriver($mockClient))->createConsignment($attributes + ['quotation' => $expired]);
    }

    public function test_the_lalamove_driver_is_quotable()
    {
        $driver = new LalamoveDriver(m::mock(Lalamove::class));

        $this->assertInstanceOf(Quotable::class, $driver);
        $this->assertNotInstanceOf(Quotable::class, $this->app->make('courier')->vendor('dhl-ecommerce'));
    }

    public function test_a_lalamove_quotation_is_a_quotation()
    {
        $quotation = $this->freshQuotation();

        $this->assertInstanceOf(Quotation::class, $quotation);
        $this->assertSame('24.6', $quotation->price->total);
        $this->assertFalse($quotation->isExpired());
    }

    public function test_a_quotation_from_another_courier_is_refused()
    {
        $foreign = new Quotation('OTHER-1', Carbon::now()->addMinutes(5), new Price('10', 'MYR'));

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldNotReceive('createQuotation');
        $mockClient->shouldNotReceive('createOrder');

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('Lalamove can only book with a quotation from LalamoveDriver::quote()');

        $attributes = $this->makeOrder()->toConsignmentableArray('lalamove');
        (new LalamoveDriver($mockClient))->createConsignment($attributes + ['quotation' => $foreign]);
    }

    public function test_a_v1_7_0_quotation_array_still_books()
    {
        $stored = [
            'id' => '3487460355004146198',
            'stopIds' => ['3487460355004146199', '3487460355004146200'],
            'expiresAt' => Carbon::now()->addMinutes(5)->toIso8601ZuluString(),
            'price' => ['total' => '24.6', 'currency' => 'MYR', 'breakdown' => []],
            'serviceType' => 'MOTORCYCLE',
            'distanceMeters' => 36728,
        ];

        $restored = LalamoveQuotation::fromArray($stored);

        $this->assertSame($stored['stopIds'], $restored->stopIds);
        $this->assertSame('24.6', $restored->price->total);
    }

    public function test_create_consignment_with_slip_reports_the_charged_price()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload));
        $mockClient->shouldReceive('createOrder')->once()->andReturnUsing(fn (array $order) => $this->orderResponse($order));

        $consignment = (new LalamoveDriver($mockClient))
            ->createConsignmentWithSlip($this->makeOrder('13993-000')->toConsignmentableArray('lalamove'));

        $this->assertInstanceOf(Price::class, $consignment->price);
        $this->assertSame('24.6', $consignment->price->total);
        $this->assertSame('MYR', $consignment->price->currency);
        $this->assertSame('4.0', $consignment->price->breakdown['base']);
    }

    public function test_a_quotation_survives_a_round_trip_through_an_array()
    {
        $quotation = $this->freshQuotation();

        $restored = LalamoveQuotation::fromArray(json_decode(json_encode($quotation->toArray()), true));

        $this->assertSame($quotation->id, $restored->id);
        $this->assertSame($quotation->stopIds, $restored->stopIds);
        $this->assertSame($quotation->price->toArray(), $restored->price->toArray());
        $this->assertSame($quotation->distanceMeters, $restored->distanceMeters);
        $this->assertSame($quotation->serviceType, $restored->serviceType);
        $this->assertSame($quotation->expiresAt->getTimestamp(), $restored->expiresAt->getTimestamp());
        $this->assertFalse($restored->isExpired());
    }

    public function test_cancel_consignment_or_fail_passes_on_lalamoves_reason()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('cancelOrderOrFail')->once()->with('3607496166826754985')
            ->andThrow(new CourierException('ERR_CANCELLATION: Cannot cancel order.', 422));

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('ERR_CANCELLATION: Cannot cancel order.');

        (new LalamoveDriver($mockClient))->cancelConsignmentOrFail('3607496166826754985');
    }

    public function test_change_driver_passes_the_driver_and_reason_on()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('changeDriver')->once()->with('3614557210216210614', '4960890', 'DRIVER_UNRESPONSIVE');

        (new LalamoveDriver($mockClient))->changeDriver('3614557210216210614', '4960890', 'DRIVER_UNRESPONSIVE');

        $this->addToAssertionCount(1);
    }

    public function test_cancelled_and_expired_statuses_describe_themselves()
    {
        $this->assertSame('Order was cancelled', ShipmentStatus::getDescription(ShipmentStatus::Cancelled));
        $this->assertSame('Order expired before a driver was found', ShipmentStatus::getDescription(ShipmentStatus::Expired));
    }

    /**
     * A pickup and three drop-offs, sent in a deliberately poor order (far, near, middle).
     */
    protected function multiDropAttributes(): array
    {
        return [
            'serviceType' => 'MOTORCYCLE',
            'stops' => [
                ['coordinates' => ['lat' => '3.00985952', 'lng' => '101.75597'], 'address' => 'Warehouse, Balakong'],
                ['coordinates' => ['lat' => '3.0738', 'lng' => '101.5183'], 'address' => 'Far, Shah Alam'],
                ['coordinates' => ['lat' => '2.9935', 'lng' => '101.7874'], 'address' => 'Near, Kajang'],
                ['coordinates' => ['lat' => '3.139', 'lng' => '101.6869'], 'address' => 'Middle, KLCC'],
            ],
            'sender' => ['stopIndex' => 0, 'name' => 'Warehouse', 'phone' => '+60123456789'],
            'recipients' => [
                ['stopIndex' => 1, 'name' => 'Far Recipient', 'phone' => '+60111111111', 'remarks' => 'Order FAR'],
                ['stopIndex' => 2, 'name' => 'Near Recipient', 'phone' => '+60122222222', 'remarks' => 'Order NEAR'],
                ['stopIndex' => 3, 'name' => 'Middle Recipient', 'phone' => '+60133333333', 'remarks' => 'Order MIDDLE'],
            ],
            'isRouteOptimized' => true,
        ];
    }

    public function test_recipients_keep_their_own_stop_when_lalamove_optimises_the_route()
    {
        $mockClient = m::mock(Lalamove::class);
        // Lalamove reorders to pickup, near, middle, far.
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload, [0, 2, 3, 1]));
        $mockClient->shouldReceive('createOrder')->once()
            ->withArgs(function (array $order) {
                $stopIdFor = collect($order['recipients'])->pluck('stopId', 'remarks');

                return $order['sender']['stopId'] === 'STOP-0'
                    && $stopIdFor['Order FAR'] === 'STOP-1'
                    && $stopIdFor['Order NEAR'] === 'STOP-2'
                    && $stopIdFor['Order MIDDLE'] === 'STOP-3';
            })
            ->andReturnUsing(fn (array $order) => $this->orderResponse($order));

        $orderId = (new LalamoveDriver($mockClient))->createConsignment($this->multiDropAttributes());

        $this->assertSame('3487384979687051627', $orderId);
    }

    public function test_the_quotation_records_the_driving_order_and_each_drop_off_number()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload, [0, 2, 3, 1]));

        $quotation = (new LalamoveDriver($mockClient))->quote($this->multiDropAttributes());

        $this->assertSame(['STOP-0', 'STOP-1', 'STOP-2', 'STOP-3'], $quotation->stopIds);
        $this->assertSame([0, 2, 3, 1], $quotation->routeSequence);
        $this->assertSame(3, $quotation->dropOffNumber(1), 'Far is the last drop-off.');
        $this->assertSame(1, $quotation->dropOffNumber(2), 'Near is the first drop-off.');
        $this->assertSame(2, $quotation->dropOffNumber(3));
        $this->assertNull($quotation->dropOffNumber(0), 'The pickup is not a drop-off.');
    }

    public function test_stops_without_coordinates_are_matched_by_address()
    {
        $attributes = $this->multiDropAttributes();
        unset($attributes['stops'][2]['coordinates']);

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload, [0, 2, 3, 1]));

        $quotation = (new LalamoveDriver($mockClient))->quote($attributes);

        $this->assertSame(['STOP-0', 'STOP-1', 'STOP-2', 'STOP-3'], $quotation->stopIds);
    }

    public function test_two_drop_offs_at_the_same_address_get_their_own_stops()
    {
        $attributes = $this->multiDropAttributes();
        $attributes['stops'][3] = $attributes['stops'][2];

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload, [0, 3, 2, 1]));

        $quotation = (new LalamoveDriver($mockClient))->quote($attributes);

        $this->assertCount(4, array_unique($quotation->stopIds));
    }

    public function test_a_quotation_whose_stops_do_not_match_the_request_is_refused()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturn($this->fixture('create_quotation_response'));
        $mockClient->shouldNotReceive('createOrder');

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('Nothing was booked.');

        (new LalamoveDriver($mockClient))->createConsignment($this->multiDropAttributes());
    }

    public function test_the_consignment_carries_lalamoves_tracking_link()
    {
        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturnUsing(fn (array $payload) => $this->quotationFor($payload));
        $mockClient->shouldReceive('createOrder')->once()->andReturnUsing(fn (array $order) => $this->orderResponse($order));

        $consignment = (new LalamoveDriver($mockClient))->createConsignmentWithSlip($this->makeOrder('13993-000')->toConsignmentableArray('lalamove'));

        $this->assertStringStartsWith('https://share.sandbox.lalamove.com?', $consignment->trackingUrl);
    }

    public function test_a_quotation_saved_before_route_order_was_recorded_still_loads()
    {
        $stored = $this->freshQuotation()->toArray();
        unset($stored['routeSequence']);

        $quotation = LalamoveQuotation::fromArray($stored);

        $this->assertSame([], $quotation->routeSequence);
        $this->assertNull($quotation->dropOffNumber(1));
    }
}
