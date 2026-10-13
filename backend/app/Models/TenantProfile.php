<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ktp_number',
        'ktp_photo',
        'emergency_name',
        'emergency_phone',
        'occupation',
        'institution_name',
        'vehicle_type',
        'vehicle_plate',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
