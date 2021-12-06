<?php

namespace Nextbyte\Courier\Clients\BestExpress;

use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Auth\Access\AuthorizationException;
use Nextbyte\Courier\Enums\ShipmentStatus;
use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Exceptions\MissingRequiredParamException;
use Nextbyte\Courier\ShipmentStatusPush;

class BestExpress
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
    protected $endpoint = 'http://sgp-seaedi-test.800best.com/Malaysia/kdapi/api/process';

    /**
     * The Gdex Sandbox API endpoint.
     *
     * @var string
     */
    protected $sandboxEndpoint = 'http://sgp-seaedi-test.800best.com/Malaysia/kdapi/api/process';

    /**
     * @var string
     */
    protected $partnerId;

    /**
     * @var string
     */
    protected $partnerKey;

    /**
     * @var bool
     */
    protected $testMode;

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
        $this->partnerId = data_get($config, 'partner_id');
        $this->partnerKey = data_get($config, 'partner_key');
        $this->testMode = data_get($config, 'test_mode');

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
            $response = $this->request('KD_CREATE_WAYBILL_ORDER_NOTIFY', $payload);

//            $response = $this->client->post($this->getEndpointUrl(), $options);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        return json_decode(json_encode(array_merge($response, [
            'success' => data_get($response, 'result') == 'true',
        ])));
    }

    /**
     * @param array $data
     * @return \stdClass
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createConsignmentWithPdf(array $payload)
    {
        try {
            $response = $this->request('KD_CREATE_WAYBILL_ORDER_PDF_NOTIFY', $payload);
//            $response = json_decode(file_get_contents(__DIR__ . '/../../../tests/Fixtures/data/best-express/multi_waybills_pdf_response.json'), true);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        return json_decode(json_encode(array_merge($response, [
            'success' => data_get($response, 'result') == 'true',
        ])));
    }

    /**
     * @param array $attributes
     * @param callable|null $statusCallback
     * @return ShipmentStatusPush
     */
    public function createShipmentStatusPush($attributes = [], callable $statusCallback = null)
    {
        if (!$this->validateRequestData($attributes))
            throw new AuthorizationException('You are not authorized to perform this action. Signature token is invalid.');

        $bizData = data_get($attributes, 'bizData');

        $status = data_get($bizData, 'packageStatusCode');

        if ($statusCallback) {
            $status = $statusCallback($status);
        }

        $pushStatus = ShipmentStatusPush::create([
            'orderNumber' => data_get($bizData, 'txLogisticId'),
            'consignmentNumber' => data_get($bizData, 'mailNo'),
            'status' => $status,
            'description' => ShipmentStatus::getDescription($status),
            'date' => Carbon::createFromTimestamp(strtotime(data_get($bizData, 'pushTime'))),
            'currentCity' => data_get($bizData, 'currentCity'),
            'nextCity' => data_get($bizData, 'nextCity'),
            'remarks' => data_get($bizData, 'remarks'),
        ]);

        return $pushStatus;
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

        $response = json_decode($response->getBody(), true);

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
            $response = $this->request('KD_TRACE_QUERY', [
                'mailNos' => [
                    'mailNo' => [
                        $consignmentNumber
                    ]
                ]
            ]);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        return json_decode(json_encode(array_merge($response, [
            'success' => data_get($response, 'result') == 'true',
        ])));
    }

    /**
     * @param string $serviceType
     * @param array|null $parameters
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $serviceType, $bizData, array $parameters = []): array
    {
        try {
            if (!is_string($bizData)) {
                $bizData = json_encode($bizData, JSON_UNESCAPED_UNICODE );
            }

            $options = ['headers' => $this->getHeaders()];

            $options['form_params'] = array_merge([
                'partnerID' => $this->partnerId,
                'serviceType' => $serviceType,
                'bizData' => $bizData,
                'sign' => $this->generateSignature($bizData),
            ], $parameters);

//            if ($parameters) {
//                $options['json'] = $parameters;
//            }

            $response = $this->client->post($this->getEndpointUrl(), $options);
        } catch (ClientException $exception) {
            throw $this->determineException($exception);
        }

        $response = json_decode(str_replace('SYSTEM_ERROR', '', $response->getBody()), true);

        return $response ?? [];
    }

    /**
     * Validate against request params to make sure it is from valid source
     *
     * @param array $attributes
     * @return bool
     */
    protected function validateRequestData($attributes = [])
    {
        $signature = data_get($attributes, 'sign');
        $bizData = data_get($attributes, 'bizData');

        if (empty($signature))
            throw new MissingRequiredParamException('sign', $signature);

        if (empty($bizData))
            throw new MissingRequiredParamException('bizData', $attributes);

        $bizDataStr = json_encode($bizData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

//        dump($bizDataStr . $this->partnerKey);
//        dump($signature);
//        dump(md5($bizDataStr . $this->partnerKey));
//        dd($this->generateSignature($bizDataStr));

        //TODO: for reason the incoming sign is hashed without utf8_encode
        $compareSignature = md5($bizDataStr . $this->partnerKey);

        return $compareSignature === $signature;
//        return $this->generateSignature($bizDataStr) === $signature;
    }

    /**
     * @param string $dataInString
     * @return string
     */
    protected function generateSignature(string $dataInString)
    {
        return md5(utf8_encode($dataInString . $this->partnerKey), false);
    }

    /**
     * Get the HTTP headers.
     */
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
        ];
    }

    /**
     * @param string $endpoint
     * @return string
     */
    protected function getEndpointUrl(): string
    {
        $url = $this->testMode ? $this->sandboxEndpoint : $this->endpoint;

        return "{$url}";
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
