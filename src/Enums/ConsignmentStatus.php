<?php

namespace Nextbyte\Courier\Enums;

use BenSampo\Enum\Enum;
use Illuminate\Support\Str;

final class ConsignmentStatus extends Enum
{
    const Delivered = 'delivered';
    const Returned = 'returned';
    const Delivering = 'delivering';
    const Failed = 'failed';
    const Cancelled = 'cancelled';

//    /**
//     * Transform the key name into a friendly, formatted version
//     *
//     * @param string $key
//     * @return string
//     */
//    public static function getDescription($value): string
//    {
//        $name = ucfirst(str_replace('_', ' ', Str::snake($value)));
//
//        switch ($value) {
//            case self::Parts:
//                $name = 'Spare Parts';
//                break;
//            default:
//        }
//
//        return $name;
//    }
}
