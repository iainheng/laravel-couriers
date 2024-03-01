<?php

namespace Nextbyte\Courier\Clients\DhlEcommerce\Responses;

use Illuminate\Support\Arr;

class DhlEcommerceResponse
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var
     */
    protected $success;

    /**
     * @var string
     */
    protected $message = '';

    /**
     * @var array
     */
    protected $messageDetails = [];

    protected $statusCode = '';

    public function __construct($data)
    {
        $this->data = Arr::first($data);

//        $messageDetails = data_get($this->data, 'bd.responseStatus.messageDetails');
//
//        if (!is_array($messageDetails))
//            $messageDetails = [['messageDetail' => $messageDetails]];
//
//        $messageDetails = array_map(function ($row) {
//            return data_get($row, 'messageDetail');
//        }, $messageDetails);
//
//        $this->statusCode = data_get($this->data, 'bd.responseStatus.code');
//        $this->message = data_get($this->data, 'bd.responseStatus.message');
//        $this->messageDetails = $messageDetails;
    }

    public function isSuccess() : bool
    {
        return $this->message === 'SUCCESS';
    }

    public function getMessage() : ?string
    {
        return $this->message;
    }

    public function getMessageDetails() : ?array
    {
        return $this->messageDetails;
    }

    public function getMessageDetailsText() : string
    {
        return implode(',', $this->messageDetails);
    }

    public function getStatusCode() : ?string
    {
        return $this->statusCode;
    }

    public function getData() : array
    {
        return $this->data;
    }

    public function toArray() : array
    {
        return [
            'success' => $this->isSuccess(),
            'code' => $this->statusCode,
            'message' => $this->getMessageDetailsText(),
            'messageDetails' => $this->messageDetails,
            'data' => $this->data,
        ];
    }

    /**
     * @param array $responseStatus
     * @return void
     */
    protected function parseResponseStatus($responseStatus)
    {
        $this->statusCode = data_get($responseStatus, 'code');
        $this->message = data_get($responseStatus, 'message');

        $messageDetails = data_get($responseStatus, 'messageDetails');

        if (!is_array($messageDetails))
            $messageDetails = [['messageDetail' => $messageDetails]];

        $this->messageDetails = array_map(function ($row) {
            return data_get($row, 'messageDetail');
        }, $messageDetails);
    }

    public static function create(string $jsonString)
    {
        $response = json_decode($jsonString, true);

        return new static($response);
    }
}
