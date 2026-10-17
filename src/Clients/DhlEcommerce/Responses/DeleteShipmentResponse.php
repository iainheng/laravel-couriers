<?php

namespace Nextbyte\Courier\Clients\DhlEcommerce\Responses;

/**
 * Response of DHL eCommerce's Delete/Cancel Shipment endpoint (`deleteShipmentResp`).
 *
 * DHL answers per shipment, so a request can succeed at the service level while a
 * shipment in it is refused (e.g. already picked up); see getFailures().
 */
class DeleteShipmentResponse extends DhlEcommerceResponse
{
    /**
     * @var array<string, string> shipmentID => why DHL refused it
     */
    protected $failures = [];

    public function __construct($data)
    {
        parent::__construct($data);

        $this->parseResponseStatus(data_get($this->data, 'bd.responseStatus'));

        foreach (data_get($this->data, 'bd.shipmentItems', []) as $item) {
            $status = data_get($item, 'responseStatus', []);

            if (data_get($status, 'message') !== 'SUCCESS') {
                $details = collect(data_get($status, 'messageDetails', []))->pluck('messageDetail')->filter()->implode(' ');
                $this->failures[(string) data_get($item, 'shipmentID')] = trim($details ?: (string) data_get($status, 'message'));
            }
        }
    }

    public function isSuccess(): bool
    {
        return parent::isSuccess() && empty($this->failures);
    }

    /**
     * @return array<string, string> shipmentID => why DHL refused it
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * One readable reason for a refused cancellation.
     */
    public function getFailureMessage(): string
    {
        if (! empty($this->failures)) {
            return collect($this->failures)->map(fn ($reason, $id) => "{$id}: {$reason}")->implode('; ');
        }

        return trim($this->getMessageDetailsText() ?: (string) $this->getMessage());
    }
}
