<?php

namespace Nextbyte\Tests\Courier\Unit;

use Mockery as m;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Tests\Courier\Fixtures\LalamoveOrder;
use Nextbyte\Tests\Courier\TestCase;

/**
 * Integration tests that make live calls to the Lalamove sandbox/production API.
 * Requires LALAMOVE_API_KEY and LALAMOVE_API_SECRET in .env.
 */
class LalamoveApiTest extends TestCase
{
    protected $driverName = 'lalamove';

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    protected function makeOrder(string $orderNumber, string $name = 'Michal'): LalamoveOrder
    {
        return new LalamoveOrder($orderNumber, $name);
    }

    protected function randomOrderNumber(string $prefix = 'ORD-LA'): string
    {
        $faker = \Faker\Factory::create('ms_MY');

        return $prefix . '-' . sprintf('%04d', $faker->numberBetween(1, 99999));
    }

    protected function makeMultiStopOrder(string $orderNumber): LalamoveOrder
    {
        $order = $this->makeOrder($orderNumber);

        $order->set('stops', [
            [
                'coordinates' => ['lat' => '3.1578', 'lng' => '101.7118'],
                'address' => 'Suria KLCC, Kuala Lumpur City Centre, 50088 Kuala Lumpur',
            ],
            [
                'coordinates' => ['lat' => '3.1306', 'lng' => '101.6838'],
                'address' => 'Bangsar Shopping Centre, 285, Jalan Maarof, 59000 Kuala Lumpur',
            ],
            [
                'coordinates' => ['lat' => '3.0738', 'lng' => '101.6066'],
                'address' => 'Sunway Pyramid, 3, Jalan PJS 11/15, Bandar Sunway, 47500 Subang Jaya',
            ],
        ]);

        $order->set('recipients', [
            [
                'stopIndex' => 1,
                'name' => 'Katrina',
                'phone' => '+60198765432',
                'remarks' => 'First drop-off',
            ],
            [
                'stopIndex' => 2,
                'name' => 'Ahmad',
                'phone' => '+60112345678',
                'remarks' => 'Second drop-off',
            ],
        ]);

        return $order;
    }

    public function test_it_can_create_consignment()
    {
        $courier = $this->makeDriver($this->driverName);

        $faker = \Faker\Factory::create('ms_MY');
        $orderNumber = $this->randomOrderNumber();
        $order = $this->makeOrder($orderNumber, $faker->name);

        $orderId = $courier->createConsignment($order->toConsignmentableArray($this->driverName));

        dump($orderId);

        $this->assertNotEmpty($orderId);
        $this->assertIsString($orderId);
    }

    public function test_it_can_create_consignment_with_slip()
    {
        $courier = $this->makeDriver($this->driverName);

        $faker = \Faker\Factory::create('ms_MY');
        $orderNumber = $this->randomOrderNumber();
        $order = $this->makeOrder($orderNumber, $faker->name);

        $consignment = $courier->createConsignmentWithSlip($order->toConsignmentableArray($this->driverName));

        dump($consignment->number);

        $this->assertNotEmpty($consignment->number);
        $this->assertEquals($orderNumber, $consignment->orderNumber);
        $this->assertIsArray($consignment->slips);
        $this->assertEmpty($consignment->slips);
        $this->assertEquals([$consignment->number], $consignment->trackingNumbers);
    }

    public function test_it_can_create_consignment_with_special_requests()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeOrder('ORD-LA-SPECIAL-001');
        $order->set('specialRequests', ['ROUND_TRIP']);

        $orderId = $courier->createConsignment($order->toConsignmentableArray($this->driverName));

        dump($orderId);

