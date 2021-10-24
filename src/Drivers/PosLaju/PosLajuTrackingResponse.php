<?php
namespace Nextbyte\Courier\Drivers\PosLaju;

use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class PosLajuTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    protected $endpoint = 'https://track.pos.com.my/postal-services/quick-access/?track-trace';

    public function getRedirectMethod()
    {
        return 'POST';
    }

    public function getRedirectUrl()
    {
        return $this->endpoint;
    }

    public function getRedirectData()
    {
        return $this->data;
    }
}