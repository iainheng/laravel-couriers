<?php

namespace Nextbyte\Tests\Courier\Fixtures;


use Nextbyte\Courier\Contracts\Consignmentable;

abstract class Order implements Consignmentable
{
    /**
     * @var string
     */
    protected $orderNumber;

    /**
     * @var string
     */
    protected $customerName;

    /**
     * @var array|mixed
     */
    protected $consignmentNumbers;

    public function __construct($orderNumber, $customerName, $consignmentNumbers = [])
    {
        $this->orderNumber = $orderNumber;
        $this->customerName = $customerName;
        $this->consignmentNumbers = $consignmentNumbers;
    }

    /**
     * @return array
     */
    public function getConsignmentNumbers()
    {
        return $this->consignmentNumbers;
    }

    /**
     * @param array $consignmentNumbers
     */
    public function setConsignmentNumbers($consignmentNumbers): void
    {
        $this->consignmentNumbers = $consignmentNumbers;
    }
}
