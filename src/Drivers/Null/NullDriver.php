<?php

namespace Nextbyte\Courier\Drivers\Null;

use Nextbyte\Courier\Drivers\Driver;
use Nextbyte\Courier\Drivers\NationwideExpress\NullTrackingResponse;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

class NullDriver extends Driver
{
    /**
     * Redirect to external courier tracking page with tracking number
     *
     * @return RedirectResponseInterface
     */
    public function redirectTrack()
    {
        return new NullTrackingResponse([]);
    }
}