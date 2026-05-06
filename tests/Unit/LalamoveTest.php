<?php

namespace Nextbyte\Tests\Courier\Unit;

use Illuminate\Http\JsonResponse;
use Mockery as m;
use Nextbyte\Courier\Clients\Lalamove\Lalamove;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveDriver;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveTrackingResponse;
use Nextbyte\Courier\Enums\ShipmentStatus;
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
            'canceled maps to ReturnStart'           => ['CANCELED', ShipmentStatus::ReturnStart],
            'rejected maps to Rejected'              => ['REJECTED', ShipmentStatus::Rejected],
            'expired maps to OnHold'                 => ['EXPIRED', ShipmentStatus::OnHold],
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
        $orderFixture = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/create_order_response.json'),
            true
        );

        $mockClient = m::mock(Lalamove::class);
        $mockClient->shouldReceive('createQuotation')->once()->andReturn($quotationFixture);
        $mockClient->shouldReceive('createOrder')->once()->andReturn($orderFixture);

        $driver = new LalamoveDriver($mockClient);
        $consignment = $driver->createConsignmentWithSlip($this->makeOrder('13993-000')->toConsignmentableArray('lalamove'));

        $this->assertEquals([$orderId], $consignment->trackingNumbers);
        $this->assertEquals($orderId, $consignment->number);
        $this->assertEquals('13993-000', $consignment->orderNumber);
        $this->assertIsArray($consignment->slips);
        $this->assertEmpty($consignment->slips);
    }
}
