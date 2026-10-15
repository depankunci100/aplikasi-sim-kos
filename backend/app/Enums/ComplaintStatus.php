<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case SUBMITTED = 'submitted';
    case ACKNOWLEDGED = 'acknowledged';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case REOPENED = 'reopened';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Diajukan',
            self::ACKNOWLEDGED => 'Diterima / Triase',
            self::IN_PROGRESS => 'Sedang Diproses',
            self::RESOLVED => 'Terselesaikan',
            self::CLOSED => 'Ditutup',
            self::REOPENED => 'Dibuka Kembali',
            self::REJECTED => 'Ditolak',
        };
    }
}
