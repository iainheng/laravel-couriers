<?php

namespace Nextbyte\Courier;

use Carbon\Carbon;
use Nextbyte\Courier\Exceptions\MissingRequiredParamException;

class ShipmentStatusPush
{
    /**
     * @var Carbon
     */
    protected $date;

    /**
     * @var string
     */
    protected $orderNumber;

    /**
     * @var string
     */
    protected $consignmentNumber;

    /**
     * @var string
     */
    protected $statusCode;

    /**@var string **/
    protected $status;

    /**@var string **/
    protected $description;

    /**
     * @var string
     */
    protected $currentCity;

    /**
     * @var string
     */
    protected $nextCity;

    /**
     * @var string
     */
    protected $remarks;

    /**
     * Driver details for DRIVER_ASSIGNED events.
     * Keys: driverId, name, phone, photo, plateNumber, location (lat/lng).
     *
     * @var array
     */
    protected $driverDetails = [];

    /**
     * POD image URL for this stop (set for Lalamove POD_STATUS_CHANGED events).
     * @var string|null
     */
    protected $podImageUrl;

    /**
     * Recipient name at this stop — used to match the stop to the correct order
     * when multiple orders share one tracking number (multi-stop dispatch).
     * @var string|null
     */
    protected $recipientName;

    protected function __construct(array $attributes)
    {
        foreach ($attributes as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->$attribute = $value;
            }
        }
    }

    public static function create(array $attributes): ShipmentStatusPush
    {
        if (isset($attributes['date']) && !$attributes['date'] instanceof Carbon)
            $attributes['date'] = Carbon::parse($attributes['date']);

        $instance = new static($attributes);

        $instance->validateRequiredParams($attributes);

        return $instance;
    }

    /**
     * @param array $attributes
     * @throws MissingRequiredParamException
     */
    protected function validateRequiredParams(array $attributes)
    {
        $fields = ['orderNumber', 'status'];

        foreach ($fields as $field) {
            if (empty($this->$field)) {
                throw new MissingRequiredParamException($field, $attributes);
            }
        }
    }

    /**
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        return $this->date;
    }

    /**
     * @param Carbon $date
     */
    public function setDate(Carbon $date): void
    {
        $this->date = $date;
    }

    /**
     * @return string
     */
    public function getOrderNumber(): string
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
    public function getConsignmentNumber(): string
    {
        return $this->consignmentNumber;
    }

    /**
     * @param string $consignmentNumber
     */
    public function setConsignmentNumber(string $consignmentNumber): void
    {
        $this->consignmentNumber = $consignmentNumber;
    }

    /**
     * @return string
     */
    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    /**
     * @param string $statusCode
     */
    public function setStatusCode(string $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getCurrentCity(): string
    {
        return $this->currentCity;
    }

    /**
     * @param string $currentCity
     */
    public function setCurrentCity(string $currentCity): void
    {
        $this->currentCity = $currentCity;
    }

    /**
     * @return string
     */
    public function getNextCity(): string
    {
        return $this->nextCity;
    }

    /**
     * @param string $nextCity
     */
    public function setNextCity(string $nextCity): void
    {
        $this->nextCity = $nextCity;
    }

    /**
     * @return string
     */
    public function getRemarks(): string
    {
        return $this->remarks;
    }

    /**
     * @param string $remarks
     */
    public function setRemarks(string $remarks): void
    {
        $this->remarks = $remarks;
    }

    /**
     * @return array
     */
    public function getDriverDetails(): array
    {
        return $this->driverDetails;
    }

    /**
     * @param array $driverDetails
     */
    public function setDriverDetails(array $driverDetails): void
    {
        $this->driverDetails = $driverDetails;
    }

    /**
     * @return bool
     */
    public function hasDriverDetails(): bool
    {
        return !empty($this->driverDetails);
    }

    public function getPodImageUrl(): ?string
    {
        return $this->podImageUrl;
    }

    public function setPodImageUrl(?string $podImageUrl): void
    {
        $this->podImageUrl = $podImageUrl;
    }

    public function hasPod(): bool
    {
        return !empty($this->podImageUrl);
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): void
    {
        $this->recipientName = $recipientName;
    }
}
