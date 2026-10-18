<?php

namespace App\Enums;

enum DepositStatus: string
{
    case PENDING = 'pending';
    case HELD = 'held';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REFUNDED = 'refunded';
    case SETTLED = 'settled';
    case FORFEITED = 'forfeited';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Pembayaran',
            self::HELD => 'Ditahan (Aktif)',
            self::PARTIALLY_REFUNDED => 'Dikembalikan Sebagian',
            self::REFUNDED => 'Dikembalikan Penuh',
            self::SETTLED => 'Selesai (Settled)',
            self::FORFEITED => 'Hangus / Disita',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::HELD => 'success',
            self::PARTIALLY_REFUNDED => 'info',
            self::REFUNDED => 'primary',
            self::SETTLED => 'secondary',
            self::FORFEITED => 'danger',
        };
    }
}
