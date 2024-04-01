<?php

namespace Nextbyte\Courier\Enums;

use BenSampo\Enum\Enum;
use Illuminate\Support\Str;

final class ShipmentStatus extends Enum
{
    const Pending = 'pending';
    const Accepted = 'accepted';
    const AcceptFailed = 'accept-failed';
    const Pickup = 'pickup';
    const PickupFailed = 'pickup-failed';
    const ArrivedAtFacility = 'arrived-at-facility';
    const ProcessingAtFacility = 'processing-at-facility';
    const InTransit = 'in-transit';
    const OutForDelivery = 'out-for-delivery';
    const Delivered = 'delivered';
    const Claim = 'claim';
    const Undelivered = 'undelivered';
    const DeliveryRefused = 'delivery-refused';
    const DeliveryAttempted = 'delivery-attempted';
    const ReturnStart = 'return-start';
    const Returned = 'returned';
    const OnHold = 'on-hold';
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
            case self::Accepted:
                $name = 'Package has been accepted successfully';
                break;
            case self::AcceptFailed:
                $name = 'Package is failed to be accepted';
                break;
            case self::Pickup:
                $name = 'Package has been picked up successfully';
                break;
            case self::PickupFailed:
                $name = 'Package is failed to be picked up';
                break;
            case self::ArrivedAtFacility:
                $name = 'Package is arrived at facility';
                break;
            case self::ProcessingAtFacility:
                $name = 'Package is processing at facility';
                break;
            case self::InTransit:
                $name = 'Parcel is in transit';
                break;
            case self::OutForDelivery:
                $name = 'Parcel is out for delivery';
                break;
            case self::Delivered:
                $name = 'Parcel has been delivered';
                break;
            case self::DeliveryRefused:
                $name = 'Delivery was refused';
                break;
            case self::DeliveryAttempted:
                $name = 'Delivery was attempted';
                break;
            case self::ReturnStart:
                $name = 'Package starts to return';
                break;
            case self::Returned:
                $name = 'Package has been returned';
                break;
            case self::OnHold:
                $name = 'Package is held in the station';
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
