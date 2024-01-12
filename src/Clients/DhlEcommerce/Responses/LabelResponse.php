<?php

namespace Nextbyte\Courier\Clients\DhlEcommerce\Responses;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LabelResponse extends DhlEcommerceResponse
{
    public function __construct($data)
    {
        parent::__construct($data);

        $responseStatus = data_get($this->data, 'bd.labels.0.responseStatus');

        $this->parseResponseStatus($responseStatus);
    }

    /**
     * @return bool
     */
    public function isShipmentIdExists()
    {
        return !$this->isSuccess() && $this->getStatusCode() == '202' && Str::contains($this->getMessageDetailsText(), 'Shipment with same Shipment ID exists');
    }
}
