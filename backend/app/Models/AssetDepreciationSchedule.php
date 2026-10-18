<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'property_id',
        'fiscal_year',
        'fiscal_month',
        'period_date',
        'depreciation_amount',
        'accumulated_depreciation_before',
        'accumulated_depreciation_after',
        'book_value_beginning',
        'book_value_ending',
        'status',
        'journal_entry_id',
        'posted_at',
        'idempotency_key',
    ];

    protected $casts = [
        'period_date' => 'date',
        'posted_at' => 'datetime',
        'fiscal_year' => 'integer',
        'fiscal_month' => 'integer',
        'depreciation_amount' => 'float',
        'accumulated_depreciation_before' => 'float',
        'accumulated_depreciation_after' => 'float',
        'book_value_beginning' => 'float',
        'book_value_ending' => 'float',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
