<?php

namespace Nextbyte\Courier\Clients\Lalamove;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
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
            $this->cancelOrderOrFail($orderId);
            return true;
        } catch (CourierException $e) {
            return false;
        }
    }

    /**
     * Cancel an order, throwing Lalamove's reason when it refuses (e.g. ERR_CANCELLATION).
     *
     * @param string $orderId
     * @throws CourierException
     */
    public function cancelOrderOrFail(string $orderId): void
    {
        $this->request('DELETE', "/v3/orders/{$orderId}");
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
     * Get the market's cities with the service types (vehicles) each offers and their limits.
     *
     * @return array
     */
    public function getCities(): array
    {
        return $this->request('GET', '/v3/cities');
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
     * @throws CourierException when the keys are missing, Lalamove returns an error, or cannot be reached
     * @throws \GuzzleHttp\Exception\GuzzleException for other transport failures
     */
    protected function request(string $method, string $path, array $data = []): array
    {
        if (blank($this->apiKey) || blank($this->apiSecret)) {
            throw new CourierException('Lalamove API key and secret are not configured. Set LALAMOVE_API_KEY and LALAMOVE_API_SECRET.');
        }

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
        } catch (BadResponseException $exception) {
            throw $this->determineException($exception);
        } catch (ConnectException $exception) {
            throw new CourierException('Could not reach Lalamove. Check the internet connection and try again.', 0, $exception);
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
     * Turn a Lalamove HTTP error into a CourierException carrying Lalamove's own message.
     *
     * Lalamove reports errors as {"errors": [{"id", "message", "detail"}]}; its gateway
     * uses {"message"}. Anything else falls back to the HTTP reason phrase.
     *
     * @param BadResponseException $exception
     * @return CourierException
     */
    protected function determineException(BadResponseException $exception): \Exception
    {
        $response = $exception->getResponse();
        $statusCode = (int) $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);

        $error = data_get($body, 'errors.0', []);
        $message = data_get($error, 'message') ?: data_get($body, 'message') ?: $response->getReasonPhrase();

        if ($detail = data_get($error, 'detail')) {
            $message .= " ({$detail})";
        }

        if ($id = data_get($error, 'id')) {
            $message = "{$id}: {$message}";
        }

        if ($statusCode >= 500) {
            $message = "Lalamove is having trouble right now (HTTP {$statusCode}): " . rtrim($message, ".") . ". Try again shortly.";
        }

        return new CourierException($message, $statusCode, $exception);
    }
}
