<?php
namespace Nextbyte\Courier\Drivers\Gdex;

use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class GdexTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    protected $endpoint = 'https://esvr5.gdexpress.com/WebsiteEtracking/Home/Etracking?id=GDEX';

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectUrl()
    {
        $qs = http_build_query($this->getRedirectData());

        return $this->endpoint . '&' . $qs;
    }

    public function getRedirectData()
    {
        return $this->data;
    }
}
