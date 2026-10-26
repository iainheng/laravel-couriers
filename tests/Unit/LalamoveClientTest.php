<?php

namespace Nextbyte\Tests\Courier\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nextbyte\Courier\Clients\Lalamove\Lalamove;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Tests\Courier\TestCase;

class LalamoveClientTest extends TestCase
{
    private MockHandler $responses;

    private function client(array $config = []): Lalamove
    {
        $this->responses = new MockHandler();

        return new Lalamove(
            array_merge(['api_key' => 'pk_test_key', 'api_secret' => 'sk_test_secret', 'market' => 'MY', 'test_mode' => true], $config),
            new Client(['handler' => HandlerStack::create($this->responses)]),
        );
    }

    private function quotationAttributes(): array
    {
        return ['serviceType' => 'MOTORCYCLE', 'stops' => []];
    }

    private function catchCourierException(callable $call): CourierException
    {
        try {
            $call();
        } catch (CourierException $exception) {
            return $exception;
        }

        $this->fail('Expected a CourierException.');
    }

    public function test_it_refuses_to_call_lalamove_without_an_api_key_and_secret()
    {
        foreach ([['api_key' => ''], ['api_secret' => ''], ['api_key' => null, 'api_secret' => null]] as $missing) {
            $client = $this->client($missing);

            $exception = $this->catchCourierException(fn () => $client->createQuotation($this->quotationAttributes()));

            $this->assertSame('Lalamove API key and secret are not configured. Set LALAMOVE_API_KEY and LALAMOVE_API_SECRET.', $exception->getMessage());
            $this->assertNull($this->responses->getLastRequest(), 'No request should reach Lalamove.');
        }
    }

    public function test_a_gateway_error_reads_as_lalamove_having_trouble()
    {
        $client = $this->client();
        $this->responses->append(new Response(502, [], '{"message":"An invalid response was received from the upstream server"}'));

        $exception = $this->catchCourierException(fn () => $client->createQuotation($this->quotationAttributes()));

        $this->assertSame(
            'Lalamove is having trouble right now (HTTP 502): An invalid response was received from the upstream server. Try again shortly.',
            $exception->getMessage(),
        );
        $this->assertSame(502, $exception->getCode());
        $this->assertInstanceOf(ServerException::class, $exception->getPrevious());
        $this->assertStringNotContainsString('rest.', $exception->getMessage());
    }

    public function test_a_validation_error_shows_lalamoves_error_id_message_and_detail()
    {
        $client = $this->client();
        $this->responses->append(new Response(422, [], json_encode(['errors' => [[
            'id' => 'ERR_INVALID_FIELD',
            'message' => 'Invalid field',
            'detail' => 'data.stops[0].coordinates.lat',
        ]]])));

        $exception = $this->catchCourierException(fn () => $client->createQuotation($this->quotationAttributes()));

        $this->assertSame('ERR_INVALID_FIELD: Invalid field (data.stops[0].coordinates.lat)', $exception->getMessage());
        $this->assertSame(422, $exception->getCode());
    }