        $this->assertNotEmpty($orderId);
    }

    public function test_it_can_get_consignment_details()
    {
        $courier = $this->makeDriver($this->driverName);

        $faker = \Faker\Factory::create('ms_MY');
        $orderNumber = $this->randomOrderNumber();
        $order = $this->makeOrder($orderNumber, $faker->name);

        $orderId = $courier->createConsignment($order->toConsignmentableArray($this->driverName));
        $this->assertNotEmpty($orderId);

        $consignment = $courier->consignment($orderId);

        dump($consignment->status);

        $this->assertEquals($orderId, $consignment->number);
        $this->assertEquals($orderNumber, $consignment->orderNumber);
        $this->assertContains($consignment->status, [
            ShipmentStatus::Pending,
            ShipmentStatus::Accepted,
            ShipmentStatus::Pickup,
            ShipmentStatus::Delivered,
        ]);
        $this->assertNotEmpty($consignment->shipments);
        $this->assertEquals([$orderId], $consignment->trackingNumbers);
    }

    public function test_it_can_get_last_shipment_for_multiple_orders()
    {
        $courier = $this->makeDriver($this->driverName);

        $faker = \Faker\Factory::create('ms_MY');

        $orderId1 = $courier->createConsignment(
            $this->makeOrder($this->randomOrderNumber(), $faker->name)->toConsignmentableArray($this->driverName)
        );
        $orderId2 = $courier->createConsignment(
            $this->makeOrder($this->randomOrderNumber(), $faker->name)->toConsignmentableArray($this->driverName)
        );

        $shipments = $courier->getConsignmentsLastShipment([$orderId1, $orderId2]);

        $this->assertCount(2, $shipments);
        $this->assertTrue($shipments->has($orderId1));
        $this->assertTrue($shipments->has($orderId2));
        $this->assertNotEmpty($shipments->get($orderId1)->getStatus());
    }

    public function test_it_can_create_multi_stop_consignment()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeMultiStopOrder($this->randomOrderNumber('ORD-LA-MULTI'));

        $orderId = $courier->createConsignment($order->toConsignmentableArray($this->driverName));

        dump($orderId);

        $this->assertNotEmpty($orderId);
        $this->assertIsString($orderId);

        $consignment = $courier->consignment($orderId);

        $this->assertEquals($orderId, $consignment->number);
        $this->assertNotEmpty($consignment->shipments);
    }

    public function test_it_can_create_multi_stop_consignment_with_route_optimized()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = $this->makeMultiStopOrder($this->randomOrderNumber('ORD-LA-MULTI-OPT'));
        $order->set('isRouteOptimized', true);

        $orderId = $courier->createConsignment($order->toConsignmentableArray($this->driverName));

        dump($orderId);

        $this->assertNotEmpty($orderId);
        $this->assertIsString($orderId);

        $consignment = $courier->consignment($orderId);

        $this->assertEquals($orderId, $consignment->number);
        $this->assertNotEmpty($consignment->shipments);
    }

    public function test_it_can_register_webhook_url()
    {
        $webhookUrl = config('courier.lalamove.webhook_url');

        if (empty($webhookUrl)) {
            $this->markTestSkipped('LALAMOVE_WEBHOOK_URL is not set in .env');
        }

        /** @var \Nextbyte\Courier\Drivers\Lalamove\LalamoveDriver $courier */
        $courier = $this->makeDriver($this->driverName);

        try {
            $result = $courier->setWebhookUrl($webhookUrl);

            dump($result);

            // Lalamove returns an empty body (204) or an ack object on success
            $this->assertTrue(is_array($result));
        } catch (\Nextbyte\Courier\Exceptions\CourierException $e) {
            // Lalamove validates the URL by making a live test call to it and expecting a 200.
            // In local/CI environments the webhook URL may not be publicly reachable.
            // Use ngrok or a real public URL for full validation of this test.
            if (str_contains($e->getMessage(), 'ERR_INVALID_RESPONSE') || str_contains($e->getMessage(), '422')) {
                $this->markTestSkipped('Webhook URL is not publicly reachable by Lalamove (use ngrok for local testing): ' . $e->getMessage());
            }

            throw $e;
        }
    }

    public function test_it_can_cancel_consignment()
    {
        $courier = $this->makeDriver($this->driverName);

        $faker = \Faker\Factory::create('ms_MY');
        $order = $this->makeOrder($this->randomOrderNumber('ORD-LA-CANCEL'), $faker->name);

        $orderId = $courier->createConsignment($order->toConsignmentableArray($this->driverName));
        $this->assertNotEmpty($orderId);

        $result = $courier->cancelConsignment($orderId);

        $this->assertTrue($result);
    }
}
