<?php

namespace App\Models;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'property_id',
        'code',
        'name',
        'type',
        'normal_balance',
        'category',
        'description',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'normal_balance' => AccountNormalBalance::class,
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProperty(Builder $query, ?int $propertyId): Builder
    {
        if ($propertyId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($propertyId) {
            $q->where('property_id', $propertyId)
              ->orWhereNull('property_id');
        });
    }

    public function scopeOfType(Builder $query, AccountType|string $type): Builder
    {
        $val = $type instanceof AccountType ? $type->value : $type;
        return $query->where('type', $val);
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === AccountNormalBalance::DEBIT;
    }

    public function isCreditNormal(): bool
    {
        return $this->normal_balance === AccountNormalBalance::CREDIT;
    }
}
