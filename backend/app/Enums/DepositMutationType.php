<?php

namespace App\Enums;

enum DepositMutationType: string
{
    case DEPOSIT_RECEIVED = 'deposit_received';
    case DAMAGE_DEDUCTION = 'damage_deduction';
    case UNPAID_BILL_DEDUCTION = 'unpaid_bill_deduction';
    case REFUND_PAYOUT = 'refund_payout';
    case ADJUSTMENT_CREDIT = 'adjustment_credit';
    case ADJUSTMENT_DEBIT = 'adjustment_debit';

    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT_RECEIVED => 'Penerimaan Deposit',
            self::DAMAGE_DEDUCTION => 'Potongan Kerusakan (Inspeksi)',
            self::UNPAID_BILL_DEDUCTION => 'Potongan Tunggakan Tagihan',
            self::REFUND_PAYOUT => 'Pengembalian Deposit (Refund)',
            self::ADJUSTMENT_CREDIT => 'Koreksi Kredit (Penambahan)',
            self::ADJUSTMENT_DEBIT => 'Koreksi Debit (Pengurangan)',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::DEPOSIT_RECEIVED, self::ADJUSTMENT_CREDIT => 'in',
            self::DAMAGE_DEDUCTION, self::UNPAID_BILL_DEDUCTION, self::REFUND_PAYOUT, self::ADJUSTMENT_DEBIT => 'out',
        };
    }
}
