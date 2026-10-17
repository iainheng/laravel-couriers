<?php

namespace Nextbyte\Tests\Courier\Unit;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nextbyte\Courier\Clients\DhlEcommerce\DhlEcommerce;
use Nextbyte\Courier\Contracts\Cancellable;
use Nextbyte\Courier\Drivers\DhlEcommerce\DhlEcommerceDriver;
use Nextbyte\Courier\Drivers\Lalamove\LalamoveDriver;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Tests\Courier\TestCase;

/**
 * DHL eCommerce Delete/Cancel Shipment (POST /rest/v2/Label/Delete).
 *
 * Responses follow the field tables in DHL's docs
 * (https://api.dhlecommerce.dhl.com/API/docs/v2/deleteshipment.html); DHL only
 * publishes its examples as images, and its test environment was down (502) when
 * this was written, so they are not captured from DHL.
 */
class DhlEcommerceCancelTest extends TestCase
{
    private MockHandler $responses;

    private function driver(): DhlEcommerceDriver
    {
        $this->responses = new MockHandler();

        // The client reads its access token from the cache, so no login request is made.
        $this->app['cache']->put('dhl-ecommerce', ['token' => 'c9fe593442654a94a897cc6e5e201970', 'expires' => Carbon::now()->addHour()]);

        $client = new DhlEcommerce([
            'test_mode' => true,
            'account_pickup' => '5999999201',
            'account_soldto' => '5999999108',
        ], new Client(['handler' => HandlerStack::create($this->responses)]), $this->app['cache']);

        return new DhlEcommerceDriver($client);
    }

    private function deleteResponse(string $shipmentId, string $itemMessage, array $details = [], string $serviceMessage = 'SUCCESS'): Response
    {
        $status = fn (string $message, array $messageDetails) => array_filter([
            'code' => $message === 'SUCCESS' ? '200' : '204',
            'message' => $message,
            'messageDetails' => array_map(fn ($detail) => ['messageDetail' => $detail], $messageDetails),
        ]);

        return new Response(200, [], json_encode(['deleteShipmentResp' => [
            'hdr' => ['messageType' => 'DELETESHIPMENT', 'messageDateTime' => '2026-10-17T16:40:00+08:00', 'messageVersion' => '1.4', 'messageLanguage' => 'en'],
            'bd' => [
                'shipmentItems' => [['shipmentID' => $shipmentId, 'responseStatus' => $status($itemMessage, $details)]],
                'responseStatus' => $status($serviceMessage, $serviceMessage === 'SUCCESS' ? [] : $details),
            ],
        ]]));
    }

    public function test_couriers_that_can_cancel_are_cancellable()
    {
        $this->assertInstanceOf(Cancellable::class, $this->driver());
        $this->assertTrue(is_subclass_of(LalamoveDriver::class, Cancellable::class));
    }

    public function test_it_cancels_a_shipment_by_its_shipment_id()
    {
        $driver = $this->driver();
        $this->responses->append($this->deleteResponse('MYLIL14021005', 'SUCCESS'));

        $driver->cancelConsignmentOrFail('MYLIL14021005');

        $request = $this->responses->getLastRequest();
        $body = json_decode((string) $request->getBody(), true);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('apitest.dhlecommerce.asia', $request->getUri()->getHost());
        $this->assertSame('/rest/v2/Label/Delete', $request->getUri()->getPath());
        $this->assertSame('DELETESHIPMENT', $body['deleteShipmentReq']['hdr']['messageType']);
        $this->assertSame('c9fe593442654a94a897cc6e5e201970', $body['deleteShipmentReq']['hdr']['accessToken']);
        $this->assertSame('5999999201', $body['deleteShipmentReq']['bd']['pickupAccountId']);
        $this->assertSame('5999999108', $body['deleteShipmentReq']['bd']['soldToAccountId']);
        $this->assertSame([['shipmentID' => 'MYLIL14021005']], $body['deleteShipmentReq']['bd']['shipmentItems']);
    }

    public function test_a_refused_cancellation_explains_why()
    {
        $driver = $this->driver();
        $this->responses->append($this->deleteResponse('MYLIL14021005', 'FAILURE', ['Shipment cannot be cancelled after pick-up.'], 'PARTIAL SUCCESS'));

        try {
            $driver->cancelConsignmentOrFail('MYLIL14021005');
            $this->fail('Expected a CourierException.');
        } catch (CourierException $exception) {
            $this->assertSame('DHL could not cancel shipment MYLIL14021005: MYLIL14021005: Shipment cannot be cancelled after pick-up.', $exception->getMessage());
        }
    }

    public function test_a_refusal_with_only_a_service_level_status_is_explained_too()
    {
        $driver = $this->driver();
        $this->responses->append(new Response(200, [], json_encode(['deleteShipmentResp' => [
            'hdr' => ['messageType' => 'DELETESHIPMENT'],
            'bd' => ['responseStatus' => ['code' => '204', 'message' => 'FAILURE', 'messageDetails' => [['messageDetail' => 'Invalid pickupAccountId']]]],
        ]])));

        $this->expectException(CourierException::class);
        $this->expectExceptionMessage('DHL could not cancel shipment MYLIL14021005: Invalid pickupAccountId');

        $driver->cancelConsignmentOrFail('MYLIL14021005');
    }

    public function test_cancel_consignment_returns_false_when_refused()
    {
        $driver = $this->driver();
        $this->responses->append($this->deleteResponse('MYLIL14021005', 'FAILURE', ['Shipment cannot be cancelled after pick-up.'], 'PARTIAL SUCCESS'));

        $this->assertFalse($driver->cancelConsignment('MYLIL14021005'));
    }
}
