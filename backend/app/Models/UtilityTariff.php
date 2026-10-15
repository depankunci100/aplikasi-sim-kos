<?php

namespace App\Models;

use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityTariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'utility_type',
        'rate_per_unit',
        'unit',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
        'updated_by',
        'notes',
    ];

    protected $casts = [
        'utility_type' => UtilityType::class,
        'rate_per_unit' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDate(Builder $query, string|\DateTimeInterface $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function (Builder $sub) use ($date) {
                $sub->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }
}
