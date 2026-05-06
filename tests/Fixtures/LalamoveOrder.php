<?php

namespace Nextbyte\Tests\Courier\Fixtures;

class LalamoveOrder extends Order
{
    public function getQueryConsignmentSlipAttributes($courierName, array $data = [])
    {
        return null;
    }

    protected function setup()
    {
        $this->consignmentableData = [
            'serviceType' => 'MOTORCYCLE',
            'language' => 'en_MY',
            'stops' => [
                [
                    'coordinates' => [
                        'lat' => '3.1578',
                        'lng' => '101.7118',
                    ],
                    'address' => 'Suria KLCC, Kuala Lumpur City Centre, 50088 Kuala Lumpur',
                ],
                [
                    'coordinates' => [
                        'lat' => '3.1306',
                        'lng' => '101.6838',
                    ],
                    'address' => 'Bangsar Shopping Centre, 285, Jalan Maarof, 59000 Kuala Lumpur',
                ],
            ],
            'sender' => [
                'stopIndex' => 0,
                'name' => $this->customerName,
                'phone' => '+60123456789',
            ],
            'recipients' => [
                [
                    'stopIndex' => 1,
                    'name' => 'Katrina',
                    'phone' => '+60198765432',
                    'remarks' => 'Please call before delivery',
                ],
            ],
            'isPODEnabled' => true,
            'metadata' => [
                'referenceId' => $this->orderNumber,
            ],
        ];
    }
}
