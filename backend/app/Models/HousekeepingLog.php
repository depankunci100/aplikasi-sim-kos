<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HousekeepingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'housekeeping_task_id',
        'user_id',
        'action',
        'previous_status',
        'new_status',
        'notes',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(HousekeepingTask::class, 'housekeeping_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
