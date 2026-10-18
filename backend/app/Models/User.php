<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'role', 'avatar_url', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SUPER_ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role === Role::OWNER;
    }

    public function isManager(): bool
    {
        return $this->role === Role::MANAGER;
    }

    public function isStaff(): bool
    {
        return $this->role === Role::STAFF;
    }

    public function isCashier(): bool
    {
        return $this->role === Role::RECEPTIONIST;
    }

    public function isTenant(): bool
    {
        return $this->role === Role::TENANT;
    }

    public function properties(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_users')->withPivot('role')->withTimestamps();
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class, 'user_id');
    }

    public function approvedExpenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class, 'approved_by');
    }

    public function expensePayments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExpensePayment::class, 'recorded_by');
    }

    public function depositLedgers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DepositLedger::class, 'tenant_id');
    }

    public function settlements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CheckOutSettlement::class, 'tenant_id');
    }

    public function hasRole(string ...$roles): bool
    {
        $currentRole = $this->role instanceof Role ? $this->role->value : (string) $this->role;
        return in_array($currentRole, $roles, true);
    }

    public function canManageFinances(): bool
    {
        return $this->hasRole('super_admin', 'owner', 'manager', 'receptionist');
    }

    public function tenantProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TenantProfile::class);
    }

    public function announcementReads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function passwordResetLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PasswordResetLog::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }
}

