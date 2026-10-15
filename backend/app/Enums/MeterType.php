<?php

namespace App\Enums;

enum MeterType: string
{
    case INDIVIDUAL = 'individual';
    case SHARED = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual (Kamar)',
            self::SHARED => 'Bersama (Komunal/Gedung)',
        };
    }
}
