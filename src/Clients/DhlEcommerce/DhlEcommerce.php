<?php

namespace Nextbyte\Courier\Clients\DhlEcommerce;

use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Nextbyte\Courier\Clients\DhlEcommerce\Responses\AccessTokenApiResponse;
use Nextbyte\Courier\Clients\DhlEcommerce\Responses\DhlEcommerceResponse;
use Nextbyte\Courier\Clients\DhlEcommerce\Responses\LabelReprintResponse;
use Nextbyte\Courier\Clients\DhlEcommerce\Responses\LabelResponse;
use Nextbyte\Courier\Clients\DhlEcommerce\Responses\TrackingItemResponse;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\Exceptions\ClientResponseException;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\ShipmentStatusPush;

class DhlEcommerce
{
    const ENDPOINT_AUTH = 'AUTH';
    const ENDPOINT_LABEL = 'LABEL';
    const ENDPOINT_LABEL_REPRINT = 'LABELREPRINT';
    const ENDPOINT_TRACK_ITEM = 'TRACKITEM';

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * @var CacheManager
     */
    protected $cache;

    /**
     * The production API endpoint.
     *
     * @var string
     */
    protected $endpoint = 'https://api.dhlecommerce.dhl.com/';

    /**
     * The sandbox API endpoint.
     *
     * @var string
     */
    protected $sandboxEndpoint = 'https://apitest.dhlecommerce.asia/';

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $accountSoldTo;

    /**
     * @var string
     */
    protected $accountPickup;

    /**
     * @var bool
     */
    protected $testMode;

    /**
     * @var array
     */
    protected $accessToken;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * Create a new Gdex client instance.
     *
     * @param string $endpoint
     * @return void
     */
    public function __construct($config, \GuzzleHttp\Client $client = null, CacheManager $cache = null)
    {
        $this->config($config);

        $this->client = $client ?? new \GuzzleHttp\Client();
        $this->cache = $cache;

        $this->accessToken = $this->cache->get('dhl-ecommerce');
    }

    /**
     * @param array $config
     * @return $this
     */
    public function config(array $config = [])
    {
        $this->clientId = data_get($config, 'client_id', $this->clientId);
        $this->password = data_get($config, 'password', $this->password);
        $this->accountSoldTo = data_get($config, 'account_soldto', $this->accountSoldTo);
        $this->accountPickup = data_get($config, 'account_pickup', $this->accountPickup);
        $this->testMode = data_get($config, 'test_mode', $this->testMode);

        $this->accessToken = data_get($config, 'access_token', $this->accessToken);
        $this->debug = data_get($config, 'debug', $this->debug);

        return $this;
    }

