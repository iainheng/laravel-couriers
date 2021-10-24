<?php

namespace Nextbyte\Courier\Enums;

use BenSampo\Enum\Enum;
use Illuminate\Support\Str;

final class ShipmentStatus extends Enum
{
    const Pending = 'pending';
    const Pickup = 'pickup';
    const InTransit = 'in-transit';
    const OutForDelivery = 'out-for-delivery';
    const Delivered = 'delivered';
    const Returned = 'returned';
    const Claim = 'claim';
    const Undelivered = 'undelivered';
    const Unknown = 'unknown';

    /**
     * Transform the key name into a friendly, formatted version
     *
     * @param string $key
     * @return string
     */
    public static function getDescription($value): string
    {
        $name = ucfirst(str_replace('-', ' ', Str::snake($value)));

        switch ($value) {
            case self::Pending:
                $name = 'Parcel is pending for pickup';
                break;
            case self::Pickup:
                $name = 'Picked up by courier';
                break;
            case self::InTransit:
                $name = 'Parcel is in transit';
                break;
            case self::OutForDelivery:
                $name = 'Parcel is out for delivery';
                break;
            case self::Delivered:
                $name = 'Parcel is delivered';
                break;
            case self::Returned:
                $name = 'Parcel is returned';
                break;
            case self::Undelivered:
                $name = 'Parcel is undelivered';
                break;
            case self::Claim:
                $name = 'Parcel is marked as claim';
                break;
            case self::Unknown:
                $name = 'Unknown shipment error';
                break;
            default:
        }

        return $name;
    }
}
