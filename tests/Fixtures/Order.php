<?php

namespace Nextbyte\Tests\Courier\Fixtures;


use Nextbyte\Courier\Contracts\Consignmentable;
use Nextbyte\Courier\Enums\ShipmentStatus;

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
     * @var string
     */
    protected $shipmentStatus;

    /**
     * @var array
     */
    protected $consignmentableData = [];

    public function __construct($orderNumber, $customerName, $consignmentNumbers = [])
    {
        $this->orderNumber = $orderNumber;
        $this->customerName = $customerName;
        $this->consignmentNumbers = $consignmentNumbers;
        $this->shipmentStatus = ShipmentStatus::Pending;

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

    /**
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @param string $orderNumber
     */
    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    /**
     * @return string
     */
    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    /**
     * @param string $customerName
     */
    public function setCustomerName(string $customerName): void
    {
        $this->customerName = $customerName;
    }

    /**
     * @return string
     */
    public function getShipmentStatus(): string
    {
        return $this->shipmentStatus;
    }

    /**
     * @param string $shipmentStatus
     */
    public function setShipmentStatus(string $shipmentStatus): void
    {
        $this->shipmentStatus = $shipmentStatus;
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
