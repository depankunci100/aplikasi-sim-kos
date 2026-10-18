<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckOutSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_code',
        'booking_id',
        'deposit_ledger_id',
        'inspection_id',
        'property_id',
        'tenant_id',
        'check_out_date',
        'status',
        'deposit_balance_before',
        'total_deductions',
        'net_refund_amount',
        'tenant_objection_notes',
        'tenant_approved_at',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
        'refund_method',
        'refund_destination',
        'refund_payment_reference',
        'refund_receipt_proof',
        'refunded_by',
        'refunded_at',
        'created_by',
    ];

    protected $casts = [
        'status' => SettlementStatus::class,
        'check_out_date' => 'date',
        'deposit_balance_before' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_refund_amount' => 'decimal:2',
        'tenant_approved_at' => 'datetime',
        'resolved_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function depositLedger(): BelongsTo
    {
        return $this->belongsTo(DepositLedger::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(SettlementDeduction::class, 'settlement_id');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === SettlementStatus::PENDING_TENANT_APPROVAL;
    }

    public function isDisputed(): bool
    {
        return $this->status === SettlementStatus::DISPUTED;
    }

    public function isApproved(): bool
    {
        return $this->status === SettlementStatus::APPROVED;
    }

    public function isSettled(): bool
    {
        return $this->status === SettlementStatus::SETTLED;
    }
}
