<?php

namespace App\Models;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HousekeepingTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_number',
        'property_id',
        'room_id',
        'area_name',
        'type',
        'priority',
        'scheduled_date',
        'scheduled_slot',
        'assigned_to',
        'assigned_by',
        'status',
        'checklist',
        'photos_before',
        'photos_after',
        'damaged_or_missing_items',
        'notes',
        'started_at',
        'completed_at',
        'verified_at',
        'verified_by',
        'verification_notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'type' => HousekeepingType::class,
        'status' => HousekeepingStatus::class,
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'checklist' => 'array',
        'photos_before' => 'array',
        'photos_after' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HousekeepingLog::class)->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            HousekeepingStatus::COMPLETED->value,
            HousekeepingStatus::CANCELLED->value,
        ]);
    }
}
