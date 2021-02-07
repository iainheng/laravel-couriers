<?php
namespace Nextbyte\Courier\Drivers\Dhl;

use Illuminate\Support\Arr;
use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class DhlTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    protected $endpoint = 'https://www.tracking.my/dhl-ecommerce';

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectUrl()
    {
        return rtrim($this->endpoint, '/') . '/' . Arr::first($this->getRedirectData());
    }

    public function getRedirectData()
    {
        return $this->data;
    }
}
