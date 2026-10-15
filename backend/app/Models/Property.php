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
    ];

    protected $casts = [
        'facilities' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];

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

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
