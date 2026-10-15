<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_code',
        'property_id',
        'room_id',
        'booking_id',
        'inspector_id',
        'tenant_id',
        'created_by',
        'baseline_inspection_id',
        'type',
        'status',
        'scheduled_date',
        'started_at',
        'completed_at',
        'overall_condition',
        'inspector_notes',
        'tenant_feedback',
        'tenant_decision_at',
        'tenant_due_at',
    ];

    protected $casts = [
        'type' => InspectionType::class,
        'status' => InspectionStatus::class,
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'tenant_decision_at' => 'datetime',
        'tenant_due_at' => 'datetime',
    ];

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

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function baselineInspection(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baseline_inspection_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(InspectionMedia::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InspectionLog::class)->latest();
    }

    public function isScheduled(): bool
    {
        return $this->status === InspectionStatus::SCHEDULED;
    }

    public function isInProgress(): bool
    {
        return $this->status === InspectionStatus::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === InspectionStatus::COMPLETED;
    }

    public function isApproved(): bool
    {
        return $this->status === InspectionStatus::APPROVED_BY_TENANT;
    }

    public function isObjected(): bool
    {
        return $this->status === InspectionStatus::OBJECTED_BY_TENANT;
    }

    public function isCancelled(): bool
    {
        return $this->status === InspectionStatus::CANCELLED;
    }

    public static function generateCode(): string
    {
        $dateStr = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        return "INSP-{$dateStr}-{$random}";
    }
}
