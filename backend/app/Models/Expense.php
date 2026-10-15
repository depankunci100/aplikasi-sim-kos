<?php

namespace App\Models;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_number',
        'property_id',
        'expense_category_id',
        'user_id',
        'title',
        'category',
        'vendor_name',
        'amount',
        'expense_date',
        'receipt_photo',
        'payment_method',
        'approval_status',
        'payment_status',
        'paid_amount',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'paid_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'approval_status' => ExpenseApprovalStatus::class,
        'payment_status' => ExpensePaymentStatus::class,
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'expense_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExpenseLog::class)->orderBy('created_at', 'desc');
    }

    public function isDraft(): bool
    {
        return $this->approval_status === ExpenseApprovalStatus::DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->approval_status === ExpenseApprovalStatus::SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ExpenseApprovalStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === ExpenseApprovalStatus::REJECTED;
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === ExpensePaymentStatus::UNPAID;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === ExpensePaymentStatus::PARTIALLY_PAID;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === ExpensePaymentStatus::PAID;
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0.00, round((float) $this->amount - (float) $this->paid_amount, 2));
    }
}
