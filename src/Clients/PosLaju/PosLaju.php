<?php

namespace Nextbyte\Courier\Clients\PosLaju;

use Illuminate\Support\Arr;

class PosLaju
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
     * Create a new NationwideExpress client instance.
     *
     * @param string $endpoint
     * @return void
     */
    public function __construct($endpoint = null)
    {
        $this->client = new \GuzzleHttp\Client();
        $this->endpoint = $endpoint ?? 'track.pos.com.my';
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
            'POST',
            "https://{$this->endpoint}/postal-services/quick-access/?track-trace",
            array_merge([
                'form_params' => [
                    'trackingNo03' => $trackingNumber,
                    'hvtrackNoHeader03' => '',
                    'hvfromheader03' => 0,
                ]
            ], $payload)
        );

        $shipments = $this->extractShipments($response->getBody()->getContents());

        return Arr::get($shipments, 'data');
    }

    /**
     * @param $html
     * @return array
     */
    protected function extractShipments($html)
    {
        # using regex (regular expression) to parse the HTML webpage.
        # we only want to good stuff
        # regex patern
        $patern = "#<table id='tbDetails'(.*?)</table>#";
        # execute regex
        preg_match_all($patern, $html, $parsed);

        # parse the table by row <tr>
        $trpatern = "#<tr>(.*?)</tr>#";
        preg_match_all($trpatern, implode('', $parsed[0]), $tr);
        unset($tr[0][0]); # remove an array element because we don't need the 1st row (<th></th>)
        $tr[0] = array_values($tr[0]); # rearrange the array index

        # array for keeping the data
        $trackres = array();

        # checking if record found or not, by checking the number of rows available in the result table
        if (count($tr[0]) > 0 && stripos($tr[0][0], 'insert the correct Tracking Number') === false) {
            $trackres['message'] = "Record Found"; # return record found if number of row > 0

            # record found, so proceed
            # iterate through the array, access the data needed and store into new array
            for ($i = 0; $i < count($tr[0]); $i++) {
                # parse the table by column <td>
                $tdpatern = "#<td>(.*?)</td>#";
                preg_match_all($tdpatern, $tr[0][$i], $td);

                # store into variable, strip_tags is for removeing html tags
                $datetime = strip_tags($td[0][0]);
                $process = strip_tags($td[0][1]);
                $event = strip_tags($td[0][2]);

                # store into associative array
                $trackres['data'][$i]['date_time'] = $datetime;
                $trackres['data'][$i]['process'] = $process;
                $trackres['data'][$i]['event'] = $event;
            }
        } else {
            $trackres['message'] = "No Record Found"; # return record not found if number of row < 0
            # since no record found, no need to parse the html furthermore
        }

        return $trackres;
    }
}