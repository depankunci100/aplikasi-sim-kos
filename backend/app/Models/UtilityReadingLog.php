<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityReadingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'utility_reading_id',
        'user_id',
        'action',
        'previous_data',
        'new_data',
        'notes',
    ];

    protected $casts = [
        'previous_data' => 'array',
        'new_data' => 'array',
    ];

    public function utilityReading(): BelongsTo
    {
        return $this->belongsTo(UtilityReading::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