    public function test_an_error_with_a_plain_message_shows_that_message()
    {
        $client = $this->client();
        $this->responses->append(new Response(401, [], '{"message":"Unauthorized"}'));

        $exception = $this->catchCourierException(fn () => $client->createQuotation($this->quotationAttributes()));

        $this->assertSame('Unauthorized', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
    }

    public function test_an_error_without_a_json_body_falls_back_to_the_http_reason()
    {
        $client = $this->client();
        $this->responses->append(new Response(500, [], '<html>oops</html>'));

        $exception = $this->catchCourierException(fn () => $client->createQuotation($this->quotationAttributes()));

        $this->assertSame('Lalamove is having trouble right now (HTTP 500): Internal Server Error. Try again shortly.', $exception->getMessage());
    }

    public function test_a_network_failure_says_lalamove_could_not_be_reached()
    {
        $client = $this->client();
        $this->responses->append(new ConnectException('cURL error 6: Could not resolve host: rest.sandbox.lalamove.com', new Request('POST', '/v3/quotations')));

        $exception = $this->catchCourierException(fn () => $client->createQuotation($this->quotationAttributes()));

        $this->assertSame('Could not reach Lalamove. Check the internet connection and try again.', $exception->getMessage());
        $this->assertInstanceOf(ConnectException::class, $exception->getPrevious());
    }

    public function test_a_successful_request_still_returns_lalamoves_data_signed_with_the_key()
    {
        $client = $this->client();
        $this->responses->append(new Response(201, [], '{"data":{"quotationId":"Q1"}}'));

        $this->assertSame(['data' => ['quotationId' => 'Q1']], $client->createQuotation($this->quotationAttributes()));

        $request = $this->responses->getLastRequest();
        $this->assertSame('rest.sandbox.lalamove.com', $request->getUri()->getHost());
        $this->assertStringStartsWith('hmac pk_test_key:', $request->getHeaderLine('Authorization'));
    }

    public function test_cancelling_an_order_sends_a_delete_for_it()
    {
        $client = $this->client();
        $this->responses->append(new Response(204));

        $client->cancelOrderOrFail('3607496166826754985');

        $request = $this->responses->getLastRequest();
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/v3/orders/3607496166826754985', $request->getUri()->getPath());
    }

    public function test_changing_the_driver_deletes_them_from_the_order_with_the_reason()
    {
        $client = $this->client();
        $this->responses->append(new Response(204));

        $client->changeDriver('3614557210216210614', '4960890', 'DRIVER_LATE');

        $request = $this->responses->getLastRequest();
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/v3/orders/3614557210216210614/drivers/4960890', $request->getUri()->getPath());
        $this->assertSame('{"data":{"reason":"DRIVER_LATE"}}', (string) $request->getBody());
        $this->assertStringStartsWith('hmac pk_test_key:', $request->getHeaderLine('Authorization'));
    }

    public function test_a_refused_driver_change_explains_why()
    {
        $client = $this->client();
        $this->responses->append(new Response(422, [], '{"errors":[{"id":"ERR_INVALID_FIELD","message":"Driver cannot be changed."}]}'));

        $exception = $this->catchCourierException(fn () => $client->changeDriver('3614557210216210614', '4960890', 'DRIVER_LATE'));

        $this->assertSame('ERR_INVALID_FIELD: Driver cannot be changed.', $exception->getMessage());
    }

    public function test_a_refused_cancellation_explains_why()
    {
        $client = $this->client();
        $this->responses->append(new Response(422, [], '{"errors":[{"id":"ERR_CANCELLATION","message":"Cannot cancel order."}]}'));

        $exception = $this->catchCourierException(fn () => $client->cancelOrderOrFail('3607496166826754985'));

        $this->assertSame('ERR_CANCELLATION: Cannot cancel order.', $exception->getMessage());
        $this->assertSame(422, $exception->getCode());
    }

    public function test_cancel_order_still_returns_false_when_refused()
    {
        $client = $this->client();
        $this->responses->append(new Response(422, [], '{"errors":[{"id":"ERR_CANCELLATION","message":"Cannot cancel order."}]}'));

        $this->assertFalse($client->cancelOrder('3607496166826754985'));
    }

    public function test_it_gets_the_markets_cities_and_their_vehicles()
    {
        $client = $this->client();
        $this->responses->append(new Response(200, [], json_encode(['data' => [['locode' => 'MY KUL', 'services' => [['key' => 'CAR']]]]])));

        $cities = $client->getCities();

        $request = $this->responses->getLastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/v3/cities', $request->getUri()->getPath());
        $this->assertSame('MY', $request->getHeaderLine('Market'));
        $this->assertSame('MY KUL', $cities['data'][0]['locode']);
    }
}
