<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PARTIALLY_PAID => 'Dibayar Sebagian',
            self::PAID => 'Lunas',
            self::OVERDUE => 'Jatuh Tempo (Menunggak)',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::UNPAID => 'warning',
            self::PARTIALLY_PAID => 'info',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'secondary',
        };
    }
}
