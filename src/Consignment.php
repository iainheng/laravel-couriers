<?php

namespace Nextbyte\Courier;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class Consignment
{
    /**
     * The tracking number.
     *
     * @var array
    */
    public $number;

    /**
     * @var string
     */
    public $orderNumber;

    /**
     * Main consignment status
     * @var string
     */
    public $status;

    /**
     * @var string
     */
    public $statusCode;

    /**
     * @var string
     */
    public $description;

    /**
     * @var Carbon
     */
    public $pickedAt;

    /**
     * @var Carbon
     */
    public $updatedAt;

    /**
     * @var string
     */
    public $origin;

    /**
     * @var string
     */
    public $destination;

    /**
     * @var float
     */
    public $weight;

    /**
     * @var string
     */
    public $type;

    /**
     * @var Shipment[]|Collection
     */
    public $shipments;

    /**
     * @var array
     */
    public $rawShipments;

    /**
     * @var ConsignmentFile
     */
    public $slip;

    protected function __construct(array $attributes)
    {
        foreach ($attributes as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->$attribute = $value;
            }
        }
    }

    public static function create(array $attributes): Consignment
    {
        if (isset($attributes['pickedAt']) && !$attributes['pickedAt'] instanceof Carbon)
            $attributes['pickedAt'] = Carbon::createFromTimestamp(strtotime($attributes['pickedAt']));

        if (isset($attributes['updatedAt']) && !$attributes['updatedAt'] instanceof Carbon)
            $attributes['updatedAt'] = Carbon::createFromTimestamp(strtotime($attributes['updatedAt']));

        return new static($attributes);
    }
}
