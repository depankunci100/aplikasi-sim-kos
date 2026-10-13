<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case OVERSTAY = 'overstay';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Pembayaran / Persetujuan',
            self::CONFIRMED => 'Terkonfirmasi (Siap Check-In)',
            self::ACTIVE => 'Aktif (Sedang Ditempati)',
            self::COMPLETED => 'Selesai (Sudah Check-Out)',
            self::CANCELLED => 'Dibatalkan',
            self::OVERSTAY => 'Overstay (Terlambat Check-Out)',
        };
    }
}
