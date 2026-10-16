<?php

namespace Nextbyte\Courier;

use Carbon\Carbon;

/**
 * A courier's price for a delivery, valid until it expires. Returned by Contracts\Quotable::quote().
 *
 * Drivers extend this with whatever they need to book against the quotation.
 * Use toArray()/fromArray() to keep it between requests.
 */
class Quotation
{
    /**
     * @param string   $id             The courier's quotation ID
     * @param int|null $distanceMeters Route distance in metres, when the courier reports it
     */
    public function __construct(
        public readonly string $id,
        public readonly Carbon $expiresAt,
        public readonly ?Price $price,
        public readonly ?string $serviceType = null,
        public readonly ?int $distanceMeters = null,
    ) {
    }

    public static function fromArray(array $attributes): static
    {
        return new static(
            (string) $attributes['id'],
            Carbon::parse($attributes['expiresAt']),
            isset($attributes['price']) ? Price::fromArray($attributes['price']) : null,
            $attributes['serviceType'] ?? null,
            isset($attributes['distanceMeters']) ? (int) $attributes['distanceMeters'] : null,
        );
    }

    /**
     * @return array{id: string, expiresAt: string, price: array|null, serviceType: string|null, distanceMeters: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'expiresAt' => $this->expiresAt->toIso8601ZuluString(),
            'price' => $this->price?->toArray(),
            'serviceType' => $this->serviceType,
            'distanceMeters' => $this->distanceMeters,
        ];
    }

    public function isExpired(?Carbon $now = null): bool
    {
        return ($now ?? Carbon::now())->greaterThanOrEqualTo($this->expiresAt);
    }
}
