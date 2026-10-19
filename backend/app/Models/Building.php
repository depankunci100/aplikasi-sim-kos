<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'code',
        'name',
        'total_floors',
        'address_notes',
        'description',
        'is_active',
    ];

    protected $casts = [
        'total_floors' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke properti induk
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Relasi ke seluruh kamar di dalam gedung ini
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Scope gedung aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope gedung berdasarkan properti
     */
    public function scopeForProperty($query, int $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Aksesor jumlah kamar
     */
    public function getTotalRoomsCountAttribute(): int
    {
        return $this->rooms()->count();
    }
}
