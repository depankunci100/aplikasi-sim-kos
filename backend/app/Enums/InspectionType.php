<?php

namespace App\Enums;

enum InspectionType: string
{
    case CHECK_IN = 'check_in';
    case PERIODIC = 'periodic';
    case CHECK_OUT = 'check_out';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::CHECK_IN => 'Inspeksi Masuk (Check-In)',
            self::PERIODIC => 'Inspeksi Berkala',
            self::CHECK_OUT => 'Inspeksi Keluar (Check-Out)',
            self::MAINTENANCE => 'Inspeksi Pemeliharaan',
        };
    }
}
