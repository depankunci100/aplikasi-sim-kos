<?php

namespace App\Models;

use App\Enums\MeterType;
use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UtilityMeter extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'room_id',
        'utility_type',
        'meter_number',
        'meter_type',
        'unit',
        'initial_reading',
        'current_reading',
        'max_reading',
        'installed_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'utility_type' => UtilityType::class,
        'meter_type' => MeterType::class,
        'initial_reading' => 'decimal:2',
        'current_reading' => 'decimal:2',
        'max_reading' => 'decimal:2',
        'installed_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(UtilityReading::class, 'meter_id');
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(UtilityReading::class, 'meter_id')->latestOfMany('reading_date');
    }

    public function isIndividual(): bool
    {
        return $this->meter_type === MeterType::INDIVIDUAL;
    }

    public function isShared(): bool
    {
        return $this->meter_type === MeterType::SHARED;
    }
}
