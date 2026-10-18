<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'property_id',
        'booking_id',
        'user_id',
        'invoice_type',
        'period_start',
        'period_end',
        'subtotal',
        'discount',
        'penalty',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
        'paid_at',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'penalty' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Tamu Publik',
            'email' => '-',
            'phone' => '-',
        ]);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function utilityReadings(): HasMany
    {
        return $this->hasMany(UtilityReading::class);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0.00, round((float) $this->total_amount - (float) $this->paid_amount, 2));
    }

    /**
     * Sinkronisasi ulang total pembayaran terverifikasi dan status invoice
     */
    public function syncPaidAmountAndStatus(): void
    {
        $verifiedSum = (float) $this->payments()
            ->where('status', 'verified')
            ->sum('amount');

        $this->paid_amount = round($verifiedSum, 2);

        if ($this->paid_amount >= (float) $this->total_amount) {
            $this->status = InvoiceStatus::PAID;
            $this->paid_at = $this->paid_at ?? now();
        } elseif ($this->paid_amount > 0) {
            $this->status = InvoiceStatus::PARTIALLY_PAID;
            $this->paid_at = null;
        } else {
            $this->status = InvoiceStatus::UNPAID;
            $this->paid_at = null;
        }

        $this->save();
    }
}
