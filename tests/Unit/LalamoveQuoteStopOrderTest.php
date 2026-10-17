<?php

namespace Nextbyte\Tests\Courier\Unit;

use Carbon\Carbon;
use Mockery as m;
use Nextbyte\Courier\Clients\Lalamove\Lalamove;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveDriver;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveQuotation;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Tests\Courier\TestCase;

/**
 * A quote and its booking may list the same stops in different orders; each recipient must still
 * get the stop at their own address. (Seen in the sandbox: two recipients swapped addresses.)
 */
class LalamoveQuoteStopOrderTest extends TestCase
{
    private const WAREHOUSE = ['coordinates' => ['lat' => '3.00982', 'lng' => '101.75597'], 'address' => 'No 9, Jalan Alfa Impian 1, Balakong'];

    private const KENYALANG = ['coordinates' => ['lat' => '3.1545257', 'lng' => '101.5894092'], 'address' => '38 Jalan Kenyalang 11/7, Kota Damansara'];

    private const ASIA_SKYWAY = ['coordinates' => ['lat' => '3.0740764', 'lng' => '101.6156681'], 'address' => 'Asia Skyway Express, Petaling Jaya'];

    private const SUNGAI_BULOH = ['coordinates' => ['lat' => '3.2089556', 'lng' => '101.563986'], 'address' => 'No 208 Blok A Bandar Baru, Sungai Buloh'];

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    /**
     * Lalamove's answer for any order of these stops: driving order warehouse, Asia Skyway, Kenyalang,
     * Sungai Buloh, with stop IDs given in that order (as the sandbox does).
     */
    private function quotationResponse(): array
    {
        $stop = fn (string $id, array $stop) => ['stopId' => $id, 'coordinates' => ['lat' => sprintf('%.7f', $stop['coordinates']['lat']), 'lng' => sprintf('%.7f', $stop['coordinates']['lng'])], 'address' => $stop['address']];

        return ['data' => [
            'quotationId' => 'QUO-1',
            'expiresAt' => Carbon::now()->addMinutes(5)->toIso8601String(),
            'serviceType' => '4X4',
            'priceBreakdown' => ['total' => '70.40', 'currency' => 'MYR'],
            'stops' => [
                $stop('S-WAREHOUSE', self::WAREHOUSE),
                $stop('S-ASIA', self::ASIA_SKYWAY),
                $stop('S-KENYALANG', self::KENYALANG),
                $stop('S-SUNGAI-BULOH', self::SUNGAI_BULOH),
            ],
        ]];
    }

    private function quotedIn(array $stops): LalamoveQuotation
    {
        return LalamoveQuotation::fromResponse($this->quotationResponse(), $stops);
    }

    public function test_a_quotation_lines_up_with_stops_listed_in_another_order()
    {
        $quotation = $this->quotedIn([self::WAREHOUSE, self::SUNGAI_BULOH, self::ASIA_SKYWAY, self::KENYALANG])
            ->forStops([self::WAREHOUSE, self::KENYALANG, self::ASIA_SKYWAY, self::SUNGAI_BULOH]);

        $this->assertSame(['S-WAREHOUSE', 'S-KENYALANG', 'S-ASIA', 'S-SUNGAI-BULOH'], $quotation->stopIds);
        $this->assertSame([0, 2, 1, 3], $quotation->routeSequence, 'Driving order in the booking\'s indexes.');
        $this->assertSame(2, $quotation->dropOffNumber(1), 'Kenyalang is the second drop-off.');
        $this->assertSame(1, $quotation->dropOffNumber(2), 'Asia Skyway is the first.');
        $this->assertSame(3, $quotation->dropOffNumber(3));
        $this->assertSame('QUO-1', $quotation->id);
        $this->assertSame('70.40', $quotation->price->total);
    }

