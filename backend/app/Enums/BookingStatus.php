<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case OVERSTAY = 'overstay';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Pembayaran / Review',
            self::CONFIRMED => 'Terkonfirmasi (Siap Check-In)',
            self::ACTIVE => 'Aktif (Sedang Ditempati)',
            self::COMPLETED => 'Selesai (Sudah Check-Out)',
            self::CANCELLED => 'Dibatalkan',
            self::REJECTED => 'Ditolak',
            self::EXPIRED => 'Kedaluwarsa',
            self::OVERSTAY => 'Overstay (Terlambat Check-Out)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::CONFIRMED => 'info',
            self::ACTIVE => 'success',
            self::COMPLETED => 'secondary',
            self::CANCELLED => 'danger',
            self::REJECTED => 'dark',
            self::EXPIRED => 'secondary',
            self::OVERSTAY => 'danger',
        };
    }
}

