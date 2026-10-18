<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'phone',
        'rules',
        'facilities',
        'is_active',
        'dp_required',
        'dp_type',
        'dp_amount',
        'dp_percentage',
        'payment_deadline_hours',
        'deposit_amount',
    ];

    protected $casts = [
        'facilities' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
        'dp_required' => 'boolean',
        'dp_amount' => 'decimal:2',
        'dp_percentage' => 'decimal:2',
        'payment_deadline_hours' => 'integer',
        'deposit_amount' => 'decimal:2',
    ];

    public function calculateDownPayment(float $rateAmount, float $depositAmount = 0.0): float
    {
        if (!$this->dp_required) {
            return 0.0;
        }

        $total = $rateAmount + $depositAmount;
        if ($this->dp_type === 'percentage') {
            $percentage = (float) ($this->dp_percentage ?? 0);
            return round(($total * $percentage) / 100, 2);
        }

        $fixed = (float) ($this->dp_amount ?? 0);
        return min($fixed, $total);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_users')->withPivot('role')->withTimestamps();
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function depositLedgers(): HasMany
    {
        return $this->hasMany(DepositLedger::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CheckOutSettlement::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function utilityMeters(): HasMany
    {
        return $this->hasMany(UtilityMeter::class);
    }

    public function utilityTariffs(): HasMany
    {
        return $this->hasMany(UtilityTariff::class);
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

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function scopeActive($query)
    {
        return $query->where('properties.is_active', true);
    }
}

