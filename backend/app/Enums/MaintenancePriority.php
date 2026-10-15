<?php

namespace App\Enums;

enum MaintenancePriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Rendah',
            self::NORMAL => 'Normal',
            self::HIGH => 'Tinggi',
            self::URGENT => 'Darurat (Urgent)',
        };
    }

    public function slaHours(): int
    {
        return match ($this) {
            self::URGENT => 6,
            self::HIGH => 24,
            self::NORMAL => 48,
            self::LOW => 72,
        };
    }
}
