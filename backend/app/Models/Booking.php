<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\RentalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'property_id',
        'room_id',
        'user_id',
        'rental_type',
        'start_date',
        'end_date',
        'actual_check_in',
        'actual_check_out',
        'rate_amount',
        'deposit_amount',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'rental_type' => RentalType::class,
        'status' => BookingStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'rate_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
