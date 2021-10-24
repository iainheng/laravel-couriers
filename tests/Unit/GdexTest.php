<?php

namespace Nextbyte\Tests\Courier\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mockery as m;
use Nextbyte\Courier\Drivers\Gdex\GdexDriver;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Tests\Courier\TestCase;

class GdexTest extends TestCase
{
    protected $driverName = 'Gdex';

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
            "accountNo" => config('courier.gdex.account_no'),
            "shipmentType" => "Parcel",
            "totalPiece" => rand(1,5),
            "shipmentContent" => $faker->linuxPlatformToken,
            "shipmentValue" => $faker->randomFloat(2, 10, 200),
            "shipmentWeight" => rand(1, 10),
            "shipmentLength" => rand(1, 30),
            "shipmentWidth" => 0,
            "shipmentHeight" => 0,
            "isDangerousGoods" => $faker->boolean,
            "companyName" => Str::limit($faker->company, 38),
            "receiverName" => $faker->name,
            "receiverMobile" => $faker->mobileNumber(false, false),
//            "receiverMobile2" => "",
            "receiverEmail" => $faker->email,
            "receiverAddress1" => $faker->streetAddress,
            "receiverAddress2" => "",
            "receiverAddress3" => "",
            "receiverPostcode" => data_get($stateParams, 'postcode'),
            "receiverCity" => data_get($stateParams, 'city'),
            "receiverState" => ucfirst(data_get($stateParams, 'state')),
            "receiverCountry" => "Malaysia",
            "IsInsurance" => false,
//            "note1" => "",
//            "note2" => "",
            "orderID" => 'ORD-'. sprintf('%04d', $faker->numberBetween(1, 99999)),
            "isCod" => false,
            "codAmount" => 0,
//            "doNumber1" => "",
//            "doNumber2" => "",
//            "doNumber3" => "",
//            "doNumber4" => "",
//            "doNumber5" => "",
//            "doNumber6" => "",
//            "doNumber7" => "",
//            "doNumber8" => "",
//            "doNumber9" => ""
        ];
    }

    /**
     * @param $townState
     * @return array
     */
    protected function splitFakerTownState($townState)
    {
        $params = explode(',', $townState);

        $postcodeCity = data_get($params, 0, '');

        $state = trim(data_get($params, 1, ''));

        $cityParams = explode(' ', $postcodeCity);

        $postcode = trim(data_get($cityParams, 0, ''));

        $city = substr($postcodeCity, strlen($postcode . ' '));

        return [
            'postcode' => $postcode,
            'city' => $city,
            'state' => $state,
        ];
    }

    public function test_it_return_correct_driver()
    {
        $courier = $this->makeDriver($this->driverName);

        $this->assertInstanceOf(GdexDriver::class, $courier);
    }

    public function test_it_can_create_consignment()
    {
        $courier = $this->makeDriver($this->driverName);

        $consignment = $this->makeConsignmentRequestParams();

        $response = $courier->createConsignment($consignment);

        $this->assertEquals('success', data_get($response, 's'));
        $this->assertNotEmpty(data_get($response, 'r'));
    }

    public function test_it_can_create_consignment_and_get_consignment_image()
    {
        $courier = $this->makeDriver($this->driverName);

        $consignment = $this->makeConsignmentRequestParams();

//        dump($consignment);

        $consignmentNo = $courier->createConsignment($consignment);

        $this->assertNotEmpty($consignmentNo);
        $this->assertStringStartsWith('TCN', $consignmentNo);

        $consignmentFile = $courier->getConsignmentSlip($consignmentNo);

        $this->assertEquals('zip', $consignmentFile->getExtension());
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