    public function test_booking_a_quotation_sends_each_recipient_to_their_own_address()
    {
        $quotation = $this->quotedIn([self::WAREHOUSE, self::SUNGAI_BULOH, self::ASIA_SKYWAY, self::KENYALANG]);
        $sent = null;

        $client = m::mock(Lalamove::class);
        $client->shouldReceive('config')->andReturnSelf();
        $client->shouldNotReceive('createQuotation');
        $client->shouldReceive('createOrder')->once()->andReturnUsing(function (array $attributes) use (&$sent) {
            $sent = $attributes;

            return ['data' => ['orderId' => 'ORDER-1']];
        });

        (new LalamoveDriver($client))->createConsignment([
            'stops' => [self::WAREHOUSE, self::KENYALANG, self::ASIA_SKYWAY, self::SUNGAI_BULOH],
            'sender' => ['stopIndex' => 0, 'name' => 'Empire Hamper', 'phone' => '+60129499667'],
            'recipients' => [
                ['stopIndex' => 1, 'name' => 'Gogillan', 'phone' => '0163747160'],
                ['stopIndex' => 2, 'name' => 'Jaret', 'phone' => '0162055410'],
                ['stopIndex' => 3, 'name' => 'Prabagaran', 'phone' => '01121536713'],
            ],
            'quotation' => $quotation->toArray(),
        ]);

        $this->assertSame('S-WAREHOUSE', $sent['sender']['stopId']);
        $this->assertSame(
            ['Gogillan' => 'S-KENYALANG', 'Jaret' => 'S-ASIA', 'Prabagaran' => 'S-SUNGAI-BULOH'],
            array_column($sent['recipients'], 'stopId', 'name'),
        );
    }

    public function test_stops_that_were_not_quoted_are_refused_before_booking()
    {
        $quotation = $this->quotedIn([self::WAREHOUSE, self::KENYALANG, self::ASIA_SKYWAY, self::SUNGAI_BULOH]);
        $elsewhere = ['coordinates' => ['lat' => '3.5', 'lng' => '101.5'], 'address' => 'Somewhere else'];

        foreach ([[self::WAREHOUSE, self::KENYALANG, self::ASIA_SKYWAY, $elsewhere], [self::WAREHOUSE, self::KENYALANG]] as $stops) {
            try {
                $quotation->forStops($stops);
                $this->fail('Expected a CourierException.');
            } catch (CourierException $exception) {
                $this->assertStringStartsWith('The stops changed after the price was quoted', $exception->getMessage());
                $this->assertStringEndsWith('Nothing was booked.', $exception->getMessage());
            }
        }
    }

    public function test_two_recipients_in_one_building_keep_their_own_stop()
    {
        $unitA = ['coordinates' => self::KENYALANG['coordinates'], 'address' => 'Unit A, 38 Jalan Kenyalang'];
        $unitB = ['coordinates' => self::KENYALANG['coordinates'], 'address' => 'Unit B, 38 Jalan Kenyalang'];
        $response = ['data' => [
            'quotationId' => 'QUO-2',
            'expiresAt' => Carbon::now()->addMinutes(5)->toIso8601String(),
            'stops' => [
                ['stopId' => 'S-WAREHOUSE'] + self::WAREHOUSE,
                ['stopId' => 'S-B'] + $unitB,
                ['stopId' => 'S-A'] + $unitA,
            ],
        ]];

        $quotation = LalamoveQuotation::fromResponse($response, [self::WAREHOUSE, $unitA, $unitB])->forStops([self::WAREHOUSE, $unitB, $unitA]);

        $this->assertSame(['S-WAREHOUSE', 'S-B', 'S-A'], $quotation->stopIds);
    }

    public function test_the_stops_survive_a_round_trip_through_an_array()
    {
        $quotation = $this->quotedIn([self::WAREHOUSE, self::SUNGAI_BULOH, self::ASIA_SKYWAY, self::KENYALANG]);

        $restored = LalamoveQuotation::fromArray($quotation->toArray());

        $this->assertSame($quotation->stops, $restored->stops);
        $this->assertSame(
            ['S-WAREHOUSE', 'S-KENYALANG', 'S-ASIA', 'S-SUNGAI-BULOH'],
            $restored->forStops([self::WAREHOUSE, self::KENYALANG, self::ASIA_SKYWAY, self::SUNGAI_BULOH])->stopIds,
        );
    }

    public function test_a_quotation_saved_before_it_knew_its_stops_is_used_as_is()
    {
        $old = LalamoveQuotation::fromArray([
            'id' => 'QUO-OLD',
            'expiresAt' => Carbon::now()->addMinutes(5)->toIso8601String(),
            'stopIds' => ['S-0', 'S-1'],
            'routeSequence' => [0, 1],
        ]);

        $this->assertSame($old, $old->forStops([self::WAREHOUSE, self::KENYALANG]));
    }