    /**
     * @param array $data
     * @return \stdClass
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function login()
    {
        try {
            $url = $this->getBaseUrl() . '/rest/v1/OAuth/AccessToken';

            $options = [
                'query' => [
                    'clientId' => $this->clientId,
                    'password' => $this->password,
                ]
            ];

            $this->debug('Getting new access token.');

            $result = $this->client->get($url, $options);

            $response = new AccessTokenApiResponse(json_decode($result->getBody(), true));

            $this->validateAndThrowResponse($response);

            $tokenData = [
                'token' => data_get($response->getData(), 'token'),
                'expires' => now()->addSeconds(data_get($response->getData(), 'expires_in_seconds', 0))
            ];

            $this->cache->put('dhl-ecommerce', $tokenData);
            $this->accessToken = $tokenData;

            $this->debug($this->accessToken);

        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }
    }

    /**
     * @param string $url
     * @param string $messageType
     * @param string $requestKey
     * @param array|null $data
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($url, $messageType, $requestKey = null, $data = []) : array
    {
        try {
            $token = $this->getAccessToken();

            $postData = [
                'hdr' => array_merge([
                    'messageType' => $messageType,
                    'messageDateTime' => now()->toIso8601String(),
                    'messageVersion' => '1.4',
                    'accessToken' => $token,
                ], data_get($data, 'hdr', [])),
                'bd' => array_merge([
                    'pickupAccountId' => $this->accountPickup,
                    'soldToAccountId' => $this->accountSoldTo,
                ], data_get($data, 'bd', [])),
            ];

            if ($requestKey) {
                $postData = [$requestKey => $postData];
            }

            $options['json'] = $postData;

//            if ($parameters) {
//                $options['json'] = $parameters;
//            }

            $this->debug(json_encode($postData));

            $res = $this->client->post($url, $options);
            $result = json_decode($res->getBody(), true);

//            $result = file_get_contents('../../tests/Fixtures/data/dhl-ecommerce/label_response.json');

            $this->debug(json_encode($result));

            return $result;
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        } catch (\GuzzleHttp\Exception\RequestException $ex) {
            $this->debug($ex->getResponse()->getBody()->getContents());
            throw $ex;
        }
    }

    /**
     * @param array $payload
     * @return LabelResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createConsignment(array $payload)
    {
        $url = $this->getBaseUrl() . '/rest/v2/Label';

        $requestKey = 'labelRequest';

        $response = new LabelResponse($this->request($url, self::ENDPOINT_LABEL, $requestKey, $payload));

        return $response;
    }

    /**
     * @param array $payload
     * @return LabelResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createConsignmentWithSlip(array $payload)
    {
        return $this->createConsignment($payload);
    }

    /**
     * @param array $payload
     * @return LabelReprintResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function reprintConsignmentSlip(array $payload)
    {
        $url = $this->getBaseUrl() . '/rest/v2/Label/Reprint';

        $requestKey = 'labelReprintRequest';

        $shipmentId = data_get($payload, 'bd.shipmentItems.0.shipmentID');

        $pieces = collect(data_get($payload, 'bd.shipmentItems.0.shipmentPieces'))->pluck('pieceID');

        $payload = [
            'bd' => [
                'shipmentItems' => $pieces->map(function ($pieceId) use ($pieces, $shipmentId) {
                    return ['shipmentID' => (count($pieces) > 1) ? $shipmentId . '-' . $pieceId : $shipmentId];
                })->all()
            ]
        ];

        $response = new LabelReprintResponse($this->request($url, self::ENDPOINT_LABEL_REPRINT, $requestKey, $payload));

        return $response;
    }

    public function createShipmentStatusPush($attributes = [], callable $statusCallback = null)
    {
//        if (!$this->validateRequestData($attributes))
//            throw new AuthorizationException('You are not authorized to perform this action. Signature token is invalid.');
        $pushDatetime = data_get($attributes, 'pushTrackItemRequest.hdr.messageDateTime');
        $shipment = data_get($attributes, 'pushTrackItemRequest.bd.shipmentItems.0');

        $orderNumber = data_get($shipment, 'shipmentID');
        $trackingNumber = data_get($shipment, 'trackingID');
        $events = collect(data_get($shipment, 'events', []));
        $lastEvent = $events->first();

        $status = data_get($lastEvent, 'status');

        if ($statusCallback) {
            $status = $statusCallback($status);
        }

        $pushStatus = ShipmentStatusPush::create([
            'orderNumber' => $orderNumber,
            'consignmentNumber' => $trackingNumber,
            'status' => $status,
            'description' => data_get($lastEvent, 'description', ShipmentStatus::getDescription($status)),
            'date' => Carbon::parse(data_get($lastEvent, 'dateTime')),
            'currentCity' => data_get($lastEvent, 'address.city'),
            'nextCity' => null,
            'remarks' => data_get($lastEvent, 'description'),
        ]);

        return $pushStatus;
    }

    /**
     * @param string $trackingNumber
     * @return TrackingItemResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getShipmentStatusDetail(string $trackingNumber)
    {
        $url = $this->getBaseUrl() . '/rest/v3/Tracking';

        $requestKey = 'trackItemRequest';

        $payload = [
            'bd' => [
                'ePODRequired' => 'Y',
                'trackingReferenceNumber' => [$trackingNumber],
            ]
        ];

        $response = new TrackingItemResponse($this->request($url, self::ENDPOINT_TRACK_ITEM, $requestKey, $payload));

        return $response;
    }

    /**
     * @param string $endpoint
     * @return string
     */
    protected function getBaseUrl(): string
    {
        $url = rtrim($this->testMode ? $this->sandboxEndpoint : $this->endpoint, '/');

        return "{$url}";
    }

    /**
     * @param DhlEcommerceResponse $response
     * @return void
     */
    protected function validateAndThrowResponse(DhlEcommerceResponse $response)
    {
        if (! $response->isSuccess()) {
            throw new ClientResponseException($response->getMessage(), $response->getMessageDetailsText(), $response->getData(), $response->getStatusCode());
        }
    }

    /**
     * @param ClientException $exception
     * @return \Exception
     */
    protected function determineException(ClientException $exception)
    {
        $statusCode = (int) $exception->getResponse()->getStatusCode();

        if ($statusCode >= 400 && $statusCode < 500) {
            return new CourierException($exception->getMessage(), $statusCode);
        }

        return $exception;
    }

    /**
     * @return string|null
     */
    protected function getAccessToken()
    {
        $token = data_get($this->accessToken, 'token');

        if (!$token || $this->isAccessTokenExpired()) {
            $this->login();
        }

        return data_get($this->accessToken, 'token');
    }

    /**
     * @return bool
     */
    protected function isAccessTokenExpired()
    {
        $expires = data_get($this->accessToken, 'expires');

        if ($expires && !$expires instanceof Carbon)
            $expires = Carbon::parse($expires);

        return !($expires && $expires->greaterThanOrEqualTo(now()->addSeconds(300)));
    }

    public function debug($message)
    {
        if ($this->debug) {
            if (App::runningInConsole()) {
                dump($message);
            } else {
                if (!is_string($message))
                    $message = json_encode($message);

                Log::debug($message);
            }
        }
    }
}
