<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'description',
        'base_price_daily',
        'base_price_monthly',
        'base_price_yearly',
        'max_occupants',
        'size_m2',
        'facilities',
        'photos',
    ];

    protected $casts = [
        'base_price_daily' => 'decimal:2',
        'base_price_monthly' => 'decimal:2',
        'base_price_yearly' => 'decimal:2',
        'size_m2' => 'decimal:2',
        'facilities' => 'array',
        'photos' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
