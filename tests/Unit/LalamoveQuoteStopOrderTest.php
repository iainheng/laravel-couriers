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
}
