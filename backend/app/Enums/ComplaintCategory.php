<?php

namespace App\Enums;

enum ComplaintCategory: string
{
    case ROOM = 'room';
    case FACILITY = 'facility';
    case SERVICE = 'service';
    case BILLING = 'billing';
    case ENVIRONMENT = 'environment';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ROOM => 'Kondisi Kamar',
            self::FACILITY => 'Fasilitas Umum',
            self::SERVICE => 'Layanan & Staf',
            self::BILLING => 'Tagihan & Pembayaran',
            self::ENVIRONMENT => 'Lingkungan & Ketertiban',
            self::OTHER => 'Lain-lain',
        };
    }
}
