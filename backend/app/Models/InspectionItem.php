<?php

namespace App\Models;

use App\Enums\InspectionItemCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_id',
        'room_asset_id',
        'item_name',
        'category',
        'condition',
        'notes',
        'repair_required',
        'estimated_cost',
    ];

    protected $casts = [
        'condition' => InspectionItemCondition::class,
        'repair_required' => 'boolean',
        'estimated_cost' => 'decimal:2',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function roomAsset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(InspectionMedia::class);
    }
}
