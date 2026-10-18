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
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_id_card',
        'access_token',
        'rental_type',
        'start_date',
        'end_date',
        'actual_check_in',
        'actual_check_out',
        'rate_amount',
        'deposit_amount',
        'down_payment_amount',
        'total_amount',
        'status',
        'notes',
        'rejection_reason',
        'cancellation_reason',
        'confirmed_by',
        'cancelled_by',
        'checked_in_by',
        'expires_at',
    ];

    protected $casts = [
        'rental_type' => RentalType::class,
        'status' => BookingStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'expires_at' => 'datetime',
        'rate_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
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

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function depositLedger(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DepositLedger::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CheckOutSettlement::class);
    }

    public function isGuest(): bool
    {
        return is_null($this->user_id);
    }

    public function getCustomerNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->guest_name ?? 'Tamu Publik';
    }

    public function getCustomerPhoneAttribute(): ?string
    {
        return $this->user ? $this->user->phone : $this->guest_phone;
    }

    public function getCustomerEmailAttribute(): ?string
    {
        return $this->user ? $this->user->email : $this->guest_email;
    }

    public function canConfirm(): bool
    {
        return $this->status === BookingStatus::PENDING;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED]);
    }

    public function canCheckIn(): bool
    {
        return $this->status === BookingStatus::CONFIRMED;
    }

    public function canCheckOut(): bool
    {
        return in_array($this->status, [BookingStatus::ACTIVE, BookingStatus::OVERSTAY]);
    }

    public function isExpired(): bool
    {
        if ($this->status === BookingStatus::EXPIRED) {
            return true;
        }

        return $this->status === BookingStatus::PENDING
            && $this->expires_at !== null
            && now()->greaterThan($this->expires_at);
    }
}
