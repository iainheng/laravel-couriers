<?php

namespace Nextbyte\Courier\Drivers\Lalamove;

use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class LalamoveTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    public function getRedirectUrl(): string
    {
        $orderId = data_get($this->data, 'id', '');

        return "https://www.lalamove.com/en-my/order-tracking/{$orderId}";
    }
}
