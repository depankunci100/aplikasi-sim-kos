<?php

namespace App\Enums;

enum JournalEntryStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::POSTED => 'Diposting',
            self::REVERSED => 'Dibalik (Reversed)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::POSTED => 'success',
            self::REVERSED => 'secondary',
        };
    }
}
