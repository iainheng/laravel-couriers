<?php

namespace Nextbyte\Tests\Courier\Fixtures;


class BestExpressOrder extends Order
{

    public function __construct($orderNumber, $customerName, $consignmentNumbers = [])
    {
        parent::__construct($orderNumber, $customerName, $consignmentNumbers);
    }

    protected function setup()
    {
        $faker = \Faker\Factory::create('ms_MY');

        $this->setConsignmentableData([
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
                "postCode" => "77500",
                "mobile" => "13927089988",
                "prov" => "Melaka",
                "city" => " Selandar",
                "county" => "Cheras",
                "address" => "456",
                "email" => "kkk@email.com",
                "country" => "01"
            ],
            "items" => [
                "item" => [
                    [
                        "itemName" => '豪华礼盒 (可寄东马）Deluxe Gift Pack (East Malaysia Available)'
                    ],
                ]
            ],
            "itemsWeight" => rand(1, 5),
            "piece" => "1",
            "remark" => ""
        ]);
    }

    public function getQueryConsignmentSlipAttributes($courierName, array $data = [])
    {
        return $this->toConsignmentableArray('Best Express');
    }
}
