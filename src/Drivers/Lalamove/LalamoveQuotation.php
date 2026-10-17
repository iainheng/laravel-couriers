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
     * @param array[]    $stops          The requested stops' coordinates and address, in request order, so
     *                                   forStops() can line the quotation up with a booking; empty if unknown
     */
    public function __construct(
        string $id,
        public readonly array $stopIds,
        Carbon $expiresAt,
        ?Price $price,
        ?string $serviceType = null,
        ?int $distanceMeters = null,
        public readonly array $routeSequence = [],
        public readonly array $stops = [],
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
            array_map(fn (array $stop) => self::stopKey($stop), array_values($requestedStops)),
        );
    }

    /**
     * The same quotation lined up with a booking's stops, which may list the same addresses in a
     * different order than the quotation request did (e.g. orders loaded in another order).
     *
     * Stop IDs belong to addresses, not positions: booking with the quotation's positions would hand
     * each recipient another recipient's address. Unchanged when the quotation does not know its stops.
     *
     * @param array $stops the `stops` being booked, in the order recipients refer to them
     * @throws CourierException when the stops are not the ones quoted
     */
    public function forStops(array $stops): static
    {
        $stops = array_values($stops);

        if ($this->stops === []) {
            return $this;
        }

        if (count($stops) !== count($this->stops)) {
            throw new CourierException('The stops changed after the price was quoted (' . count($this->stops) . ' quoted, ' . count($stops) . ' to book). Get a new price. Nothing was booked.');
        }

        $quotedIndexFor = [];
        $unmatched = array_keys($this->stops);

        foreach ($stops as $index => $stop) {
            $match = self::findStop($stop, $this->stops, $unmatched);

            if ($match === null) {
                throw new CourierException('The stops changed after the price was quoted (stop ' . ($index + 1) . ': ' . ($stop['address'] ?? '?') . '). Get a new price. Nothing was booked.');
            }

            $quotedIndexFor[$index] = $match;
            $unmatched = array_values(array_diff($unmatched, [$match]));
        }

        $bookingIndexFor = array_flip($quotedIndexFor);

        return new static(
            $this->id,
            array_map(fn (int $quotedIndex) => $this->stopIds[$quotedIndex], $quotedIndexFor),
            $this->expiresAt,
            $this->price,
            $this->serviceType,
            $this->distanceMeters,
            array_map(fn (int $quotedIndex) => $bookingIndexFor[$quotedIndex], $this->routeSequence),
            array_map(fn (int $quotedIndex) => $this->stops[$quotedIndex], $quotedIndexFor),
        );
    }

    /**
     * The first of the candidate stops that is the same place as $stop, preferring one with the same
     * address too, so two recipients at one building keep their own stop.
     *
     * @param int[] $candidates indexes into $stops
     */
    private static function findStop(array $stop, array $stops, array $candidates): ?int
    {
        $samePlace = array_values(array_filter($candidates, fn (int $index) => self::isSameStop($stop, $stops[$index])));

        foreach ($samePlace as $index) {
            if (trim((string) ($stop['address'] ?? '')) === trim((string) ($stops[$index]['address'] ?? ''))) {
                return $index;
            }
        }

        return $samePlace[0] ?? null;
    }

    /**
     * @return array{coordinates: array{lat: string|null, lng: string|null}, address: string}
     */
    private static function stopKey(array $stop): array
    {
        return [
            'coordinates' => [
                'lat' => isset($stop['coordinates']['lat']) ? (string) $stop['coordinates']['lat'] : null,
                'lng' => isset($stop['coordinates']['lng']) ? (string) $stop['coordinates']['lng'] : null,
            ],
            'address' => (string) ($stop['address'] ?? ''),
        ];
    }

    /**
     * Match Lalamove's (possibly reordered) stops to the requested ones by coordinates (and address, when
     * two stops share coordinates), or by address when a requested stop has no coordinates.
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
            $match = self::findStop($requested, $responseStops, $unmatched);

            if ($match !== null) {
                $unmatched = array_values(array_diff($unmatched, [$match]));
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

    /**
     * Whether two stops are the same place: matching coordinates, or the same address when either has none.
     */
    public static function isSameStop(array $requested, array $returned): bool
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
            array_values($attributes['stops'] ?? []),
        );
    }

    /**
     * @return array{id: string, expiresAt: string, price: array|null, serviceType: string|null, distanceMeters: int|null, stopIds: string[], routeSequence: int[], stops: array[]}
     */
    public function toArray(): array
    {
        return parent::toArray() + ['stopIds' => $this->stopIds, 'routeSequence' => $this->routeSequence, 'stops' => $this->stops];
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
