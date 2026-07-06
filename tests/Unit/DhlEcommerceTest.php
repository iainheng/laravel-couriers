<?php

namespace Nextbyte\Tests\Courier\Unit;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Mockery as m;
use Nextbyte\Courier\Clients\DhlEcommerce\DhlEcommerce;
use Nextbyte\Courier\Clients\DhlEcommerce\Responses\TrackingItemResponse;
use Nextbyte\Courier\Drivers\DhlEcommerce\DhlEcommerceDriver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\ProofOfDelivery;
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
            'token' => 'c9fe593442654a94a897cc6e5e201970',
            'expires' => Carbon::now()->addSeconds(63255),
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

        $orderNumber = 'ORD-' . now()->format('YmdHis') . 'TEST';

        $order = new DhlEcommerceOrder($orderNumber, 'Jane Doe');

        $consignmentNo = $courier->createConsignment($order->toConsignmentableArray('dhl-ecommerce'));

        dump($consignmentNo);

        $this->assertNotEmpty($consignmentNo);
    }

    public function test_it_can_create_consignment_and_get_consignment_image()
    {
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $orderNumber = 'ORD-' . now()->format('YmdHis') . 'TEST';

        $order = new DhlEcommerceOrder($orderNumber, 'Jane Doe');

        $consignment = $courier->createConsignmentWithSlip($order->toConsignmentableArray('dhl-ecommerce'));

        // save consignment waybill/slip to disk if available
//        if (!empty($consignment->slip)) {
//            file_put_contents($consignment->slip->getName(), $consignment->slip->getBody());
//        }

        $this->assertObjectHasProperty('number', $consignment);

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

        $order = new DhlEcommerceOrder('MYLILORD-' . now()->format('YmdHis') . 'TEST', 'Jane Doe');

        $order->set('bd.shipmentItems.0.shipmentPieces', array_merge($order->get('bd.shipmentItems.0.shipmentPieces'), [
                ['pieceID' => 2]
            ]))->set('bd.shipmentItems.0.isMult', 'true');

        $data = $order->toConsignmentableArray('dhl-ecommerce');

//        $data = array(
//            'bd' => array(
//                'pickupAddress' => array(
//                    'companyName' => 'Empire Hamper & Gift Sdn Bhd',
//                    'name' => 'Empire Hamper & Gift Sdn Bhd',
//                    'address1' => 'No 9, Jalan Alfa Impian 1',
//                    'address2' => 'Taman Perindustrian Alfa Impian',
//                    'postCode' => '43300',
//                    'city' => 'Balakong',
//                    'state' => 'Selangor',
//                    'phone' => '0129499667',
//                    'country' => 'MY',
//                    'email' => 'empirehamper.order@gmail.com',
//                ),
//                'shipperAddress' => array(
//                    'companyName' => 'Empire Hamper & Gift Sdn Bhd',
//                    'name' => 'Empire Hamper & Gift Sdn Bhd',
//                    'address1' => 'No 9, Jalan Alfa Impian 1',
//                    'address2' => 'Taman Perindustrian Alfa Impian',
//                    'postCode' => '43300',
//                    'city' => 'Balakong',
//                    'state' => 'Selangor',
//                    'phone' => '0129499667',
//                    'country' => 'MY',
//                    'email' => 'empirehamper.order@gmail.com',
//                ),
//                'handoverMethod' => 2,
//                'shipmentItems' => array(
//                    0 => array(
//                        'consigneeAddress' => array(
//                            'name' => 'ELYCIA KOI ',
//                            'address1' => '1MILE,BERTAM VALLEY,RINGLET',
//                            'address2' => '',
//                            'country' => 'MY',
//                            'city' => 'CAMERON HIGHLANDS',
//                            'state' => 'Pahang',
//                            'postCode' => '39200',
//                            'phone' => '0192244618',
//                            'email' => 'hryamrealty@yahoo.cmo.my',
//                        ),
//                        'packageDesc' => 'Hamper',
//                        'shipmentID' => 'MYLIL06256-00',
//                        'returnMode' => '01',
//                        'totalWeight' => 2000.0,
//                        'totalWeightUOM' => 'G',
//                        'productCode' => 'PDO',
//                        'currency' => 'MYR',
//                        'isMult' => 'true',
//                        'deliveryOption' => 'C',
//                        'shipmentPieces' => array(
//                            0 => array(
//                                'pieceID' => 1,
//                                'announcedWeight' => array('weight' => 2000.0, 'unit' => 'G',),
//                                'codAmount' => null,
//                                'insuranceAmount' => null,
//                                'billingReference1' => null,
//                                'billingReference2' => null,
//                                'pieceDescription' => 'Elegant Diwali Hamper 2024 DGDV23',
//                            ),
//                            1 => array(
//                                'pieceID' => 2,
//                                'announcedWeight' => array('weight' => 2000.0, 'unit' => 'G',),
//                                'codAmount' => null,
//                                'insuranceAmount' => null,
//                                'billingReference1' => null,
//                                'billingReference2' => null,
//                                'pieceDescription' => 'Elegant Diwali Hamper 2024 DGDV23',
//                            ),
//                            2 => array(
//                                'pieceID' => 3,
//                                'announcedWeight' => array('weight' => 2000.0, 'unit' => 'G',),
//                                'codAmount' => null,
//                                'insuranceAmount' => null,
//                                'billingReference1' => null,
//                                'billingReference2' => null,
//                                'pieceDescription' => 'Elegant Diwali Hamper 2024 DGDV23',
//                            ),
//                            3 => array(
//                                'pieceID' => 4,
//                                'announcedWeight' => array('weight' => 2000.0, 'unit' => 'G',),
//                                'codAmount' => null,
//                                'insuranceAmount' => null,
//                                'billingReference1' => null,
//                                'billingReference2' => null,
//                                'pieceDescription' => 'Elegant Diwali Hamper 2024 DGDV23',
//                            ),
//                            4 => array(
//                                'pieceID' => 5,
//                                'announcedWeight' => array('weight' => 2000.0, 'unit' => 'G',),
//                                'codAmount' => null,
//                                'insuranceAmount' => null,
//                                'billingReference1' => null,
//                                'billingReference2' => null,
//                                'pieceDescription' => 'Elegant Diwali Hamper 2024 DGDV23',
//                            ),
//                        ),
//                        'remarks' => '5xDGDV23 ',
//                    ),
//                ),
//                'label' => array('pageSize' => '400x600', 'format' => 'PNG', 'layout' => '1x1',),
//            ),
//        );

        $consignment = $courier->createConsignmentWithSlip($data);

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

        // Create a fresh consignment so we have a tracking number that exists in the
        // sandbox, then query its shipment details.
        $orderNumber = 'ORD-' . now()->format('YmdHis') . 'TEST';
        $order = new DhlEcommerceOrder($orderNumber, 'Jane Doe');
        $trackingNumber = $courier->createConsignment($order->toConsignmentableArray('dhl-ecommerce'));

        $consignment = $courier->consignment($trackingNumber);

        $this->assertEquals(ShipmentStatus::Accepted, $consignment->status);
        $this->assertStringContainsStringIgnoringCase('Puchong', optional($consignment->shipments->first())->getLocation());
    }

    public function test_it_can_capture_proof_of_delivery_from_tracking()
    {
        $courier = $this->makeDhlDriverWithTrackingResponse('trackitem_full_response.json');

        $pod = $courier->getProofOfDelivery('7228038086003024');

        $this->assertInstanceOf(ProofOfDelivery::class, $pod);
        $this->assertEquals('zakwan', $pod->recipientName);

        $this->assertTrue($pod->hasSignature());
        $this->assertEquals('jpg', $pod->signature->getExtension());
        $this->assertNotEmpty($pod->signature->getBody());

        $this->assertTrue($pod->hasImage());
        $this->assertStringContainsString('cloudfront.net', $pod->imageUrl);

        $this->assertInstanceOf(Carbon::class, $pod->deliveredAt);
    }

    public function test_it_exposes_proof_of_delivery_on_the_consignment()
    {
        $courier = $this->makeDhlDriverWithTrackingResponse('trackitem_full_response.json');

        $consignment = $courier->consignment('7228038086003024');

        $this->assertInstanceOf(ProofOfDelivery::class, $consignment->proofOfDelivery);
        $this->assertEquals(ShipmentStatus::Delivered, $consignment->status);
    }

    public function test_it_returns_null_proof_of_delivery_when_shipment_not_delivered()
    {
        $courier = $this->makeDhlDriverWithTrackingResponse('trackitem_response.json');

        $this->assertNull($courier->getProofOfDelivery('7027010899621494'));
    }

    /**
     * Build a DHL driver whose client returns the given tracking fixture, so
     * proof of delivery parsing can be tested without hitting the live API.
     *
     * @param string $fixture
     * @return DhlEcommerceDriver
     */
    protected function makeDhlDriverWithTrackingResponse(string $fixture): DhlEcommerceDriver
    {
        $response = TrackingItemResponse::create(
            file_get_contents(__DIR__ . '/../Fixtures/data/dhl-ecommerce/' . $fixture)
        );

        $client = m::mock(DhlEcommerce::class);
        $client->shouldReceive('getShipmentStatusDetail')->andReturn($response);

        return new DhlEcommerceDriver($client);
    }

    public function test_it_can_push_update_order_shipment_status()
    {
        /**@var $courier DhlEcommerceDriver */
        $courier = $this->makeDriver($this->driverName)->config($this->defaultConfig());

        $order = new DhlEcommerceOrder('ORD-10002TEST', 'Jane Doe');

        $pushData = json_decode(file_get_contents(__DIR__ . '/../Fixtures/data/dhl-ecommerce/shipment_push_request.json'),
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

    public function test_it_can_fill_in_consignment_number()
    {
        $trackingNumbersArray = [
            '7228109013005524',
            '7228109013013324',
            '7228109013025124',
            '7228109013034924',
            '7228109013044024',
        ];

        $orderItems = [
            ['quantity' => 2, 'consignment_number' => null],
            ['quantity' => 2, 'consignment_number' => null],
            ['quantity' => 1, 'consignment_number' => null],
        ];

//        $orderItems = [
//            ['quantity' => 1, 'consignment_number' => null,],
//            ['quantity' => 1, 'consignment_number' => null,],
//            ['quantity' => 1, 'consignment_number' => null,],
//            ['quantity' => 1, 'consignment_number' => null,],
//            ['quantity' => 1, 'consignment_number' => null,],
//        ];

        $trackingNumbers = collect($trackingNumbersArray);

        foreach ($orderItems as $i => $orderItem) {
            $itemTrackingNumbers = $trackingNumbers->shift(data_get($orderItem, 'quantity', 1));

            if (!is_null($itemTrackingNumbers)) {
                if (is_string($itemTrackingNumbers)) {
                    $itemTrackingNumbers = collect([$itemTrackingNumbers]);
                }

                $orderItem['consignment_number'] = implode(',', $itemTrackingNumbers->all());

                $orderItems[$i] = $orderItem;
            }
        }

        dump($trackingNumbers);
        dump($trackingNumbersArray);
        dump($orderItems);
        $this->assertStringContainsString(7228109013005524, data_get($orderItems, '0.consignment_number'));
    }
}
