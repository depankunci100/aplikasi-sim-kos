<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLocationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'from_location_type',
        'from_location_name',
        'from_room_id',
        'to_location_type',
        'to_location_name',
        'to_room_id',
        'transferred_by',
        'transferred_at',
        'reason',
        'notes',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function transferredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
