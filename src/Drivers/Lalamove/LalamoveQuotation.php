<?php

namespace Nextbyte\Courier\Drivers\Lalamove;

use Carbon\Carbon;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Price;

/**
 * A Lalamove quotation: the price for a route, valid until it expires (about five minutes).
 *
 * Pass it back to LalamoveDriver::createConsignment() as the `quotation` attribute to
 * book at exactly this price. Use toArray()/fromArray() to keep it between requests.
 */
class LalamoveQuotation
{
    /**
     * @param string     $id             Lalamove quotationId
     * @param string[]   $stopIds        Stop IDs in the order the stops were quoted
     * @param int|null   $distanceMeters Route distance in metres
     */
    public function __construct(
        public readonly string $id,
        public readonly array $stopIds,
        public readonly Carbon $expiresAt,
        public readonly ?Price $price,
        public readonly ?string $serviceType = null,
        public readonly ?int $distanceMeters = null,
    ) {
    }

    /**
     * Build a quotation from a Lalamove `POST /v3/quotations` response.
     *
     * @throws CourierException when the response has no quotationId
     */
    public static function fromResponse(array $response): self
    {
        $data = $response['data'] ?? [];

        if (empty($data['quotationId'])) {
            throw new CourierException($response['message'] ?? 'Unknown Lalamove quotation error.');
        }

        $distance = $data['distance']['value'] ?? null;

        return new self(
            (string) $data['quotationId'],
            array_values(array_column($data['stops'] ?? [], 'stopId')),
            Carbon::parse($data['expiresAt'] ?? 'now'),
            Price::fromLalamove($data['priceBreakdown'] ?? null),
            $data['serviceType'] ?? null,
            $distance !== null ? (int) $distance : null,
        );
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            (string) $attributes['id'],
            array_values($attributes['stopIds'] ?? []),
            Carbon::parse($attributes['expiresAt']),
            isset($attributes['price']) ? Price::fromArray($attributes['price']) : null,
            $attributes['serviceType'] ?? null,
            isset($attributes['distanceMeters']) ? (int) $attributes['distanceMeters'] : null,
        );
    }

    /**
     * @return array{id: string, stopIds: string[], expiresAt: string, price: array|null, serviceType: string|null, distanceMeters: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stopIds' => $this->stopIds,
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
