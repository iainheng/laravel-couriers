<?php

namespace Nextbyte\Tests\Courier\Fixtures;


class BestExpressOrder extends Order
{
    public function __construct($orderNumber, $customerName, $consignmentNumbers = [])
    {
        parent::__construct($orderNumber, $customerName, $consignmentNumbers);
    }

    public function toConsignmentableArray($courierName, array $data = [])
    {
        $faker = \Faker\Factory::create('ms_MY');

        return [
            "customerName" => $this->customerName,
            "txLogisticId" => $this->orderNumber,
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

    public function getQueryConsignmentSlipAttributes($courierName, array $data = [])
    {
        return $this->toConsignmentableArray('Best Express');
    }
}