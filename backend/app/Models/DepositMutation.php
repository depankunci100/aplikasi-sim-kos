<?php

namespace App\Models;

use App\Enums\DepositMutationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositMutation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'deposit_ledger_id',
        'mutation_code',
        'transaction_type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'payment_method',
        'payment_reference',
        'inspection_id',
        'invoice_id',
        'payment_id',
        'performed_by',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'transaction_type' => DepositMutationType::class,
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(DepositLedger::class, 'deposit_ledger_id');
    }

    public function depositLedger(): BelongsTo
    {
        return $this->ledger();
    }


    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
