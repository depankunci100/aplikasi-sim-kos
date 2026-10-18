<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code',
        'property_id',
        'room_id',
        'name',
        'category',
        'classification',
        'location_type',
        'location_name',
        'description',
        'acquisition_date',
        'in_service_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_months',
        'depreciation_method',
        'accumulated_depreciation',
        'current_book_value',
        'vendor_name',
        'purchase_reference',
        'serial_number',
        'warranty_expiry_date',
        'condition',
        'lifecycle_status',
        'pic_user_id',
        'expense_id',
        'photos',
        'documents',
        'notes',
        'disposal_date',
        'disposal_price',
        'disposal_cost',
        'disposal_notes',
        'disposal_journal_id',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'in_service_date' => 'date',
        'warranty_expiry_date' => 'date',
        'disposal_date' => 'date',
        'purchase_cost' => 'float',
        'salvage_value' => 'float',
        'useful_life_months' => 'integer',
        'accumulated_depreciation' => 'float',
        'current_book_value' => 'float',
        'disposal_price' => 'float',
        'disposal_cost' => 'float',
        'photos' => 'array',
        'documents' => 'array',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function disposalJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'disposal_journal_id');
    }

    public function locationHistories(): HasMany
    {
        return $this->hasMany(AssetLocationHistory::class)->latest('transferred_at');
    }

    public function depreciationSchedules(): HasMany
    {
        return $this->hasMany(AssetDepreciationSchedule::class)->orderBy('period_date');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function isDepreciable(): bool
    {
        return $this->classification === 'fixed_asset'
            && $this->depreciation_method === 'straight_line'
            && $this->useful_life_months > 0
            && $this->purchase_cost > $this->salvage_value
            && !in_array($this->lifecycle_status, ['disposed', 'written_off']);
    }

    public function isFullyDepreciated(): bool
    {
        return $this->current_book_value <= $this->salvage_value;
    }

    public function calculateMonthlyDepreciation(): float
    {
        if (!$this->isDepreciable()) {
            return 0.00;
        }

        $depreciableBase = max(0, $this->purchase_cost - $this->salvage_value);
        return round($depreciableBase / $this->useful_life_months, 2);
    }

    public function getLocationLabelAttribute(): string
    {
        if ($this->room_id && $this->room) {
            return "Kamar " . $this->room->room_number . ($this->room->building ? " ({$this->room->building})" : "");
        }

        return $this->location_name ?: match ($this->location_type) {
            'common_area' => 'Area Umum / Fasilitas Bersama',
            'warehouse' => 'Gudang Logistik',
            'building' => 'Infrastruktur Gedung',
            default => 'Lokasi Umum'
        };
    }
}
