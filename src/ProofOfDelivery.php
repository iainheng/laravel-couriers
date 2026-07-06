<?php

namespace Nextbyte\Courier;

use Carbon\Carbon;

/**
 * Electronic proof of delivery (ePOD) captured by a courier at the time of
 * delivery. Depending on the courier and shipment, it may contain the name of
 * the person who received the parcel, their signature and/or a photo taken at
 * the doorstep.
 */
class ProofOfDelivery
{
    /**
     * Name of the person who received the shipment.
     *
     * @var string|null
     */
    public $recipientName;

    /**
     * The recipient signature captured at delivery, decoded into a storable
     * file so the consuming project can persist it locally.
     *
     * @var ConsignmentFile|null
     */
    public $signature;

    /**
     * Publicly accessible URL of the delivery photo taken at delivery. The
     * consuming project can download this to store the image locally.
     *
     * @var string|null
     */
    public $imageUrl;

    /**
     * When the shipment was delivered.
     *
     * @var Carbon|null
     */
    public $deliveredAt;

    /**
     * The raw courier response the proof of delivery was built from.
     *
     * @var array
     */
    public $raw = [];

    protected function __construct(array $attributes)
    {
        foreach ($attributes as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->$attribute = $value;
            }
        }
    }

    public static function create(array $attributes): ProofOfDelivery
    {
        if (isset($attributes['deliveredAt']) && !$attributes['deliveredAt'] instanceof Carbon) {
            $attributes['deliveredAt'] = Carbon::parse($attributes['deliveredAt']);
        }

        return new static($attributes);
    }

    /**
     * @return bool
     */
    public function hasSignature(): bool
    {
        return $this->signature instanceof ConsignmentFile && !empty($this->signature->getBody());
    }

    /**
     * @return bool
     */
    public function hasImage(): bool
    {
        return !empty($this->imageUrl);
    }
}
