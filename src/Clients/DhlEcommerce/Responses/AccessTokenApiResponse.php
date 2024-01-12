<?php

namespace Nextbyte\Courier\Clients\DhlEcommerce\Responses;

class AccessTokenApiResponse extends DhlEcommerceResponse
{
    public function __construct($data)
    {
        parent::__construct($data);

        $responseStatus = data_get($this->data, 'responseStatus');

        $this->parseResponseStatus($responseStatus);
    }

    public function isSuccess() : bool
    {
        return $this->statusCode === '100000';
    }
}
