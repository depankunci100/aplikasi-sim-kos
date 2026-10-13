<?php

namespace App\Enums;

enum RoomStatus: string
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';
    case MAINTENANCE = 'maintenance';
    case DIRTY = 'dirty';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::OCCUPIED => 'Terisi',
            self::MAINTENANCE => 'Perbaikan (Maintenance)',
            self::DIRTY => 'Kotor (Perlu Housekeeping)',
        };
    }
}