    /**
     * Every order the stops could be quoted in, every order they could be booked in, and several ways
     * Lalamove could route them: each recipient must land on the stop at their own address.
     */
    public function test_every_quote_order_and_booking_order_puts_each_recipient_at_their_own_address()
    {
        $dropOffs = [self::KENYALANG, self::ASIA_SKYWAY, self::SUNGAI_BULOH, ['coordinates' => ['lat' => '2.92138', 'lng' => '101.65592'], 'address' => 'Casa View, Cybersouth']];
        $routes = [
            'by latitude' => fn (array $stops) => collect($stops)->sortBy(fn (array $stop) => (float) $stop['coordinates']['lat'])->keys()->all(),
            'reversed' => fn (array $stops) => array_reverse(array_keys($stops)),
            'as sent' => fn (array $stops) => array_keys($stops),
        ];
        $orders = $this->permutations(array_keys($dropOffs));
        $bookings = 0;

        foreach ($routes as $routeName => $route) {
            foreach ($orders as $quoteOrder) {
                foreach ($orders as $bookingOrder) {
                    $quoteStops = array_merge([self::WAREHOUSE], array_map(fn (int $i) => $dropOffs[$i], $quoteOrder));
                    $bookingStops = array_merge([self::WAREHOUSE], array_map(fn (int $i) => $dropOffs[$i], $bookingOrder));
                    [$response, $addressOf] = $this->lalamoveQuotes($quoteStops, $route);

                    $sent = $this->book($bookingStops, LalamoveQuotation::fromResponse($response, $quoteStops));

                    $this->assertSame(self::WAREHOUSE['address'], $addressOf[$sent['sender']['stopId']], 'The sender must be at the warehouse.');

                    foreach ($sent['recipients'] as $recipient) {
                        $this->assertSame(
                            $recipient['name'],
                            $addressOf[$recipient['stopId']],
                            "Route {$routeName}, quoted as ".implode(',', $quoteOrder).', booked as '.implode(',', $bookingOrder).": {$recipient['name']} was sent to {$addressOf[$recipient['stopId']]}.",
                        );
                    }

                    $bookings++;
                }
            }
        }

        $this->assertSame(3 * 24 * 24, $bookings);
    }

    /**
     * Lalamove's quotation response for these stops: drop-offs in the route's driving order, each with
     * a fresh stop ID, coordinates rounded to 7 decimal places.
     *
     * @return array{0: array, 1: array<string, string>} the response, and each stop ID's address
     */
    private function lalamoveQuotes(array $stops, callable $route): array
    {
        static $nextId = 1000;

        $driving = array_merge([$stops[0]], array_map(fn (int $i) => $stops[$i + 1], $route(array_slice($stops, 1))));
        $responseStops = [];

        foreach ($driving as $stop) {
            $responseStops[] = [
                'stopId' => (string) $nextId++,
                'coordinates' => ['lat' => sprintf('%.7f', $stop['coordinates']['lat']), 'lng' => sprintf('%.7f', $stop['coordinates']['lng'])],
                'address' => $stop['address'],
            ];
        }

        return [
            ['data' => ['quotationId' => 'QUO-' . $nextId, 'expiresAt' => Carbon::now()->addMinutes(5)->toIso8601String(), 'stops' => $responseStops]],
            array_column($responseStops, 'address', 'stopId'),
        ];
    }

    /**
     * Book the stops through the driver with the quotation, naming each recipient after their address.
     *
     * @return array what the driver sent to Lalamove's create order endpoint
     */
    private function book(array $stops, LalamoveQuotation $quotation): array
    {
        $sent = null;
        $client = m::mock(Lalamove::class);
        $client->shouldReceive('config')->andReturnSelf();
        $client->shouldReceive('createOrder')->once()->andReturnUsing(function (array $attributes) use (&$sent) {
            $sent = $attributes;

            return ['data' => ['orderId' => 'ORDER-1']];
        });

        (new LalamoveDriver($client))->createConsignment([
            'stops' => $stops,
            'sender' => ['stopIndex' => 0, 'name' => 'Warehouse', 'phone' => '+60129499667'],
            'recipients' => array_map(fn (int $i) => ['stopIndex' => $i, 'name' => $stops[$i]['address'], 'phone' => '+6012000000' . $i], range(1, count($stops) - 1)),
            'quotation' => $quotation->toArray(),
        ]);

        return $sent;
    }

    private function permutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $result = [];

        foreach ($items as $i => $item) {
            $rest = $items;
            unset($rest[$i]);

            foreach ($this->permutations(array_values($rest)) as $permutation) {
                $result[] = array_merge([$item], $permutation);
            }
        }

        return $result;
    }
}
