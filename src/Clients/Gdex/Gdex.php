<?php

namespace Nextbyte\Courier\Clients\Gdex;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Nextbyte\Courier\Exceptions\CourierException;

class Gdex
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The Gdex API endpoint.
     *
     * @var string
     */
    protected $endpoint = 'https://myopenapi.gdexpress.com/api/prime';

    /**
     * The Gdex Sandbox API endpoint.
     *
     * @var string
     */
    protected $sandboxEndpoint = 'https://myopenapi.gdexpress.com/api/demo/prime';

    /**
     * @var string
     */
    protected $subscriptionKey;

    /**
     * @var string
     */
    protected $apiToken;

    /**
     * @var bool
     */
    protected $testMode;

    /**
     * @var int
     */
    protected $accountNo;

    /**
     * Create a new Gdex client instance.
     *
     * @param string $endpoint
     * @return void
     */
    public function __construct($config, \GuzzleHttp\Client $client = null)
    {
        $this->config($config);

        $this->client = $client ?? new \GuzzleHttp\Client();
    }

    /**
     * @param array $config
     * @return $this
     */
    public function config(array $config = [])
    {
        $this->apiToken = data_get($config, 'api_token');
        $this->subscriptionKey = data_get($config, 'subscription_key');
        $this->testMode = data_get($config, 'test_mode');
        $this->accountNo = data_get($config, 'account_no');

        return $this;
    }

    /**
     * @param array $data
     * @return \stdClass
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createConsignment(array $payload)
    {
        try {
            $options = ['headers' => $this->getHeaders(), 'form_params' => [
//                'accountNo' => $accountNo
            ]];

            if ($payload) {
                $options['json'] = $payload;
            }

            $response = $this->client->post($this->getEndpointUrl("CreateConsignment"), $options);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        $response = json_decode($response->getBody()->getContents(), true);

        return json_decode(json_encode(array_merge($response, [
            'success' => data_get($response, 's') === 'success',
        ])));
    }

    /**
     * @param string $consignmentNumber
     * @return \Psr\Http\Message\ResponseInterface
     * @throws CourierException
     */
    public function getZippedConsignmentImage($consignmentNumber)
    {
        try {
            $options = ['headers' => $this->getHeaders(), 'form_params' => [
            ]];

            $options['json'] = [$consignmentNumber];

            $response = $this->client->post($this->getEndpointUrl("GetConsignmentDocument"), $options);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        return $response;
    }

    /**
     * Get latest shipment status for multiple consignments
     *
     * @param array $consignmentNumbers
     * @return \stdClass
     * @throws CourierException
     */
    public function getLastShipmentStatus(array $consignmentNumbers)
    {
        try {
            $options = ['headers' => $this->getHeaders(), 'form_params' => [
            ]];

            $options['json'] = $consignmentNumbers;

            $response = $this->client->post($this->getEndpointUrl("GetLastShipmentStatus"), $options);

        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        $response = json_decode($response->getBody()->getContents(), true);

        return json_decode(json_encode(array_merge($response, [
            'success' => data_get($response, 's') === 'success',
        ])));
    }

    /**
     * Get detailed shipments status for a consignment
     *
     * @param array $consignmentNumbers
     * @return \Psr\Http\Message\ResponseInterface
     * @throws CourierException
     */
    public function getShipmentStatusDetail(string $consignmentNumber)
    {
        try {
            $options = ['headers' => $this->getHeaders(), 'query' => [
                'consignmentNumber' => $consignmentNumber
            ]];

            $response = $this->client->get($this->getEndpointUrl("GetShipmentStatusDetail"), $options);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        $response = json_decode($response->getBody()->getContents(), true);

        return json_decode(json_encode(array_merge($response, [
            'success' => data_get($response, 's') === 'success',
        ])));
    }

    /**
     * @param string $endpoint
     * @param array|null $parameters
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $endpoint, array $parameters = null): array
    {
        try {
            $options = ['headers' => $this->getHeaders()];

            if ($parameters) {
                $options['json'] = $parameters;
            }

            $response = $this->client->get($this->getEndpointUrl($endpoint), $options);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        $response = json_decode($response->getBody()->getContents());

        return $response ?? [];
    }

    /**
     * Get the HTTP headers.
     */
    protected function getHeaders(array $headers = []): array
    {
        return [
            'ApiToken' => $this->apiToken,
            'Subscription-Key' => $this->subscriptionKey,
        ];
    }

    /**
     * @param string $endpoint
     * @return string
     */
    protected function getEndpointUrl(string $endpoint): string
    {
        $url = $this->testMode ? $this->sandboxEndpoint : $this->endpoint;

        return "{$url}/{$endpoint}?accountNo={$this->accountNo}";
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
}
