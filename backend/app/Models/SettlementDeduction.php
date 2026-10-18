<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'inspection_item_id',
        'title',
        'description',
        'proposed_amount',
        'approved_amount',
        'is_approved',
        'is_disputed',
        'dispute_reason',
    ];

    protected $casts = [
        'proposed_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'is_approved' => 'boolean',
        'is_disputed' => 'boolean',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CheckOutSettlement::class, 'settlement_id');
    }

    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class);
    }
}
