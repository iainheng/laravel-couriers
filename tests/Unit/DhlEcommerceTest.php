<?php

namespace Nextbyte\Tests\Courier\Unit;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Mockery as m;
use Nextbyte\Courier\Drivers\DhlEcommerce\DhlEcommerceDriver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\ShipmentStatusPush;
use Nextbyte\Tests\Courier\Fixtures\DhlEcommerceOrder;
use Nextbyte\Tests\Courier\TestCase;

class DhlEcommerceTest extends TestCase
{
    protected $driverName = 'DhlEcommerce';

    protected $accessToken = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessToken = [
            'token' => 'f71818256b094213a36dcd27d207fef5',
            'expires' => Carbon::now()->addSeconds(60451),
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

        $orderNumber = 'ORD-10010TEST';

        $order = new DhlEcommerceOrder($orderNumber, 'Jane Doe');

        $consignmentNo = $courier->createConsignment($order->toConsignmentableArray('dhl-ecommerce'));

        dump($consignmentNo);

        $this->assertNotEmpty($consignmentNo);
    }

    public function test_it_can_create_consignment_and_get_consignment_image()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $orderNumber = 'ORD-10009TEST';

        $order = new DhlEcommerceOrder($orderNumber, 'Jane Doe');

        $consignment = $courier->createConsignmentWithSlip($order->toConsignmentableArray('dhl-ecommerce'));

        $this->assertObjectHasAttribute('number', $consignment);

        $consignmentFile = data_get($consignment, 'slip');

        $this->assertEquals('png', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_slip_using_consignmentable()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('ORD-10002TEST', 'Jane Doe');

        $consignmentFile = $courier->getConsignmentableSlip($order);

        $this->assertEquals('png', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_multiple_consignment_slip_when_order_has_multiple_items()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('ORD-10013TEST', 'Jane Doe');

        $order->set('bd.shipmentItems.0.shipmentPieces', array_merge($order->get('bd.shipmentItems.0.shipmentPieces'), [
                ['pieceID' => 2]
            ]))->set('bd.shipmentItems.0.isMult', 'true');

        $consignment = $courier->createConsignmentWithSlip($order->toConsignmentableArray('dhl-ecommerce'));

        $this->assertEquals('zip', $consignment->slip->getExtension());
        $this->assertNotEmpty($consignment->slip->getBody());
    }

    public function test_it_can_reprint_consignment_slip()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('03825-00', 'Jane Doe');

        $order->set('bd.shipmentItems.0.shipmentPieces', array_merge($order->get('bd.shipmentItems.0.shipmentPieces'), [
                ['pieceID' => 2]
            ]))->set('bd.shipmentItems.0.isMult', 'true');

        $consignmentFile = $courier->getConsignmentableSlip($order);

        $this->assertEquals('zip', $consignmentFile->getExtension());
        $this->assertNotEmpty($consignmentFile->getBody());
    }

    public function test_it_can_get_consignment_shipments_details()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $consignment = $courier->consignment('7127014431174024');
//        $consignment = $courier->consignment('ORD-10007TEST-1');

        $this->assertEquals(ShipmentStatus::Accepted, $consignment->status);
        $this->assertStringContainsStringIgnoringCase('Puchong', optional($consignment->shipments->first())->getLocation());
    }

    public function test_it_can_push_update_order_shipment_status()
    {
        /**@var $courier DhlEcommerceDriver */
        $courier = $this->makeDriver($this->driverName);

        $order = new DhlEcommerceOrder('ORD-10002TEST', 'Jane Doe');

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
        /**@var $courier DhlEcommerceDriver */
        $courier = $this->makeDriver($this->driverName);

        $order = new DhlEcommerceOrder('ORD-10002TEST', 'Jane Doe');

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
}
