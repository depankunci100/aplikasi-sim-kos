<?php

namespace App\Enums;

enum UtilityType: string
{
    case ELECTRICITY = 'electricity';
    case WATER = 'water';

    public function label(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'Listrik (kWh)',
            self::WATER => 'Air (m³)',
        };
    }

    public function defaultUnit(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'kWh',
            self::WATER => 'm3',
        };
    }
}
