<?php

namespace App\Models;

use App\Enums\UtilityReadingStatus;
use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UtilityReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'meter_id',
        'property_id',
        'room_id',
        'booking_id',
        'invoice_id',
        'utility_type',
        'previous_meter',
        'current_meter',
        'usage',
        'rate_per_unit',
        'total_cost',
        'reading_date',
        'period_start',
        'period_end',
        'proof_photo',
        'status',
        'rejection_reason',
        'recorded_by',
        'reviewed_by',
        'reviewed_at',
        'is_billed',
        'notes',
    ];

    protected $casts = [
        'utility_type' => UtilityType::class,
        'status' => UtilityReadingStatus::class,
        'previous_meter' => 'decimal:2',
        'current_meter' => 'decimal:2',
        'usage' => 'decimal:2',
        'rate_per_unit' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'reading_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'reviewed_at' => 'datetime',
        'is_billed' => 'boolean',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(UtilityMeter::class, 'meter_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(UtilityReadingLog::class);
    }

    public function isApproved(): bool
    {
        return $this->status === UtilityReadingStatus::APPROVED;
    }

    public function isPendingReview(): bool
    {
        return $this->status === UtilityReadingStatus::PENDING_REVIEW;
    }

    public function isDraft(): bool
    {
        return $this->status === UtilityReadingStatus::DRAFT;
    }

    public function isRejected(): bool
    {
        return $this->status === UtilityReadingStatus::REJECTED;
    }
}
