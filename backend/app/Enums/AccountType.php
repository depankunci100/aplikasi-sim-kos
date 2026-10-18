<?php

namespace App\Enums;

enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::ASSET => 'Aset',
            self::LIABILITY => 'Kewajiban',
            self::EQUITY => 'Ekuitas',
            self::REVENUE => 'Pendapatan',
            self::EXPENSE => 'Beban',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ASSET => 'primary',
            self::LIABILITY => 'warning',
            self::EQUITY => 'info',
            self::REVENUE => 'success',
            self::EXPENSE => 'danger',
        };
    }

    public function defaultNormalBalance(): AccountNormalBalance
    {
        return match ($this) {
            self::ASSET, self::EXPENSE => AccountNormalBalance::DEBIT,
            self::LIABILITY, self::EQUITY, self::REVENUE => AccountNormalBalance::CREDIT,
        };
    }
}
