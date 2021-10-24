<?php
namespace Nextbyte\Courier\Drivers\Best;

use Illuminate\Support\Arr;
use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class BestTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    protected $endpoint = 'https://www.tracking.my/best';

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
