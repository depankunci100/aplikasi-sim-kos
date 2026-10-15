<?php

namespace App\Enums;

enum MaintenanceCategory: string
{
    case ELECTRICITY = 'electricity';
    case WATER = 'water';
    case PLUMBING = 'plumbing';
    case AC = 'ac';
    case FURNITURE = 'furniture';
    case DOOR_LOCK = 'door_lock';
    case INTERNET = 'internet';
    case CLEANLINESS = 'cleanliness';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ELECTRICITY => 'Kelistrikan',
            self::WATER => 'Saluran Air',
            self::PLUMBING => 'Plumbing / Pipa',
            self::AC => 'Pendingin Ruangan (AC)',
            self::FURNITURE => 'Perabot / Furnitur',
            self::DOOR_LOCK => 'Pintu / Kunci / Jendela',
            self::INTERNET => 'Jaringan Internet / WiFi',
            self::CLEANLINESS => 'Kebersihan Ekstra',
            self::OTHER => 'Lain-lain',
        };
    }
}
