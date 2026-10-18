<?php

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DepositLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'deposit_code',
        'booking_id',
        'property_id',
        'tenant_id',
        'initial_deposit_amount',
        'total_received',
        'total_deductions',
        'total_refunded',
        'current_balance',
        'status',
        'notes',
    ];

    protected $casts = [
        'initial_deposit_amount' => 'decimal:2',
        'total_received' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_refunded' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'status' => DepositStatus::class,
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(DepositMutation::class)->orderBy('created_at', 'desc');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CheckOutSettlement::class);
    }

    public function latestSettlement(): HasOne
    {
        return $this->hasOne(CheckOutSettlement::class)->latestOfMany();
    }

    public function isHeld(): bool
    {
        return $this->status === DepositStatus::HELD;
    }

    public function isPending(): bool
    {
        return $this->status === DepositStatus::PENDING;
    }

    public function isSettled(): bool
    {
        return in_array($this->status, [DepositStatus::SETTLED, DepositStatus::REFUNDED, DepositStatus::FORFEITED]);
    }
}
