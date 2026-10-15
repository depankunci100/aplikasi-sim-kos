<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case APPROVED_BY_TENANT = 'approved_by_tenant';
    case OBJECTED_BY_TENANT = 'objected_by_tenant';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Terjadwal',
            self::IN_PROGRESS => 'Sedang Berlangsung',
            self::COMPLETED => 'Selesai (Menunggu Respon Penyewa)',
            self::APPROVED_BY_TENANT => 'Disetujui Penyewa',
            self::OBJECTED_BY_TENANT => 'Keberatan Diajukan',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SCHEDULED => 'bg-secondary',
            self::IN_PROGRESS => 'bg-primary',
            self::COMPLETED => 'bg-warning text-dark',
            self::APPROVED_BY_TENANT => 'bg-success',
            self::OBJECTED_BY_TENANT => 'bg-danger',
            self::CANCELLED => 'bg-dark',
        };
    }
}
