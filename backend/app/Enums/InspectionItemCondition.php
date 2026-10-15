<?php

namespace App\Enums;

enum InspectionItemCondition: string
{
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';
    case DAMAGED = 'damaged';
    case MISSING = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::GOOD => 'Baik / Normal',
            self::FAIR => 'Cukup / Aus Wajar',
            self::POOR => 'Kurang Baik',
            self::DAMAGED => 'Rusak',
            self::MISSING => 'Hilang',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::GOOD => 'bg-success',
            self::FAIR => 'bg-info text-dark',
            self::POOR => 'bg-warning text-dark',
            self::DAMAGED => 'bg-danger',
            self::MISSING => 'bg-dark',
        };
    }
}
