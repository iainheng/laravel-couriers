<?php

namespace Nextbyte\Tests\Courier\Fixtures;


use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GdexOrder extends Order
{
    public function toConsignmentableArray($courierName, array $data = [])
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
            "companyName" => Str::limit($faker->company, 35),
            "receiverName" => $this->customerName,
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
            "orderID" => $this->orderNumber,
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

    public function getQueryConsignmentSlipAttributes($courierName, array $data = [])
    {
        return Arr::first($this->consignmentNumbers);
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
}
