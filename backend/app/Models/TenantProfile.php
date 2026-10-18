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
        'nik_hash',
        'is_nik_verified',
        'nik_verified_at',
        'ktp_photo',
        'emergency_name',
        'emergency_phone',
        'occupation',
        'institution_name',
        'vehicle_type',
        'vehicle_plate',
    ];

    protected $casts = [
        'ktp_number' => 'encrypted',
        'is_nik_verified' => 'boolean',
        'nik_verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tampilkan NIK yang disamarkan demi keamanan & privasi (contoh: 3507********1234)
     */
    public function getMaskedNikAttribute(): ?string
    {
        if (empty($this->ktp_number)) {
            return null;
        }

        $length = strlen($this->ktp_number);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = substr($this->ktp_number, 0, 4);
        $suffix = substr($this->ktp_number, -4);
        return $prefix . str_repeat('*', max(0, $length - 8)) . $suffix;
    }
}
