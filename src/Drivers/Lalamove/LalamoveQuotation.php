<?php

namespace Nextbyte\Courier\Drivers\Lalamove;

use Carbon\Carbon;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Price;
use Nextbyte\Courier\Quotation;

/**
 * A Lalamove quotation: the price for a route, valid until it expires (about five minutes).
 *
 * Pass it back to LalamoveDriver::createConsignment() as the `quotation` attribute to
 * book at exactly this price. Use toArray()/fromArray() to keep it between requests.
 */
class LalamoveQuotation extends Quotation
{
    /**
     * Lalamove returns coordinates to 7 decimal places; requests may carry more.
     */
    private const COORDINATE_TOLERANCE = 0.000002;

    /**
     * @param string     $id             Lalamove quotationId
     * @param string[]   $stopIds        Stop IDs in the order the stops were requested (index 0 = pickup)
     * @param int|null   $distanceMeters Route distance in metres
     * @param int[]      $routeSequence  Requested stop indexes in the order the driver visits them,
     *                                   e.g. [0, 2, 3, 1] when Lalamove optimised the route; empty if unknown
     */
    public function __construct(
        string $id,
        public readonly array $stopIds,
        Carbon $expiresAt,
        ?Price $price,
        ?string $serviceType = null,
        ?int $distanceMeters = null,
        public readonly array $routeSequence = [],
    ) {
        parent::__construct($id, $expiresAt, $price, $serviceType, $distanceMeters);
    }

    /**
     * Build a quotation from a Lalamove `POST /v3/quotations` response.
     *
     * With route optimisation Lalamove returns the stops in driving order, not the order
     * they were requested in. Pass the requested stops so stopIds can be put back in request
     * order (recipients are matched to stops by that order) and the driving order recorded.
     *
     * @param array $requestedStops the `stops` sent in the quotation request
     * @throws CourierException when the response has no quotationId, or its stops don't match the request
     */
    public static function fromResponse(array $response, array $requestedStops = []): self
    {
        $data = $response['data'] ?? [];

        if (empty($data['quotationId'])) {
            throw new CourierException($response['message'] ?? 'Unknown Lalamove quotation error.');
        }

        $distance = $data['distance']['value'] ?? null;
        $responseStops = array_values($data['stops'] ?? []);

        [$stopIds, $routeSequence] = $requestedStops
            ? self::matchStops(array_values($requestedStops), $responseStops)
            : [array_column($responseStops, 'stopId'), []];

        return new self(
            (string) $data['quotationId'],
            $stopIds,
            Carbon::parse($data['expiresAt'] ?? 'now'),
            Price::fromLalamove($data['priceBreakdown'] ?? null),
            $data['serviceType'] ?? null,
            $distance !== null ? (int) $distance : null,
            $routeSequence,
        );
    }

    /**
     * Match Lalamove's (possibly reordered) stops to the requested ones by coordinates, or by address
     * when a requested stop has no coordinates.
     *
     * @return array{0: string[], 1: int[]} stopIds in request order, and requested indexes in driving order
     * @throws CourierException when a stop can't be matched
     */
    private static function matchStops(array $requestedStops, array $responseStops): array
    {
        if (count($requestedStops) !== count($responseStops)) {
            throw new CourierException('Lalamove quoted ' . count($responseStops) . ' stops for ' . count($requestedStops) . ' requested. Nothing was booked.');
        }

        $stopIds = [];
        $routeSequence = [];
        $unmatched = array_keys($responseStops);

        foreach ($requestedStops as $requestedIndex => $requested) {
            $match = null;

            foreach ($unmatched as $position => $responseIndex) {
                if (self::isSameStop($requested, $responseStops[$responseIndex])) {
                    $match = $responseIndex;
                    unset($unmatched[$position]);
                    break;
                }
            }

            if ($match === null) {
                throw new CourierException('Lalamove returned stops that do not match the requested addresses (stop ' . ($requestedIndex + 1) . ': ' . ($requested['address'] ?? '?') . '). Nothing was booked.');
            }

            $stopIds[$requestedIndex] = (string) $responseStops[$match]['stopId'];
            $routeSequence[$match] = $requestedIndex;
        }

        ksort($routeSequence);

        return [array_values($stopIds), array_values($routeSequence)];
    }

    private static function isSameStop(array $requested, array $returned): bool
    {
        $lat = data_get($requested, 'coordinates.lat');
        $lng = data_get($requested, 'coordinates.lng');

        if (is_numeric($lat) && is_numeric($lng) && is_numeric(data_get($returned, 'coordinates.lat')) && is_numeric(data_get($returned, 'coordinates.lng'))) {
            return abs((float) $lat - (float) data_get($returned, 'coordinates.lat')) <= self::COORDINATE_TOLERANCE
                && abs((float) $lng - (float) data_get($returned, 'coordinates.lng')) <= self::COORDINATE_TOLERANCE;
        }

        return trim((string) ($requested['address'] ?? '')) !== ''
            && trim((string) ($requested['address'] ?? '')) === trim((string) ($returned['address'] ?? ''));
    }

    public static function fromArray(array $attributes): static
    {
        return new static(
            (string) $attributes['id'],
            array_values($attributes['stopIds'] ?? []),
            Carbon::parse($attributes['expiresAt']),
            isset($attributes['price']) ? Price::fromArray($attributes['price']) : null,
            $attributes['serviceType'] ?? null,
            isset($attributes['distanceMeters']) ? (int) $attributes['distanceMeters'] : null,
            array_map('intval', array_values($attributes['routeSequence'] ?? [])),
        );
    }

    /**
     * @return array{id: string, expiresAt: string, price: array|null, serviceType: string|null, distanceMeters: int|null, stopIds: string[], routeSequence: int[]}
     */
    public function toArray(): array
    {
        return parent::toArray() + ['stopIds' => $this->stopIds, 'routeSequence' => $this->routeSequence];
    }

    /**
     * Where a requested drop-off comes in the driver's route: 1 for the first drop-off after pickup.
     * Null when the driving order is unknown.
     */
    public function dropOffNumber(int $requestedIndex): ?int
    {
        $position = array_search($requestedIndex, $this->routeSequence, true);

        return $position === false || $position === 0 ? null : $position;
    }
}
