<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case RECEPTIONIST = 'receptionist';
    case STAFF = 'staff';
    case TENANT = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::OWNER => 'Pemilik Properti',
            self::MANAGER => 'Pengelola Cabang',
            self::RECEPTIONIST => 'Resepsionis',
            self::STAFF => 'Staf Maintenance & Housekeeping',
            self::TENANT => 'Penyewa / Tamu',
        };
    }

    public function isStaffOrAdmin(): bool
    {
        return in_array($this, [
            self::SUPER_ADMIN,
            self::OWNER,
            self::MANAGER,
            self::RECEPTIONIST,
            self::STAFF,
        ]);
    }
}
