<?php

namespace Nextbyte\Tests\Courier\Unit;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Mockery as m;
use Nextbyte\Courier\Drivers\BestExpress\BestExpressDriver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\ShipmentStatusPush;
use Nextbyte\Tests\Courier\Fixtures\BestExpressOrder;
use Nextbyte\Tests\Courier\TestCase;
use Symfony\Component\HttpFoundation\Response;

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
        $attributes['txLogisticId'] = 'ORD-99999TEST';
        $consignment = $courier->createConsignmentWithSlip($attributes);

        $this->assertObjectHasAttribute('number', $consignment);

        $consignmentFile = Arr::first(data_get($consignment, 'slips'));

        $this->assertEquals('pdf', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_slip_using_consignmentable()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = new BestExpressOrder('ORD-10002TEST', 'Jane Doe');

        $consignmentFile = $courier->getConsignmentableSlip($order);

        $this->assertEquals('pdf', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_multiple_consignment_slip_when_order_has_multiple_items()
    {
        $courier = $this->makeDriver($this->driverName);

        $order = new BestExpressOrder('ORD-10003TEST', 'Jane Doe');

        $order->set('items.item', array_merge($order->get('items.item'), [
                ["itemName" => "Another item"]
            ]))
            ->set('piece', 2);

        $consignmentFiles = $courier->getConsignmentableSlips($order);

        $consignmentFile = Arr::first($consignmentFiles);

        $this->assertEquals('pdf', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
        $this->assertCount(2, $consignmentFiles);
    }

    public function test_it_can_push_update_order_shipment_status()
    {
        /**@var $courier BestExpressDriver */
        $courier = $this->makeDriver($this->driverName);

        $order = new BestExpressOrder('ORD-10002TEST', 'Jane Doe');

        $pushData = json_decode(file_get_contents('../Fixtures/data/best-express/shipment_push_request.json'),
            true);

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order) {
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $pushData);

        dump($response->content());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(true, data_get(json_decode($response->content()), 'result'));
        $this->assertEquals(ShipmentStatus::Accepted, $order->getShipmentStatus());
    }

    public function test_it_can_push_update_order_shipment_status_with_raw_request()
    {
        /**@var $courier BestExpressDriver */
        $courier = $this->makeDriver($this->driverName);

        $order = new BestExpressOrder('ORD-10002TEST', 'Jane Doe');

        $pushData = json_decode(file_get_contents('../Fixtures/data/best-express/raw_shipment_push_request.json'),
            true);

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order) {
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $pushData);

        dump($response->content());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(true, data_get(json_decode($response->content()), 'result'));
        $this->assertEquals(ShipmentStatus::OutForDelivery, $order->getShipmentStatus());
    }

    public function test_it_can_get_consignment_shipments_details()
    {
        $courier = $this->makeDriver($this->driverName);

        $consignment = $courier->consignment('60710185826000');

        $this->assertEquals(ShipmentStatus::Delivered, $consignment->status);
        $this->assertEquals('Port Klang', optional($consignment->shipments->first())->location);
    }
}
