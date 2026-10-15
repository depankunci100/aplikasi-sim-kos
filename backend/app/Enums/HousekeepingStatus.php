<?php

namespace App\Enums;

enum HousekeepingStatus: string
{
    case SCHEDULED = 'scheduled';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case PENDING_VERIFICATION = 'pending_verification';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Terjadwal',
            self::ASSIGNED => 'Ditugaskan',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::PENDING_VERIFICATION => 'Menunggu Verifikasi',
            self::COMPLETED => 'Selesai & Bersih',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
