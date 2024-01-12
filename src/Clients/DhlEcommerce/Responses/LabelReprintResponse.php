<?php

namespace Nextbyte\Courier\Clients\DhlEcommerce\Responses;

use Illuminate\Support\Arr;

class LabelReprintResponse extends DhlEcommerceResponse
{
    public function __construct($data)
    {
        parent::__construct($data);

        $responseStatus = data_get($this->data, 'bd.responseStatus');

        $this->parseResponseStatus($responseStatus);
    }
}
