<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case DRAFT = 'draft';
    case PENDING_TENANT_APPROVAL = 'pending_tenant_approval';
    case DISPUTED = 'disputed';
    case APPROVED = 'approved';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Konsep (Draft)',
            self::PENDING_TENANT_APPROVAL => 'Menunggu Review Penyewa',
            self::DISPUTED => 'Disengketakan (Dispute)',
            self::APPROVED => 'Disetujui (Siap Refund)',
            self::SETTLED => 'Selesai (Refund Dibayar)',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING_TENANT_APPROVAL => 'warning',
            self::DISPUTED => 'danger',
            self::APPROVED => 'info',
            self::SETTLED => 'success',
            self::CANCELLED => 'dark',
        };
    }
}
