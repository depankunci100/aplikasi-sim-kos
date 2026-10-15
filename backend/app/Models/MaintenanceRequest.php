<?php

namespace App\Models;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'property_id',
        'room_id',
        'room_asset_id',
        'complaint_id',
        'reporter_id',
        'assigned_to',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'rejection_reason',
        'cancellation_reason',
        'scheduled_date',
        'started_at',
        'completed_at',
        'verified_at',
        'verified_by',
        'verification_notes',
        'diagnosis_notes',
        'action_taken',
        'spare_parts',
        'labor_cost',
        'material_cost',
        'total_cost',
        'photos_before',
        'photos_after',
        'sla_due_at',
        'is_overdue',
    ];

    protected $casts = [
        'category' => MaintenanceCategory::class,
        'priority' => MaintenancePriority::class,
        'status' => MaintenanceStatus::class,
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'spare_parts' => 'array',
        'photos_before' => 'array',
        'photos_after' => 'array',
        'is_overdue' => 'boolean',
        'labor_cost' => 'decimal:2',
        'material_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomAsset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class)->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            MaintenanceStatus::COMPLETED->value,
            MaintenanceStatus::CANCELLED->value,
            MaintenanceStatus::REJECTED->value,
        ]);
    }
}
