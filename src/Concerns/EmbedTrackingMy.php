<?php

namespace Nextbyte\Courier\Concerns;


use Illuminate\Support\Str;
use Illuminate\View\View;

trait EmbedTrackingMy
{
    /**
     * @param string $trackingNumber
     * @param array $options
     * @return View
     */
    public function embedTrackingMy($trackingNumber, array $options = [])
    {
        $options = array_merge($options, [
            'tracking_no' => $trackingNumber,
        ]);

        return view('laravelCourier::embed_trackingmy', [
            'options' => array_merge($this->getTrackingMyEmbedOptions(), $options),
        ]);
    }

    /**
     * Get option for tracking.my embed code
     * @return array
     */
    public function getTrackingMyEmbedOptions()
    {
        return [
            'selector' => '#embedTrack',
            'courier' => $this->getTrackingMyCourierName(),
        ];
    }

    /**
     * @return string
     */
    protected function getTrackingMyCourierName()
    {
        $className = (new \ReflectionClass($this))->getShortName();

        $driverName = Str::before($className, 'Driver');

        return strtolower($driverName);
    }
}
