<?php

namespace Nextbyte\Tests\Courier\Unit;

use Mockery as m;
use Nextbyte\Courier\Clients\Lalamove\Lalamove;
use Nextbyte\Courier\Contracts\ProvidesServiceTypes;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveDriver;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\ServiceType;
use Nextbyte\Courier\SpecialRequest;
use Nextbyte\Tests\Courier\TestCase;

class LalamoveServiceTypesTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    private function driver(array $config = [], ?array $cities = null): LalamoveDriver
    {
        $client = m::mock(Lalamove::class);
        $client->shouldReceive('config')->andReturnSelf();
        $client->shouldReceive('getCities')->andReturn($cities ?? json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/data/lalamove/get_cities_response.json'),
            true
        ));

        return (new LalamoveDriver($client))->config($config);
    }

    public function test_lalamove_lists_its_service_types()
    {
        $this->assertInstanceOf(ProvidesServiceTypes::class, $this->driver());
    }

    public function test_it_lists_the_configured_citys_vehicles_with_their_limits_in_kg_and_cm()
    {
        $types = $this->driver(['city' => 'KUL'])->serviceTypes();

        $this->assertSame(['MOTORCYCLE', 'CAR', '4X4', 'VAN', 'TRUCK330', 'TRUCK550'], array_map(fn (ServiceType $type) => $type->key, $types));

        $car = $types[1];
        $this->assertSame('Ideal for groceries, food, flowers, parcels, fragile goods', $car->description);
        $this->assertSame(40.0, $car->maxWeightKg);
        $this->assertSame([50.0, 50.0, 50.0], [$car->lengthCm, $car->widthCm, $car->heightCm]);
        $this->assertSame([170.0, 100.0, 120.0], [$types[3]->lengthCm, $types[3]->widthCm, $types[3]->heightCm]);
    }

    public function test_each_vehicle_lists_its_special_requests()
    {
        [$motorcycle, $car, , $van] = $this->driver(['city' => 'KUL'])->serviceTypes();

        $this->assertSame([], $motorcycle->specialRequests);
        $this->assertSame(['ROUND_TRIP', 'DOOR_TO_DOOR', 'DOOR_TO_DOOR_1DRIVER'], array_map(fn (SpecialRequest $request) => $request->key, $car->specialRequests));
        $this->assertSame('Door-to-door (loading & unloading by driver)', $car->specialRequest('DOOR_TO_DOOR_1DRIVER')->option());
        $this->assertNull($car->specialRequest('DOOR_TO_DOOR')->group);
        $this->assertNull($car->specialRequest('DOOR_TO_DOOR_3'));

        $helper = $van->specialRequest('DOOR_TO_DOOR_3');
        $this->assertSame('Door-to-Door Service · by Driver + 1 Helper', $helper->description, "Lalamove's lost \"+\" is put back.");
        $this->assertSame(['Door-to-Door Service', 'by Driver + 1 Helper', 1], [$helper->group, $helper->option(), $helper->maxSelection]);
    }

    public function test_special_requests_survive_being_cached_as_an_array()
    {
        $car = $this->driver(['city' => 'KUL'])->serviceTypes()[1];

        $this->assertEquals($car, ServiceType::fromArray($car->toArray()));
        $this->assertSame([], ServiceType::fromArray(['key' => 'CAR', 'description' => 'Car'])->specialRequests, 'An array cached before special requests were kept.');
    }

    public function test_the_city_can_be_given_with_or_without_the_market()
    {
        $this->assertCount(6, $this->driver()->serviceTypes('MY KUL'));
        $this->assertCount(6, $this->driver()->serviceTypes('kul'));
        $this->assertCount(1, $this->driver(['city' => 'KUL'])->serviceTypes('KCH'));
    }

    public function test_a_description_that_only_repeats_the_key_is_dropped()
    {
        $this->assertSame('', $this->driver()->serviceTypes('KCH')[0]->description);
    }

    public function test_an_unknown_city_names_the_cities_lalamove_serves()
    {
        try {
            $this->driver()->serviceTypes('PEN');
            $this->fail('Expected a CourierException.');
        } catch (CourierException $exception) {
            $this->assertSame('Lalamove does not serve city PEN. Cities: MY KUL (Kuala Lumpur), MY KCH (Kuching(Sandbox)).', $exception->getMessage());
        }
    }

    public function test_without_a_city_it_asks_for_one_unless_the_market_has_only_one()
    {
        try {
            $this->driver()->serviceTypes();
            $this->fail('Expected a CourierException.');
        } catch (CourierException $exception) {
            $this->assertStringStartsWith('Set LALAMOVE_CITY to the city deliveries start from. Cities: MY KUL', $exception->getMessage());
        }

        $single = ['data' => [['locode' => 'SG SIN', 'name' => 'Singapore', 'services' => [['key' => 'CAR']]]]];
        $this->assertSame('CAR', $this->driver([], $single)->serviceTypes()[0]->key);
    }

    public function test_a_service_type_survives_a_round_trip_through_an_array()
    {
        $car = $this->driver()->serviceTypes('KUL')[1];

        $this->assertEquals($car, ServiceType::fromArray($car->toArray()));
    }

    public function test_missing_or_unusual_units_are_handled()
    {
        $type = ServiceType::fromLalamove([
            'key' => 'X',
            'load' => ['value' => '2', 'unit' => 't'],
            'dimensions' => ['length' => ['value' => '120', 'unit' => 'cm'], 'width' => ['value' => 'n/a']],
        ]);

        $this->assertSame(2000.0, $type->maxWeightKg);
        $this->assertSame(120.0, $type->lengthCm);
        $this->assertNull($type->widthCm);
        $this->assertNull($type->heightCm);
    }
}
