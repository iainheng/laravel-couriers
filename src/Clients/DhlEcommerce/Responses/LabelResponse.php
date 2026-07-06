<?php

namespace Nextbyte\Courier\Clients\DhlEcommerce\Responses;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LabelResponse extends DhlEcommerceResponse
{
    public function __construct($data)
    {
        parent::__construct($data);

        $responseStatus = data_get($this->data, 'bd.responseStatus');

        if (!in_array(data_get($responseStatus, 'code'), ['202'])) {
            // Prefer the shipment item level status when present, but fall back to the
            // top level status so a top level error (e.g. no labels returned) is not lost.
            $responseStatus = data_get($this->data, 'bd.labels.0.responseStatus') ?: $responseStatus;
        }

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
