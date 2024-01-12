<?php

namespace Nextbyte\Tests\Courier\Fixtures;


class DhlEcommerceOrder extends Order
{

    public function __construct($orderNumber, $customerName, $consignmentNumbers = [])
    {
        parent::__construct($orderNumber, $customerName, $consignmentNumbers);
    }

    protected function setup()
    {
        $faker = \Faker\Factory::create('ms_MY');

        $pickupName = 'Bell Wong';
        $pickupAddress1 = $faker->streetAddress;
        $pickupPostcode = '49100';
        $pickupCity = 'Balakong';
        $pickupState = 'Selangor';
        $pickupCounterCode = 'MY';
        $productCode = 'PDO'; // https://api.dhlecommerce.dhl.com/API/docs/v2/appendix.html#prodcd

        $customerAddress1 = $faker->streetAddress;
        $customerPostcode = '47810';
        $customerCity = 'Kota Damansara';
        $customerState = 'Selangor';
        $customerPhone = $faker->phoneNumber;

        $remarks = 'Please delivery in the evening after 6pm.';

        $pickupAttributes = [
            'companyName' => 'Empire Hamper',
            'name' => $pickupName,
            'address1' => $pickupAddress1,
            'postCode' => $pickupPostcode,
            'city' => $pickupCity,
            'state' => $pickupState,
            'country' => $pickupCounterCode,
        ];

        $this->setConsignmentableData([
            "bd" => [
                "pickupAddress" => $pickupAttributes,
                "shipperAddress" => $pickupAttributes,
                'shipmentItems' => [
                    [
                        'consigneeAddress' => [
                            'name' => $this->customerName,
                            'address1' => $customerAddress1,
                            'country' => $pickupCounterCode,
                            'city' => $customerCity,
                            'postCode' => $customerPostcode,
                            'state' => $customerState,
                            'phone' => $customerPhone,
                        ],
                        'packageDesc' => 'Hamper',
                        'shipmentID' => 'MYLIL' . $this->orderNumber,
                        'totalWeight' => 1,
                        'totalWeightUOM' => 'G',
                        'productCode' => $productCode,
                        'currency' => 'MYR',
                        'remarks' => $remarks,
                        'isMult' => 'false',
                        'shipmentPieces' => [
                            [
                                'pieceID' => 1,
                            ]
                        ],
                    ],
                ],
                'label' => [
                    'pageSize' => '400x600',
                    'format' => 'PNG',
                    'layout' => '1x1',
                ],
//                    "items" => [
//                        "item" => [
//                            [
//                                "itemName" => '豪华礼盒 (可寄东马）Deluxe Gift Pack (East Malaysia Available)'
//                            ],
//                        ]
//                    ],
            ]
        ]);
    }

    public function getQueryConsignmentSlipAttributes($courierName, array $data = [])
    {
        return $this->toConsignmentableArray('DHL Ecommerce');
    }
}
