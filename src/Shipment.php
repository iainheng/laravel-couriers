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
    protected $date;

    /**
     * @var string
     */
    protected $origin;

    /**
     * @var string
     */
    protected $destination;

    /** @var string */
    protected $location;

    /**@var string **/
    protected $status;

    /**@var string **/
    protected $description;

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

//    public function __get($attribute)
//    {
//        if (property_exists($this, $attribute)) {
//            return $this->{$attribute};
//        }
//    }

    /**
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * @return string
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }
}
