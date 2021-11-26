<?php

namespace Nextbyte\Courier\Concerns;


use Illuminate\Support\Str;

trait CanQueryConsignmentSlip
{
    /**
     * Create consignment note attributes required by courier driver to create a new consignment note
     *
     * @param null|string $courierName
     * @return array
     */
    public function getQueryConsignmentSlipAttributes($courierName, array $data = [])
    {
        $method = 'create'.Str::studly($courierName).'QueryConsignmentSlipAttributes';

        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException('Method not found. Please implement method ' . $method . '() in class.' );
        }

        return $this->$method($data);
    }
}
