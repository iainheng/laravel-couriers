<?php

namespace Nextbyte\Courier\Contracts;

use Nextbyte\Courier\ConsignmentFile;
use Nextbyte\Courier\Messages\RedirectResponseInterface;

interface Consignmentable
{
    /**
     * Create consignment note attributes required by courier driver to create a new consignment note
     *
     * @param null|string $courierName
     * @return array
     */
    public function toConsignmentableArray($courierName, array $data = []);

    /**
     * Get all consignment note numbers
     *
     * @return array
     */
    public function getConsignmentNumbers();

    /**+
     * Get all attributes requires by courier API to get consignment slip/waybill
     *
     * @param string $courierName
     * @param array $data
     * @return string|array
     */
    public function getQueryConsignmentSlipAttributes($courierName, array $data = []);
}
