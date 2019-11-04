<?php

namespace Nextbyte\Courier;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Nextbyte\Courier\Contracts\Courier;
use Nextbyte\Courier\Enums\ConsignmentStatus;
use Nextbyte\Courier\Exceptions\CourierException;

class Shipment
{
    /**
     * @var Carbon
     */
    public $date;

    /**
     * @var string
     */
    public $origin;

    /**
     * @var string
     */
    public $destination;

    /**
     * @var string
     */
    public $description;

    protected function __construct(array $attributes)
    {
        foreach ($attributes as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->$attribute = $value;
            }
        }
    }
    public static function create(array $attributes): Shipment
    {
        if (isset($attributes['date']) && !$attributes['date'] instanceof Carbon)
            $attributes['date'] = Carbon::createFromTimestamp(strtotime($attributes['date']));

        return new static($attributes);
    }
}