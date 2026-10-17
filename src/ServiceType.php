<?php

namespace Nextbyte\Courier;

/**
 * A vehicle or service a courier offers, with the largest load it takes.
 *
 * Limits are normalised to kilograms and centimetres, and are null when the courier gives none.
 */
class ServiceType
{
    /**
     * @param string     $key         The courier's code, sent when quoting or booking, e.g. "CAR"
     * @param string     $description The courier's own description, e.g. "Ideal for groceries, food, flowers"
     * @param float|null $maxWeightKg Heaviest load, e.g. 40.0
     * @param float|null $lengthCm    Largest item length, e.g. 50.0
     * @param float|null $widthCm     Largest item width
     * @param float|null $heightCm    Largest item height
     */
    public function __construct(
        public readonly string $key,
        public readonly string $description = '',
        public readonly ?float $maxWeightKg = null,
        public readonly ?float $lengthCm = null,
        public readonly ?float $widthCm = null,
        public readonly ?float $heightCm = null,
    ) {
    }

    /**
     * Build a service type from a Lalamove City Info `services` entry.
     */
    public static function fromLalamove(array $service): self
    {
        $description = trim((string) ($service['description'] ?? ''));

        return new self(
            (string) $service['key'],
            // Some cities repeat the key as the description, which says nothing.
            $description === (string) $service['key'] ? '' : $description,
            self::toKilograms($service['load'] ?? null),
            self::toCentimetres($service['dimensions']['length'] ?? null),
            self::toCentimetres($service['dimensions']['width'] ?? null),
            self::toCentimetres($service['dimensions']['height'] ?? null),
        );
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            (string) $attributes['key'],
            (string) ($attributes['description'] ?? ''),
            $attributes['maxWeightKg'] ?? null,
            $attributes['lengthCm'] ?? null,
            $attributes['widthCm'] ?? null,
            $attributes['heightCm'] ?? null,
        );
    }

    /**
     * @return array{key: string, description: string, maxWeightKg: float|null, lengthCm: float|null, widthCm: float|null, heightCm: float|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'description' => $this->description,
            'maxWeightKg' => $this->maxWeightKg,
            'lengthCm' => $this->lengthCm,
            'widthCm' => $this->widthCm,
            'heightCm' => $this->heightCm,
        ];
    }

    /**
     * @param array{value?: string|int|float, unit?: string}|null $measure
     */
    private static function toKilograms(?array $measure): ?float
    {
        if (!is_numeric($measure['value'] ?? null)) {
            return null;
        }

        $value = (float) $measure['value'];

        return match (strtolower((string) ($measure['unit'] ?? 'kg'))) {
            'g' => $value / 1000,
            'lb', 'lbs' => round($value * 0.45359237, 2),
            't', 'ton', 'tonne' => $value * 1000,
            default => $value,
        };
    }

    /**
     * @param array{value?: string|int|float, unit?: string}|null $measure
     */
    private static function toCentimetres(?array $measure): ?float
    {
        if (!is_numeric($measure['value'] ?? null)) {
            return null;
        }

        $value = (float) $measure['value'];

        return match (strtolower((string) ($measure['unit'] ?? 'm'))) {
            'cm' => $value,
            'mm' => $value / 10,
            'ft' => round($value * 30.48, 1),
            'in' => round($value * 2.54, 1),
            default => round($value * 100, 1),
        };
    }
}
