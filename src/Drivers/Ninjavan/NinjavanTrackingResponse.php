<?php
namespace Nextbyte\Courier\Drivers\Ninjavan;

use Illuminate\Support\Arr;
use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class NinjavanTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    protected $endpoint = 'https://www.ninjavan.co/en-my/tracking?id=';

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectUrl()
    {
        return rtrim($this->endpoint, '/') . Arr::first($this->getRedirectData());
    }

    public function getRedirectData()
    {
        return $this->data;
    }
}
