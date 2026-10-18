<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'room_type_id',
        'room_number',
        'floor',
        'building',
        'size_m2',
        'capacity',
        'description',
        'facilities',
        'price_daily',
        'price_monthly',
        'price_yearly',
        'deposit_amount',
        'down_payment_amount',
        'photos',
        'internal_notes',
        'status',
        'is_active',
    ];

    protected $casts = [
        'status' => RoomStatus::class,
        'is_active' => 'boolean',
        'floor' => 'integer',
        'size_m2' => 'float',
        'capacity' => 'integer',
        'price_daily' => 'float',
        'price_monthly' => 'float',
        'price_yearly' => 'float',
        'deposit_amount' => 'float',
        'down_payment_amount' => 'float',
        'facilities' => 'array',
        'photos' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(RoomAsset::class);
    }

    public function fixedAssets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function utilityMeters(): HasMany
    {
        return $this->hasMany(UtilityMeter::class);
    }

    public function utilityReadings(): HasMany
    {
        return $this->hasMany(UtilityReading::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RoomChangeHistory::class)->latest();
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === RoomStatus::AVAILABLE;
    }

    public function hasActiveBooking(): bool
    {
        return $this->bookings()
            ->whereIn('status', [
                \App\Enums\BookingStatus::CONFIRMED->value,
                \App\Enums\BookingStatus::ACTIVE->value,
                \App\Enums\BookingStatus::OVERSTAY->value,
            ])
            ->exists();
    }

    public function getEffectiveMonthlyPriceAttribute(): float
    {
        return (float) ($this->price_monthly ?? $this->roomType?->base_price_monthly ?? 0);
    }

    public function getEffectiveDailyPriceAttribute(): float
    {
        return (float) ($this->price_daily ?? $this->roomType?->base_price_daily ?? 0);
    }

    public function getEffectiveYearlyPriceAttribute(): float
    {
        return (float) ($this->price_yearly ?? $this->roomType?->base_price_yearly ?? 0);
    }

    public function getEffectiveDepositAttribute(): float
    {
        return (float) ($this->deposit_amount ?? $this->property?->default_deposit_amount ?? 0);
    }

    public function getEffectiveDownPaymentAttribute(): float
    {
        return (float) ($this->down_payment_amount ?? $this->property?->default_down_payment_amount ?? 0);
    }

    public function getEffectiveFacilitiesAttribute(): array
    {
        return !empty($this->facilities) ? (array) $this->facilities : (array) ($this->roomType?->facilities ?? []);
    }

    public function getEffectivePhotosAttribute(): array
    {
        return !empty($this->photos) ? (array) $this->photos : (array) ($this->roomType?->photos ?? []);
    }

    public function getEffectiveCapacityAttribute(): int
    {
        return (int) ($this->capacity ?? $this->roomType?->max_occupants ?? 1);
    }

    public function getEffectiveSizeAttribute(): float
    {
        return (float) ($this->size_m2 ?? $this->roomType?->size_m2 ?? 0);
    }
}
