<?php

namespace Nextbyte\Tests\Courier\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mockery as m;
use Nextbyte\Courier\Drivers\BestExpress\BestExpressDriver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Tests\Courier\Fixtures\BestExpressOrder;
use Nextbyte\Tests\Courier\TestCase;

class BestExpressTest extends TestCase
{
    protected $driverName = 'BestExpress';

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    protected function makeConsignmentRequestParams()
    {
        $faker = \Faker\Factory::create('ms_MY');

//        $townState = $faker->townState();
//        $stateParams = $this->splitFakerTownState($townState);

        $state = 'perak';

        $stateParams = [
            'postcode' => 47810,
            'city' => $faker->city(),
            'state' => $faker->state(),
        ];

        return [
            "customerName" => " YiNian Fashion (Shanghai) Co., Ltd. - Shanghai O2O ",
            "txLogisticId" => 'ORD-'. sprintf('%04d', $faker->numberBetween(1, 99999)),
            "serviceType" => "1",
            "recSite" => "000003",
            "goodsValue" => "100",
            "itemsValue" => "0",
            "insuranceValue" => "0",
            "special" => "0",
            "certificateType" => "01",
            "certificateNo" => "1769900274531",
            "sender" => [
                "name" => "sender",
                "postCode" => "10254",
                "mobile" => "13668122696",
                "prov" => "Selangor ",
                "city" => " Petaling",
                "county" => "Subang Jaya",
                "address" => "123",
                "email" => "kkk@email.com",
                "country" => "01"
            ],
            "receiver" => [
                "name" => "receiver",
                "postCode" => "50110",
                "mobile" => "13927089988",
                "prov" => "Selangor",
                "city" => " Ulu Langat ",
                "county" => "Cheras",
                "address" => "456",
                "email" => "kkk@email.com",
                "country" => "01"
            ],
            "items" => [
                "item" => [
                    [
                        "itemName" => $faker->linuxPlatformToken
                    ]
                ]
            ],
            "itemsWeight" => rand(1, 5),
            "piece" => "1",
            "remark" => ""
        ];
    }


    public function test_it_return_correct_driver()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->assertInstanceOf(BestExpressDriver::class, $courier);
    }

    public function test_it_can_create_consignment()
    {
        $courier = $this->makeDriver($this->driverName);

        $consignment = $this->makeConsignmentRequestParams();

        $consignmentNo = $courier->createConsignment($consignment);

        dump($consignmentNo);

        $this->assertIsNumeric($consignmentNo);
    }

    public function test_it_can_create_consignment_and_get_consignment_image()
    {
        $courier = $this->makeDriver($this->driverName);

        $attributes = $this->makeConsignmentRequestParams();

//        dump($attributes);
        $attributes['txLogisticId'] = 'ORD-9999';
        $consignment = $courier->createConsignmentWithSlip($attributes);

        $this->assertObjectHasAttribute('number', $consignment);

        $consignmentFile = data_get($consignment, 'slip');

        $this->assertEquals('pdf', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_slip_using_consignmentable()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = new BestExpressOrder('ORD-9999', 'Jane Doe');

        $consignmentFile = $courier->getConsignmentableSlip($order);

//        dump($consignmentFile);

        $this->assertEquals('pdf', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_shipments_details()
    {
        $courier = $this->makeDriver($this->driverName);

        $consignment = $courier->consignment('MY98000781824');

        $this->assertEquals(ShipmentStatus::Delivered, $consignment->status);
        $this->assertEquals('Port Klang', optional($consignment->shipments->first())->location);
    }

    public function test_it_can_get_last_shipment_status()
    {
        $courier = $this->makeDriver($this->driverName);

        $shipments = $courier->getConsignmentsLastShipment([
            "MY98000781824",
            "MY82108919154"
        ]);

        $this->assertCount(2, $shipments);
        $this->assertEquals(ShipmentStatus::Delivered, data_get($shipments->get('MY98000781824'), 'status'));
    }
}
