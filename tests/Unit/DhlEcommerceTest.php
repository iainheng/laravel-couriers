<?php

namespace Nextbyte\Tests\Courier\Unit;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Mockery as m;
use Nextbyte\Courier\Drivers\DhlEcommerce\DhlEcommerceDriver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\ShipmentStatusPush;
use Nextbyte\Tests\Courier\Fixtures\DhlEcommerceOrder;
use Nextbyte\Tests\Courier\TestCase;

class DhlEcommerceTest extends TestCase
{
    protected $driverName = 'dhl-ecommerce';

    protected $accessToken = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessToken = [
            'token' => '2c1619e67d7342b0a97d21668341d0d8',
            'expires' => Carbon::now()->addSeconds(56184),
        ];
    }

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    protected function defaultConfig(): array
    {
        return ['access_token' => $this->accessToken, 'debug' => true];
    }

    public function test_it_return_correct_driver()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->assertInstanceOf(DhlEcommerceDriver::class, $courier);
    }

    public function test_it_can_create_consignment()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $orderNumber = 'ORD-10100TEST';

        $order = new DhlEcommerceOrder($orderNumber, 'Jane Doe');

        $consignmentNo = $courier->createConsignment($order->toConsignmentableArray('dhl-ecommerce'));

        dump($consignmentNo);

        $this->assertNotEmpty($consignmentNo);
    }

    public function test_it_can_create_consignment_and_get_consignment_image()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $orderNumber = 'ORD-10030TEST';

        $order = new DhlEcommerceOrder($orderNumber, 'Jane Doe');

        $consignment = $courier->createConsignmentWithSlip($order->toConsignmentableArray('dhl-ecommerce'));

        // save consignment waybill/slip to disk if available
//        if (!empty($consignment->slip)) {
//            file_put_contents($consignment->slip->getName(), $consignment->slip->getBody());
//        }

        $this->assertObjectHasAttribute('number', $consignment);

        $consignmentFile = Arr::first(data_get($consignment, 'slips', []));

        $this->assertEquals('png', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
        $this->assertCount(1, $consignment->trackingNumbers);
    }

    public function test_it_can_get_consignment_slip_using_consignmentable()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('ORD-10030TEST', 'Jane Doe');

        $consignmentFiles = $courier->getConsignmentableSlips($order);

        $consignmentFile = Arr::first($consignmentFiles);

        $this->assertEquals('png', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_multiple_consignment_slip_when_order_has_multiple_items()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('MYLILORD-10033TEST', 'Jane Doe');

        $order->set('bd.shipmentItems.0.shipmentPieces', array_merge($order->get('bd.shipmentItems.0.shipmentPieces'), [
                ['pieceID' => 2]
            ]))->set('bd.shipmentItems.0.isMult', 'true');

        $consignment = $courier->createConsignmentWithSlip($order->toConsignmentableArray('dhl-ecommerce'));

//        // save consignment waybill/slip to disk if available
//        if (!empty($consignment->slip)) {
//            file_put_contents($consignment->slip->getName(), $consignment->slip->getBody());
//        }

        dump($consignment->trackingNumbers);

        $slip = Arr::first($consignment->slips);

        $this->assertEquals('png', $slip->getExtension());
        $this->assertNotEmpty($slip->getBody());
        $this->assertCount(2, $consignment->trackingNumbers);
        $this->assertCount(2, $consignment->slips);
    }

    public function test_it_can_reprint_consignment_slip()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('MYLILORD-10033TEST', 'Jane Doe');

        $order->set('bd.shipmentItems.0.shipmentPieces', array_merge($order->get('bd.shipmentItems.0.shipmentPieces'), [
                ['pieceID' => 2]
            ]))->set('bd.shipmentItems.0.isMult', 'true');

        $consignmentFile = $courier->getConsignmentableSlip($order);

        $this->assertEquals('png', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_shipments_details()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $consignment = $courier->consignment('7327071895094004');
//        $consignment = $courier->consignment('ORD-10007TEST-1');

        $this->assertEquals(ShipmentStatus::Accepted, $consignment->status);
        $this->assertStringContainsStringIgnoringCase('Puchong', optional($consignment->shipments->first())->getLocation());
    }

    public function test_it_can_push_update_order_shipment_status()
    {
        /**@var $courier DhlEcommerceDriver */
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('ORD-10002TEST', 'Jane Doe');

        $pushData = json_decode(file_get_contents('../Fixtures/data/dhl-ecommerce/shipment_push_request.json'),
            true);

        $response = $courier->pushShipmentStatus(function (ShipmentStatusPush $push) use ($order) {
            $order->setShipmentStatus($push->getStatus());
            return $order;
        }, $pushData);

        dump($response->content());

        $json = json_decode($response->content());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, data_get($json,'pushTrackingResponse.bd.shipmentItems.0.responseStatus.code'));
        $this->assertEquals(ShipmentStatus::DeliveryRefused, $order->getShipmentStatus());
    }
}
