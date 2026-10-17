<?php

namespace Nextbyte\Tests\Courier\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nextbyte\Courier\Clients\DhlEcommerce\DhlEcommerce;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Tests\Courier\TestCase;
use Symfony\Component\VarDumper\VarDumper;

/**
 * DHL's login only accepts the client ID and password as query parameters, so the client
 * must keep that URL out of exception messages and its debug output.
 */
class DhlEcommerceAuthTest extends TestCase
{
    private const CLIENT_ID = 'LTE-test-client-id';

    private const PASSWORD = 'sEcReT-dhl-password';

    private MockHandler $responses;

    /**
     * @var array<int, string>
     */
    private array $dumped = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['cache']->forget('dhl-ecommerce');
        VarDumper::setHandler(function ($value) {
            $this->dumped[] = is_string($value) ? $value : json_encode($value);
        });
    }

    protected function tearDown(): void
    {
        VarDumper::setHandler(null);

        parent::tearDown();
    }

    private function client(): DhlEcommerce
    {
        $this->responses = new MockHandler();

        return new DhlEcommerce([
            'test_mode' => true,
            'client_id' => self::CLIENT_ID,
            'password' => self::PASSWORD,
            'account_pickup' => '5999999201',
            'account_soldto' => '5999999108',
            'debug' => true,
        ], new Client(['handler' => HandlerStack::create($this->responses)]), $this->app['cache']);
    }

    private function catchLoginFailure(DhlEcommerce $client): CourierException
    {
        try {
            $client->deleteShipment(['MYLIL1']);
        } catch (CourierException $exception) {
            return $exception;
        }

        $this->fail('Expected the login to fail.');
    }

    private function assertNoCredentials(string $text): void
    {
        $this->assertStringNotContainsString(self::PASSWORD, $text);
        $this->assertStringNotContainsString(self::CLIENT_ID, $text);
        $this->assertStringNotContainsString(urlencode(self::CLIENT_ID), $text);
    }

    public function test_a_gateway_error_at_login_does_not_reveal_the_credentials()
    {
        $client = $this->client();
        $this->responses->append(new Response(502, [], '<html>502 Bad Gateway</html>'));

        $exception = $this->catchLoginFailure($client);

        $this->assertSame('DHL eCommerce login failed (HTTP 502).', $exception->getMessage());
        $this->assertSame(502, $exception->getCode());
        $this->assertNull($exception->getPrevious(), 'The Guzzle exception repeats the URL with the credentials.');
        $this->assertNoCredentials((string) $exception);
    }

    public function test_a_rejected_login_does_not_reveal_the_credentials()
    {
        $client = $this->client();
        $this->responses->append(new Response(401, [], '{"message":"Unauthorized"}'));

        $exception = $this->catchLoginFailure($client);

        $this->assertSame('DHL eCommerce login failed (HTTP 401).', $exception->getMessage());
        $this->assertNoCredentials((string) $exception);
    }

    public function test_an_unreachable_dhl_does_not_reveal_the_credentials()
    {
        $client = $this->client();
        $this->responses->append(new ConnectException(
            'cURL error 7: Failed to connect to apitest.dhlecommerce.asia (see https://apitest.dhlecommerce.asia/rest/v1/OAuth/AccessToken?clientId='.urlencode(self::CLIENT_ID).'&password='.self::PASSWORD.')',
            new Request('GET', 'https://apitest.dhlecommerce.asia/rest/v1/OAuth/AccessToken'),
        ));

        $exception = $this->catchLoginFailure($client);

        $this->assertSame('DHL eCommerce login failed: could not reach DHL.', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertNoCredentials((string) $exception);
    }

    public function test_debug_output_never_shows_the_credentials_or_the_access_token()
    {
        $client = $this->client();
        $this->responses->append(
            new Response(200, [], json_encode(['accessTokenResponse' => [
                'token' => 'tok-0123456789abcdef',
                'token_type' => 'Bearer',
                'expire_in_seconds' => '86400',
                'client_id' => self::CLIENT_ID,
                'responseStatus' => ['code' => '100000', 'message' => 'SUCCESS'],
            ]])),
            new Response(200, [], json_encode(['deleteShipmentResp' => ['bd' => [
                'shipmentItems' => [['shipmentID' => 'MYLIL1', 'responseStatus' => ['code' => '200', 'message' => 'SUCCESS']]],
                'responseStatus' => ['code' => '200', 'message' => 'SUCCESS'],
            ]]])),
        );

        $client->deleteShipment(['MYLIL1']);

        $output = implode("\n", $this->dumped);
        $this->assertNotEmpty($this->dumped);
        $this->assertStringNotContainsString(self::PASSWORD, $output);
        $this->assertStringNotContainsString('tok-0123456789abcdef', $output);
        $this->assertStringContainsString('"accessToken":"***"', $output);

        $deleteRequest = $this->responses->getLastRequest();
        $this->assertSame('tok-0123456789abcdef', json_decode((string) $deleteRequest->getBody(), true)['deleteShipmentReq']['hdr']['accessToken'], 'DHL still receives the real token.');
    }

    public function test_the_token_lifetime_is_read_from_dhls_documented_field()
    {
        $client = $this->client();
        $this->responses->append(
            new Response(200, [], json_encode(['accessTokenResponse' => [
                'token' => 'tok-0123456789abcdef',
                'expire_in_seconds' => '86400',
                'responseStatus' => ['code' => '100000', 'message' => 'SUCCESS'],
            ]])),
            new Response(200, [], json_encode(['deleteShipmentResp' => ['bd' => ['responseStatus' => ['code' => '200', 'message' => 'SUCCESS']]]])),
        );

        $client->deleteShipment(['MYLIL1']);

        $cached = $this->app['cache']->get('dhl-ecommerce');
        $this->assertTrue($cached['expires']->greaterThan(now()->addHours(23)), 'A 24-hour token should not be treated as expired.');
    }
}
