<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case REPORTED = 'reported';
    case TRIAGED = 'triaged';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case PENDING_VERIFICATION = 'pending_verification';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::REPORTED => 'Dilaporkan',
            self::TRIAGED => 'Ditinjau / Triase',
            self::ASSIGNED => 'Ditugaskan',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::PENDING_VERIFICATION => 'Menunggu Verifikasi',
            self::COMPLETED => 'Selesai & Terverifikasi',
            self::CANCELLED => 'Dibatalkan',
            self::REJECTED => 'Ditolak',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::REJECTED]);
    }
}
