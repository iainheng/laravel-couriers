<?php
namespace Nextbyte\Courier\Drivers\NationwideExpress;

use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class NationwideExpressTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    protected $endpoint = 'http://www.nationwide2u.com/v2/cgi-bin/trackbe.cfm';

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