<?php

namespace Nextbyte\Courier;

/**
 * A courier's price for a delivery: a quoted price before booking, or the price charged after.
 */
class Price
{
    /**
     * @param string $total     Decimal string as returned by the courier, e.g. "24.6"
     * @param string $currency  ISO 4217 code, e.g. "MYR"
     * @param array  $breakdown Courier-specific components, e.g. ['base' => '4.0', 'extraMileage' => '20.6']
     */
    public function __construct(
        public readonly string $total,
        public readonly string $currency,
        public readonly array $breakdown = [],
    ) {
    }

    /**
     * Build a price from a Lalamove `priceBreakdown` object; null when it has no total.
     */
    public static function fromLalamove(?array $priceBreakdown): ?self
    {
        if (empty($priceBreakdown) || !isset($priceBreakdown['total'])) {
            return null;
        }

        return new self(
            (string) $priceBreakdown['total'],
            (string) ($priceBreakdown['currency'] ?? ''),
            array_diff_key($priceBreakdown, array_flip(['total', 'currency'])),
        );
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            (string) $attributes['total'],
            (string) $attributes['currency'],
            $attributes['breakdown'] ?? [],
        );
    }

    /**
     * @return array{total: string, currency: string, breakdown: array}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'currency' => $this->currency,
            'breakdown' => $this->breakdown,
        ];
    }
}
