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

    /**
     * @var array
     */
    protected $consignmentableData = [];

    public function __construct($orderNumber, $customerName, $consignmentNumbers = [])
    {
        $this->orderNumber = $orderNumber;
        $this->customerName = $customerName;
        $this->consignmentNumbers = $consignmentNumbers;

        $this->setup();
    }

    abstract protected function setup();

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

    public function toConsignmentableArray($courierName, array $data = [])
    {
        return $this->consignmentableData;
    }

    /**
     * @param array $consignmentableData
     */
    public function setConsignmentableData(array $consignmentableData): void
    {
        $this->consignmentableData = $consignmentableData;
    }

    /**
     * @param $key
     * @param null $value
     * @return $this
     */
    public function set($key, $value = null)
    {
        data_set($this->consignmentableData, $key, $value);

        return $this;
    }

    /**
     * @param $key
     * @param null $defaultValue
     * @return mixed
     */
    public function get($key, $defaultValue = null)
    {
        return data_get($this->consignmentableData, $key, $defaultValue);
    }
}
