<?php

namespace Nextbyte\Courier\Contracts;

use Nextbyte\Courier\Exceptions\CourierException;
use Nextbyte\Courier\Quotation;

/**
 * A courier that can price a delivery before booking it.
 *
 * Only drivers whose courier has a quotation API implement this, so check
 * `$driver instanceof Quotable` before calling quote().
 */
interface Quotable
{
    /**
     * Get a price for a consignment without booking it.
     *
     * Takes the same attributes as createConsignment(). Pass the result back as the
     * `quotation` attribute to createConsignment() / createConsignmentWithSlip() to
     * book at this price before it expires.
     *
     * @param array $attributes
     * @return Quotation
     * @throws CourierException when the courier cannot price the delivery
     */
    public function quote(array $attributes): Quotation;
}
