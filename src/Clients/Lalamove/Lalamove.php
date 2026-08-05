<?php

namespace Nextbyte\Courier\Clients\Lalamove;

use GuzzleHttp\Exception\ClientException;
use Nextbyte\Courier\Exceptions\CourierException;

class Lalamove
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $apiSecret;

    /**
     * @var string
     */
    protected $market = 'MY';

    /**
     * @var bool
     */
    protected $testMode = false;

    /**
     * @var string
     */
    protected $sandboxBaseUrl = 'https://rest.sandbox.lalamove.com';

    /**
     * @var string
     */
    protected $productionBaseUrl = 'https://rest.lalamove.com';

    /**
     * @param array $config
     * @param \GuzzleHttp\Client|null $client
     */
    public function __construct(array $config, \GuzzleHttp\Client $client = null)
    {
        $this->config($config);
        $this->client = $client ?? new \GuzzleHttp\Client();
    }

    /**
     * @param array $config
     * @return $this
     */
    public function config(array $config = []): self
    {
        $this->apiKey = data_get($config, 'api_key');
        $this->apiSecret = data_get($config, 'api_secret');
        $this->market = data_get($config, 'market', 'MY');
        $this->testMode = data_get($config, 'test_mode', false);

        return $this;
    }

    /**
     * Get a price quotation and stop IDs for an order.
     *
     * @param array $attributes
     * @return array
     */
    public function createQuotation(array $attributes): array
    {
        return $this->request('POST', '/v3/quotations', $attributes);
    }

    /**
     * Place a delivery order using a quotation.
     *
     * @param array $attributes
     * @return array
     */
    public function createOrder(array $attributes): array
    {
        return $this->request('POST', '/v3/orders', $attributes);
    }

    /**
     * Retrieve an order's current status and details.
     *
     * @param string $orderId
     * @return array
     */
    public function getOrder(string $orderId): array
    {
        return $this->request('GET', "/v3/orders/{$orderId}");
    }

    /**
     * Cancel an order. Returns true on success, false if cancellation is forbidden.
     *
     * @param string $orderId
     * @return bool
     */
    public function cancelOrder(string $orderId): bool
    {
        try {
            $this->request('DELETE', "/v3/orders/{$orderId}");
            return true;
        } catch (CourierException $e) {
            return false;
        }
    }

    /**
     * Get the assigned driver's location and details.
     *
     * @param string $orderId
     * @param string $driverId
     * @return array
     */
    public function getDriver(string $orderId, string $driverId): array
    {
        return $this->request('GET', "/v3/orders/{$orderId}/drivers/{$driverId}");
    }

    /**
     * Register or update the webhook URL for order status push notifications.
     *
     * @param string $url
     * @return array
     */
    public function setWebhook(string $url): array
    {
        return $this->request('PATCH', '/v3/webhook', ['url' => $url]);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $data
     * @return array
     * @throws CourierException|\GuzzleHttp\Exception\GuzzleException
     */
    protected function request(string $method, string $path, array $data = []): array
    {
        $body = !empty($data) ? json_encode(['data' => $data]) : '';

        try {
            $options = ['headers' => $this->getHeaders($method, $path, $body)];

            if ($body) {
                $options['body'] = $body;
            }

            $response = $this->client->request($method, $this->getBaseUrl() . $path, $options);

            if ($response->getStatusCode() === 204) {
                return [];
            }

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }
    }

    /**
     * Build HMAC-authenticated headers for a request.
     *
     * @param string $method
     * @param string $path
     * @param string $body
     * @return array
     */
    protected function getHeaders(string $method, string $path, string $body = ''): array
    {
        $timestamp = (string)(time() * 1000);
        $rawSignature = "{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}";
        $signature = hash_hmac('sha256', $rawSignature, $this->apiSecret);
        $token = "{$this->apiKey}:{$timestamp}:{$signature}";

        return [
            'Authorization' => "hmac {$token}",
            'Market' => $this->market,
            'Request-ID' => $this->generateRequestId(),
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];
    }

    /**
     * @return string
     */
    protected function generateRequestId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * @return string
     */
    protected function getBaseUrl(): string
    {
        return $this->testMode ? $this->sandboxBaseUrl : $this->productionBaseUrl;
    }

    /**
     * @param ClientException $exception
     * @return \Exception
     */
    protected function determineException(ClientException $exception): \Exception
    {
        $statusCode = (int) $exception->getResponse()->getStatusCode();
        $body = json_decode($exception->getResponse()->getBody()->getContents(), true);
        $message = data_get($body, 'message', $exception->getMessage());

        if ($statusCode >= 400 && $statusCode < 500) {
            return new CourierException($message, $statusCode);
        }

        return $exception;
    }
}
