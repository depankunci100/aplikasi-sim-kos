<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'property_id',
        'room_id',
        'user_id',
        'title',
        'description',
        'photos',
        'priority',
        'status',
        'assigned_to',
        'resolution_notes',
        'resolved_at',
        'category',
        'maintenance_request_id',
        'first_responded_at',
        'rejection_reason',
        'reopen_reason',
        'reopened_at',
        'tenant_feedback',
        'rating',
        'acknowledged_at',
        'acknowledged_by',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'category' => ComplaintCategory::class,
        'priority' => ComplaintPriority::class,
        'status' => ComplaintStatus::class,
        'photos' => 'array',
        'resolved_at' => 'datetime',
        'first_responded_at' => 'datetime',
        'reopened_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'closed_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ComplaintLog::class)->latest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ComplaintComment::class)->oldest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ComplaintStatus::CLOSED->value,
            ComplaintStatus::REJECTED->value,
        ]);
    }
}
