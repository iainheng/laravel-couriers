<?php

namespace Nextbyte\Tests\Courier\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mockery as m;
use Nextbyte\Courier\Drivers\Gdex\GdexDriver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Tests\Courier\Fixtures\BestExpressOrder;
use Nextbyte\Tests\Courier\Fixtures\GdexOrder;
use Nextbyte\Tests\Courier\TestCase;

class GdexTest extends TestCase
{
    protected $driverName = 'Gdex';

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_return_correct_driver()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->assertInstanceOf(GdexDriver::class, $courier);
    }

    public function test_it_can_create_consignment()
    {
        $courier = $this->makeDriver($this->driverName);

        $faker = \Faker\Factory::create('ms_MY');

        $orderId = 'ORD-'. sprintf('%04d', $faker->numberBetween(1, 99999));

        $order = new GdexOrder($orderId, $faker->name);

        $response = $courier->createConsignment($order->toConsignmentableArray($this->driverName));

        $this->assertStringStartsWith('TCN', $response);
    }

    public function test_it_can_create_consignment_and_get_consignment_image()
    {
        $courier = $this->makeDriver($this->driverName);

        $faker = \Faker\Factory::create('ms_MY');

        $orderId = 'ORD-'. sprintf('%04d', $faker->numberBetween(1, 99999));

        $order = new GdexOrder($orderId, $faker->name);

        $consignmentNo = $courier->createConsignment($order->toConsignmentableArray($this->driverName));

        $this->assertNotEmpty($consignmentNo);
        $this->assertStringStartsWith('TCN', $consignmentNo);

        $consignmentFile = $courier->getConsignmentSlip($consignmentNo);

        $this->assertEquals('zip', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_slip_using_consignmentable()
    {
        $faker = \Faker\Factory::create('ms_MY');

        $courier = $this->makeDriver($this->driverName);

        $orderId = 'ORD-'. sprintf('%04d', $faker->numberBetween(1, 99999));

        $order = new GdexOrder($orderId, $faker->name);

        $consignmentNo = $courier->createConsignment($order->toConsignmentableArray($this->driverName));

        $order->setConsignmentNumbers([$consignmentNo]);

        $consignmentFile = $courier->getConsignmentableSlip($order);

        dump($consignmentFile);

        $this->assertEquals('zip', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_shipments_details()
    {
        $courier = $this->makeDriver($this->driverName);

        $consignment = $courier->consignment('MY98000781824');

        $this->assertEquals(ShipmentStatus::Delivered, $consignment->status);
        $this->assertEquals('Port Klang', optional($consignment->shipments->first())->getLocation());
    }

    public function test_it_can_get_last_shipment_status()
    {
        $courier = $this->makeDriver($this->driverName);

        $shipments = $courier->getConsignmentsLastShipment([
            "MY98000781824",
            "MY82108919154"
        ]);

        $this->assertCount(2, $shipments);

        $shipment = $shipments->get('MY98000781824');

        $this->assertEquals(ShipmentStatus::Delivered, $shipment->getStatus());
    }
}
