<?php

namespace App\Enums;

enum RentalType: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Harian (Guesthouse)',
            self::WEEKLY => 'Mingguan',
            self::MONTHLY => 'Bulanan (Kos)',
            self::QUARTERLY => '3 Bulanan',
            self::YEARLY => 'Tahunan',
        };
    }

    public function isShortTerm(): bool
    {
        return in_array($this, [self::DAILY, self::WEEKLY]);
    }
}
