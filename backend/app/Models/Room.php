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
        'status',
        'is_active',
    ];

    protected $casts = [
        'status' => RoomStatus::class,
        'is_active' => 'boolean',
        'floor' => 'integer',
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

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === RoomStatus::AVAILABLE;
    }
}
