<?php

namespace Nextbyte\Courier\Clients\NationwideExpress;

use Illuminate\Support\Arr;

class NationwideExpress
{

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The Nationwide Express API endpoint.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * @var array
     */
    protected $locations = [];

    /**
     * @var array
     */
    protected $statusCodes = [];


    /**
     * Create a new NationwideExpress client instance.
     *
     * @param string $endpoint
     * @return void
     */
    public function __construct($endpoint = null)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->endpoint = $endpoint ?? 'cms.nationwide2u.com:88';

        $this->loadLocations();
        $this->loadStatusCodes();
    }

    public function loadLocations()
    {
        $locations = [];

        $row = 1;

        if (($handle = fopen(__DIR__ . "/data/locations.csv", "r")) !== false) {
            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if ($row > 1) {
                    $locations[$data[0]] = [
                        'code' => $data[0],
                        'name' => $data[1],
                        'state_code' => $data[2],
                        'state_name' => $data[3]
                    ];
                }

                $row++;
            }
            fclose($handle);
        }

        $this->locations = $locations;
    }

    public function loadStatusCodes()
    {
        $statusCodes = [];

        $row = 1;

        if (($handle = fopen(__DIR__ . "/data/status_codes.csv", "r")) !== false) {
            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if ($row > 1) {
                    $statusCodes[$data[0]] = [
                        'code' => $data[0],
                        'name' => $data[1],
                    ];
                }

                $row++;
            }
            fclose($handle);
        }

        $this->statusCodes = $statusCodes;
    }

    /**
     * Get shipment history
     *
     * @param string $trackingNumber
     * @param null|array $payload
     * @return array
     */
    public function shipments($trackingNumber, $payload = [])
    {
        $response = $this->client->request(
            'GET',
            "http://{$this->endpoint}/nw_track/{$trackingNumber}",
            $payload
        );

        $shipments = json_decode($response->getBody()->getContents());

        return $shipments;
    }

    /**
     * @return array
     */
    public function getLocations()
    {
        return $this->locations;
    }

    /**
     * @return array
     */
    public function getStatusCodes()
    {
        return $this->statusCodes;
    }

    /**
     * @param $locationCode
     * @return array
     */
    public function getLocation($locationCode)
    {
        return Arr::get($this->locations, $locationCode);
    }

    /**
     * @param $statusCode
     * @return string
     */
    public function getStatus($statusCode)
    {
        return Arr::get(Arr::get($this->statusCodes, $statusCode), 'name');
    }
}