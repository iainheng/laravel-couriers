<?php
namespace Nextbyte\Courier\Drivers\NationwideExpress;

use Nextbyte\Courier\Messages\AbstractTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class NullTrackingResponse extends AbstractTrackingResponse implements RedirectResponseInterface
{
    public function isRedirect()
    {
        return false;
    }
}