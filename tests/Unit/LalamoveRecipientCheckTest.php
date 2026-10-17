<?php

namespace Nextbyte\Tests\Courier\Unit;

use Carbon\Carbon;
use Mockery as m;
use Nextbyte\Courier\Clients\Lalamove\Lalamove;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveDriver;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Tests\Courier\TestCase;

/**
 * After booking, the driver checks Lalamove put each recipient at their own address, and cancels
 * the order when it did not: a hamper, name and phone at the wrong door must never go out.
 */
class LalamoveRecipientCheckTest extends TestCase
{
    private const STOPS = [
        ['coordinates' => ['lat' => '3.00982', 'lng' => '101.75597'], 'address' => 'Warehouse, Balakong'],
        ['coordinates' => ['lat' => '3.1545257', 'lng' => '101.5894092'], 'address' => '38 Jalan Kenyalang'],
        ['coordinates' => ['lat' => '3.0740764', 'lng' => '101.6156681'], 'address' => 'Asia Skyway Express'],
    ];

    /**
     * @var array<int, string>
     */
    private array $cancelled = [];

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    private function attributes(): array
    {
        return [
            'serviceType' => 'CAR',
            'stops' => self::STOPS,
            'sender' => ['stopIndex' => 0, 'name' => 'Empire Hamper', 'phone' => '+60129499667'],
            'recipients' => [
                ['stopIndex' => 1, 'name' => 'Gogillan Sevarathanam', 'phone' => '+60163747160', 'remarks' => 'Order 14004-000'],
                ['stopIndex' => 2, 'name' => 'Jaret Boy', 'phone' => '+60162055410', 'remarks' => 'Order 14008-036'],
            ],
            'quotation' => [
                'id' => 'QUO-1',
                'expiresAt' => Carbon::now()->addMinutes(5)->toIso8601String(),
                'stopIds' => ['S-0', 'S-1', 'S-2'],
                'routeSequence' => [0, 1, 2],
            ],
        ];
    }

    /**
     * Lalamove's stops for the order, naming who is at each address (null = none).
     *
     * @param array<int, string|null> $names by index into STOPS
     */
    private function stopsNaming(array $names): array
    {
        return array_map(fn (int $i) => self::STOPS[$i] + ['stopId' => 'S-' . $i, 'name' => $names[$i] ?? null, 'phone' => '0123456789'], array_keys(self::STOPS));
    }

    /**
     * @param array|null $orderStops     stops in the create order response; null for none
     * @param array|null $fetchedStops   stops when the order is fetched; null if it is never fetched
     */
    private function driver(?array $orderStops, ?array $fetchedStops = null, ?string $cancelRefusal = null): LalamoveDriver
    {
        $client = m::mock(Lalamove::class);
        $client->shouldReceive('config')->andReturnSelf();
        $client->shouldNotReceive('createQuotation');
        $client->shouldReceive('createOrder')->once()->andReturn(['data' => array_filter([
            'orderId' => 'LLM-1',
            'shareLink' => 'https://share.sandbox.lalamove.com?LLM-1',
            'stops' => $orderStops,
        ], fn ($value) => $value !== null)]);

        if ($fetchedStops === null) {
            $client->shouldNotReceive('getOrder');
        } else {
            $client->shouldReceive('getOrder')->with('LLM-1')->once()->andReturn(['data' => ['orderId' => 'LLM-1', 'stops' => $fetchedStops]]);
        }

        $client->shouldReceive('cancelOrderOrFail')->andReturnUsing(function (string $orderId) use ($cancelRefusal) {
            if ($cancelRefusal) {
                throw new CourierException($cancelRefusal);
            }

            $this->cancelled[] = $orderId;
        });

        return new LalamoveDriver($client);
    }

    private function bookingFails(LalamoveDriver $driver): CourierException
    {
        try {
            $driver->createConsignment($this->attributes());
        } catch (CourierException $exception) {
            return $exception;
        }

        $this->fail('The booking should have been refused.');
    }

    public function test_a_booking_with_everyone_at_their_own_address_goes_ahead()
    {
        $driver = $this->driver($this->stopsNaming(['Empire Hamper', 'Gogillan Sevarathanam', 'Jaret Boy']));

        $this->assertSame('LLM-1', $driver->createConsignment($this->attributes()));
        $this->assertSame([], $this->cancelled);
    }

    public function test_names_match_regardless_of_case_spacing_punctuation_or_being_cut_short()
    {
        $driver = $this->driver($this->stopsNaming(['EMPIRE HAMPER', ' gogillan  sevarathanam ', 'JARET-BOY']));

        $this->assertSame('LLM-1', $driver->createConsignment($this->attributes()));

        $driver = $this->driver($this->stopsNaming(['Empire Hamper', 'Gogillan Sevar', 'Jaret Boy']));
        $this->assertSame('LLM-1', $driver->createConsignment($this->attributes()), 'A name Lalamove shortened is still the same person.');
    }

    public function test_swapped_recipients_cancel_the_order_and_say_who_is_where()
    {
        $exception = $this->bookingFails($this->driver($this->stopsNaming(['Empire Hamper', 'Jaret Boy', 'Gogillan Sevarathanam'])));

        $this->assertSame(['LLM-1'], $this->cancelled);
        $this->assertSame(
            'Lalamove put recipients at the wrong addresses: Gogillan Sevarathanam is at Asia Skyway Express instead of 38 Jalan Kenyalang; '
            . 'Jaret Boy is at 38 Jalan Kenyalang instead of Asia Skyway Express. '
            . 'Lalamove order LLM-1 was cancelled, so nothing was booked. Try again; if it happens again, book these deliveries one by one.',
            $exception->getMessage(),
        );
    }

    public function test_a_recipient_left_off_every_stop_cancels_the_order()
    {
        $exception = $this->bookingFails($this->driver($this->stopsNaming(['Empire Hamper', 'Gogillan Sevarathanam', 'Someone Else'])));

        $this->assertSame(['LLM-1'], $this->cancelled);
        $this->assertStringContainsString('Jaret Boy is on no stop', $exception->getMessage());
    }

    public function test_when_the_mixed_up_order_cannot_be_cancelled_staff_are_told_to_cancel_it()
    {
        $exception = $this->bookingFails($this->driver(
            $this->stopsNaming(['Empire Hamper', 'Jaret Boy', 'Gogillan Sevarathanam']),
            null,
            'ERR_CANCELLATION: Cannot cancel order.',
        ));

        $this->assertStringContainsString('Lalamove order LLM-1 could NOT be cancelled (ERR_CANCELLATION: Cannot cancel order.): cancel it in Lalamove now', $exception->getMessage());
    }

    public function test_without_recipients_in_the_response_the_order_is_fetched_and_checked()
    {
        $exception = $this->bookingFails($this->driver(null, $this->stopsNaming(['Empire Hamper', 'Jaret Boy', 'Gogillan Sevarathanam'])));

        $this->assertSame(['LLM-1'], $this->cancelled);
        $this->assertStringStartsWith('Lalamove put recipients at the wrong addresses', $exception->getMessage());

        $this->cancelled = [];
        $driver = $this->driver(null, $this->stopsNaming(['Empire Hamper', 'Gogillan Sevarathanam', 'Jaret Boy']));
        $this->assertSame('LLM-1', $driver->createConsignment($this->attributes()));
    }

    public function test_when_lalamove_does_not_say_who_is_at_each_stop_the_booking_stands()
    {
        $driver = $this->driver(null, $this->stopsNaming([]));

        $this->assertSame('LLM-1', $driver->createConsignment($this->attributes()));
        $this->assertSame([], $this->cancelled);
    }
}
