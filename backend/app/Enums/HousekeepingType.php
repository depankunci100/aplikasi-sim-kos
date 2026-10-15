<?php

namespace App\Enums;

enum HousekeepingType: string
{
    case ROOM_CLEANING = 'room_cleaning';
    case TURNOVER_CLEANING = 'turnover_cleaning';
    case DEEP_CLEANING = 'deep_cleaning';
    case COMMON_AREA_CLEANING = 'common_area_cleaning';
    case ON_DEMAND = 'on_demand';

    public function label(): string
    {
        return match ($this) {
            self::ROOM_CLEANING => 'Pembersihan Kamar Rutin',
            self::TURNOVER_CLEANING => 'Pembersihan Check-in / Turnover',
            self::DEEP_CLEANING => 'Deep Cleaning Menyeluruh',
            self::COMMON_AREA_CLEANING => 'Pembersihan Area Bersama',
            self::ON_DEMAND => 'Pembersihan Permintaan Khusus',
        };
    }
}
