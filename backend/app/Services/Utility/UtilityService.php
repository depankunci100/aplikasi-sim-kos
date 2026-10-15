<?php

namespace App\Services\Utility;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Enums\UtilityReadingStatus;
use App\Enums\UtilityType;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use App\Models\UtilityReadingLog;
use App\Models\UtilityTariff;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UtilityService
{
    /**
     * Pastikan pengguna memiliki hak akses terhadap properti tertentu
     */
    public function authorizePropertyAccess(User $user, int $propertyId): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $hasAccess = DB::table('property_users')
            ->where('user_id', $user->id)
            ->where('property_id', $propertyId)
            ->exists();

        if (!$hasAccess) {
            throw new DomainException('Anda tidak memiliki hak akses untuk mengelola data pada properti ini.');
        }
    }

    /**
     * Dapatkan daftar ID properti yang dapat diakses pengguna
     *
     * @return array<int>|null Null berarti seluruh properti (Super Admin)
     */
    public function getAccessiblePropertyIds(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        return DB::table('property_users')
            ->where('user_id', $user->id)
            ->pluck('property_id')
            ->toArray();
    }

    // ==========================================
    // 1. MANAJEMEN METER UTILITAS FISIK
    // ==========================================

    /**
     * Ambil query meter utilitas berdasarkan filter dan hak akses
     */
    public function getMetersQuery(User $user, array $filters = []): Builder
    {
        $query = UtilityMeter::with(['property', 'room.roomType', 'latestReading'])
            ->latest('id');

        if ($user->isTenant()) {
            // Penyewa hanya melihat meter kamar miliknya yang aktif/disewa
            $activeRoomIds = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->pluck('room_id')
                ->filter();

            $query->whereIn('room_id', $activeRoomIds);
        } else {
            $accessiblePropertyIds = $this->getAccessiblePropertyIds($user);
            if ($accessiblePropertyIds !== null) {
                $query->whereIn('property_id', $accessiblePropertyIds);
            }
        }

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['utility_type'])) {
            $query->where('utility_type', $filters['utility_type']);
        }

        if (!empty($filters['meter_type'])) {
            $query->where('meter_type', $filters['meter_type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($sub) use ($term) {
                $sub->where('meter_number', 'like', "%{$term}%")
                    ->orWhereHas('room', fn ($r) => $r->where('room_number', 'like', "%{$term}%"))
                    ->orWhereHas('property', fn ($p) => $p->where('name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    /**
     * Buat meter utilitas baru
     */
    public function createMeter(array $data, User $actor): UtilityMeter
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak memiliki izin membuat meter utilitas.');
        }

        $this->authorizePropertyAccess($actor, (int) $data['property_id']);

        $property = Property::findOrFail($data['property_id']);
        $room = null;

        if (!empty($data['room_id'])) {
            $room = Room::where('property_id', $property->id)->findOrFail($data['room_id']);

            // Mencegah duplikasi meter aktif untuk jenis utilitas yang sama di kamar yang sama
            $existsActive = UtilityMeter::where('room_id', $room->id)
                ->where('utility_type', $data['utility_type'])
                ->where('is_active', true)
                ->exists();

            if ($existsActive) {
                throw new DomainException("Kamar {$room->room_number} sudah memiliki meter {$data['utility_type']} yang aktif.");
            }
        }

        $initialReading = number_format((float) ($data['initial_reading'] ?? 0.00), 2, '.', '');
        $unit = $data['unit'] ?? ($data['utility_type'] === 'electricity' ? 'kWh' : 'm3');

        return DB::transaction(function () use ($data, $property, $room, $initialReading, $unit) {
            return UtilityMeter::create([
                'property_id' => $property->id,
                'room_id' => $room?->id,
                'utility_type' => $data['utility_type'],
                'meter_number' => $data['meter_number'],
                'meter_type' => $data['meter_type'] ?? ($room ? 'individual' : 'shared'),
                'unit' => $unit,
                'initial_reading' => $initialReading,
                'current_reading' => $initialReading, // Baseline awal sama dengan initial reading
                'max_reading' => !empty($data['max_reading']) ? number_format((float) $data['max_reading'], 2, '.', '') : null,
                'installed_at' => $data['installed_at'] ?? now()->toDateString(),
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Perbarui konfigurasi meter
     */
    public function updateMeter(UtilityMeter $meter, array $data, User $actor): UtilityMeter
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak memiliki izin mengubah konfigurasi meter.');
        }

        $this->authorizePropertyAccess($actor, $meter->property_id);

        if (!empty($data['room_id']) && $data['room_id'] != $meter->room_id) {
            $room = Room::where('property_id', $meter->property_id)->findOrFail($data['room_id']);
            $meter->room_id = $room->id;
        }

        if (isset($data['meter_number']) && $data['meter_number'] !== $meter->meter_number) {
            $meter->meter_number = $data['meter_number'];
        }

        if (isset($data['unit'])) {
            $meter->unit = $data['unit'];
        }

        if (isset($data['max_reading'])) {
            $meter->max_reading = !empty($data['max_reading']) ? number_format((float) $data['max_reading'], 2, '.', '') : null;
        }

        if (isset($data['installed_at'])) {
            $meter->installed_at = $data['installed_at'];
        }

        if (isset($data['is_active'])) {
            $meter->is_active = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($data['notes'])) {
            $meter->notes = $data['notes'];
        }

        $meter->save();

        return $meter;
    }

    // ==========================================
    // 2. PENGELOLAAN TARIF UTILITAS
    // ==========================================

    /**
     * Ambil query tarif utilitas
     */
    public function getTariffsQuery(User $user, array $filters = []): Builder
    {
        $query = UtilityTariff::with(['property', 'creator', 'updater'])
            ->latest('effective_from');

        if (!$user->isSuperAdmin()) {
            $accessiblePropertyIds = $this->getAccessiblePropertyIds($user);
            if ($accessiblePropertyIds !== null) {
                $query->whereIn('property_id', $accessiblePropertyIds);
            }
        }

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (!empty($filters['utility_type'])) {
            $query->where('utility_type', $filters['utility_type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /**
     * Konfigurasi tarif baru untuk properti dan jenis utilitas
     */
    public function createTariff(array $data, User $actor): UtilityTariff
    {
        if ($actor->isTenant() || $actor->isStaff()) {
            throw new DomainException('Hanya Pemilik, Manajer, atau Kasir yang dapat mengatur tarif utilitas.');
        }

        $this->authorizePropertyAccess($actor, (int) $data['property_id']);

        $rateDecimal = number_format((float) $data['rate_per_unit'], 2, '.', '');
        if ((float) $rateDecimal <= 0) {
            throw new DomainException('Tarif per satuan harus lebih besar dari 0.');
        }

        $unit = $data['unit'] ?? ($data['utility_type'] === 'electricity' ? 'kWh' : 'm3');

        return DB::transaction(function () use ($data, $actor, $rateDecimal, $unit) {
            return UtilityTariff::create([
                'property_id' => $data['property_id'],
                'utility_type' => $data['utility_type'],
                'rate_per_unit' => $rateDecimal,
                'unit' => $unit,
                'effective_from' => $data['effective_from'],
                'effective_to' => !empty($data['effective_to']) ? $data['effective_to'] : null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Dapatkan tarif yang berlaku untuk tanggal dan jenis utilitas tertentu
     */
    public function getEffectiveTariff(int $propertyId, UtilityType|string $utilityType, string|\DateTimeInterface $date): ?UtilityTariff
    {
        $typeString = $utilityType instanceof UtilityType ? $utilityType->value : $utilityType;
        $dateString = is_string($date) ? $date : $date->format('Y-m-d');

        return UtilityTariff::where('property_id', $propertyId)
            ->where('utility_type', $typeString)
            ->active()
            ->forDate($dateString)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    // ==========================================
    // 3. PENCATATAN & AUDIT METER
    // ==========================================

    /**
     * Ambil query pembacaan meter dengan filter & isolasi keamanan
     */
    public function getReadingsQuery(User $user, array $filters = []): Builder
    {
        $query = UtilityReading::with(['meter', 'property', 'room.roomType', 'booking.user', 'invoice', 'recorder', 'reviewer'])
            ->latest('reading_date')
            ->latest('id');

        if ($user->isTenant()) {
            // Penyewa hanya melihat pembacaan untuk kamar yang pernah/sedang disewanya
            $userBookingIds = Booking::where('user_id', $user->id)->pluck('id');
            $userRoomIds = Booking::where('user_id', $user->id)->pluck('room_id')->filter();

            $query->where(function ($sub) use ($userBookingIds, $userRoomIds) {
                $sub->whereIn('booking_id', $userBookingIds)
                    ->orWhereIn('room_id', $userRoomIds);
            })->where('status', UtilityReadingStatus::APPROVED);
        } else {
            $accessiblePropertyIds = $this->getAccessiblePropertyIds($user);
            if ($accessiblePropertyIds !== null) {
                $query->whereIn('property_id', $accessiblePropertyIds);
            }
        }

        if (!empty($filters['meter_id'])) {
            $query->where('meter_id', $filters['meter_id']);
        }

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['utility_type'])) {
            $query->where('utility_type', $filters['utility_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_billed']) && $filters['is_billed'] !== '') {
            $query->where('is_billed', filter_var($filters['is_billed'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /**
     * Simpan pembacaan meter baru dengan validasi baseline, urutan tanggal, dan foto bukti privat
     */
    public function recordReading(array $data, ?UploadedFile $photo, User $actor): UtilityReading
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak diizinkan mencatat pembacaan meter resmi.');
        }

        $meter = UtilityMeter::findOrFail($data['meter_id']);
        $this->authorizePropertyAccess($actor, $meter->property_id);

        $readingDate = $data['reading_date'] ?? now()->toDateString();
        $currentMeter = round((float) $data['current_meter'], 2);

        // Ambil pembacaan terakhir yang disetujui sebelum atau pada tanggal ini
        $lastApprovedReading = UtilityReading::where('meter_id', $meter->id)
            ->where('status', UtilityReadingStatus::APPROVED)
            ->where('reading_date', '<=', $readingDate)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->first();

        // 1. Tentukan Pembacaan Sebelumnya:
        // Jika belum ada pembacaan sebelumnya yang disetujui, gunakan baseline awal meter fisik
        if ($lastApprovedReading) {
            $previousMeter = (float) $lastApprovedReading->current_meter;
        } else {
            $previousMeter = (float) $meter->initial_reading;
        }

        // 2. Validasi Pembacaan: Tidak boleh lebih kecil dari sebelumnya tanpa koreksi resmi / rollover
        if ($currentMeter < $previousMeter) {
            throw new DomainException(
                "Nilai pembacaan saat ini ({$currentMeter}) tidak boleh lebih kecil dari pembacaan sebelumnya ({$previousMeter}). " .
                "Jika meter fisik mengalami penggantian atau rollover, lakukan koreksi resmi berizin."
            );
        }

        // 3. Hitung Pemakaian Bersih
        $usage = round($currentMeter - $previousMeter, 2);
        if ($usage < 0) {
            throw new DomainException('Nilai pemakaian utilitas tidak boleh bernilai negatif.');
        }

        // 4. Hubungkan Kamar dan Booking Terkait
        $roomId = $meter->room_id ?? ($data['room_id'] ?? null);
        $bookingId = $data['booking_id'] ?? null;

        if (!$bookingId && $roomId) {
            // Cari booking aktif pada tanggal pembacaan
            $activeBooking = Booking::where('room_id', $roomId)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->where('start_date', '<=', $readingDate)
                ->where('end_date', '>=', $readingDate)
                ->first();

            $bookingId = $activeBooking?->id;
        }

        // 5. Snapshot Tarif yang Berlaku pada Tanggal Pembacaan
        $tariff = $this->getEffectiveTariff($meter->property_id, $meter->utility_type, $readingDate);
        $ratePerUnit = $tariff ? (float) $tariff->rate_per_unit : 0.00;
        $totalCost = round($usage * $ratePerUnit, 2);

        // 6. Upload Foto Bukti ke Storage Privat Lokal
        $proofPhotoPath = null;
        if ($photo) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!in_array($photo->getMimeType(), $allowedMimes)) {
                throw new DomainException('File bukti harus berformat JPG, PNG, atau WebP.');
            }
            if ($photo->getSize() > 5 * 1024 * 1024) {
                throw new DomainException('Ukuran foto bukti tidak boleh melebihi 5 MB.');
            }

            $proofPhotoPath = $photo->store("utility_proofs/{$meter->id}", 'local');
        }

        // 7. Status Pembacaan
        // Jika pencatat adalah Owner atau Manajer, dapat langsung disetujui jika dispesifikasi;
        // selain itu default 'pending_review'
        $initialStatus = $data['status'] ?? UtilityReadingStatus::PENDING_REVIEW->value;
        if (!in_array($initialStatus, [UtilityReadingStatus::DRAFT->value, UtilityReadingStatus::PENDING_REVIEW->value, UtilityReadingStatus::APPROVED->value])) {
            $initialStatus = UtilityReadingStatus::PENDING_REVIEW->value;
        }

        return DB::transaction(function () use (
            $meter,
            $roomId,
            $bookingId,
            $previousMeter,
            $currentMeter,
            $usage,
            $ratePerUnit,
            $totalCost,
            $readingDate,
            $data,
            $proofPhotoPath,
            $initialStatus,
            $actor
        ) {
            $reading = UtilityReading::create([
                'meter_id' => $meter->id,
                'property_id' => $meter->property_id,
                'room_id' => $roomId,
                'booking_id' => $bookingId,
                'utility_type' => $meter->utility_type,
                'previous_meter' => number_format($previousMeter, 2, '.', ''),
                'current_meter' => number_format($currentMeter, 2, '.', ''),
                'usage' => number_format($usage, 2, '.', ''),
                'rate_per_unit' => number_format($ratePerUnit, 2, '.', ''),
                'total_cost' => number_format($totalCost, 2, '.', ''),
                'reading_date' => $readingDate,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'proof_photo' => $proofPhotoPath,
                'status' => $initialStatus,
                'recorded_by' => $actor->id,
                'reviewed_by' => $initialStatus === UtilityReadingStatus::APPROVED->value ? $actor->id : null,
                'reviewed_at' => $initialStatus === UtilityReadingStatus::APPROVED->value ? now() : null,
                'is_billed' => false,
                'notes' => $data['notes'] ?? null,
            ]);

            // Audit Trail Log
            UtilityReadingLog::create([
                'utility_reading_id' => $reading->id,
                'user_id' => $actor->id,
                'action' => 'recorded',
                'new_data' => [
                    'current_meter' => $currentMeter,
                    'previous_meter' => $previousMeter,
                    'usage' => $usage,
                    'rate_per_unit' => $ratePerUnit,
                    'total_cost' => $totalCost,
                    'status' => $initialStatus,
                ],
                'notes' => 'Pembacaan meter dicatat oleh ' . $actor->name,
            ]);

            // Update meter current reading jika status disetujui
            if ($initialStatus === UtilityReadingStatus::APPROVED->value) {
                $meter->update(['current_reading' => number_format($currentMeter, 2, '.', '')]);
            }

            return $reading;
        });
    }

    /**
     * Tinjau dan setujui / tolak pembacaan meter
     */
    public function reviewReading(UtilityReading $reading, string $status, ?string $rejectionReason, User $actor): UtilityReading
    {
        if ($actor->isTenant() || $actor->isStaff()) {
            throw new DomainException('Hanya Pemilik, Manajer, atau Kasir yang dapat meninjau pembacaan meter.');
        }

        $this->authorizePropertyAccess($actor, $reading->property_id);

        if ($reading->is_billed) {
            throw new DomainException('Pembacaan ini sudah ditagihkan ke invoice dan tidak dapat diubah statusnya.');
        }

        if (!in_array($status, [UtilityReadingStatus::APPROVED->value, UtilityReadingStatus::REJECTED->value])) {
            throw new DomainException('Status review tidak valid. Pilih disetujui atau ditolak.');
        }

        if ($status === UtilityReadingStatus::REJECTED->value && empty(trim($rejectionReason ?? ''))) {
            throw new DomainException('Alasan penolakan wajib diisi saat menolak pembacaan meter.');
        }

        return DB::transaction(function () use ($reading, $status, $rejectionReason, $actor) {
            $prevStatus = $reading->status->value;

            $reading->update([
                'status' => $status,
                'rejection_reason' => $status === UtilityReadingStatus::REJECTED->value ? $rejectionReason : null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            if ($status === UtilityReadingStatus::APPROVED->value && $reading->meter) {
                $reading->meter->update([
                    'current_reading' => $reading->current_meter,
                ]);
            }

            UtilityReadingLog::create([
                'utility_reading_id' => $reading->id,
                'user_id' => $actor->id,
                'action' => $status,
                'previous_data' => ['status' => $prevStatus],
                'new_data' => [
                    'status' => $status,
                    'rejection_reason' => $rejectionReason,
                ],
                'notes' => $status === UtilityReadingStatus::APPROVED->value
                    ? 'Pembacaan disetujui oleh ' . $actor->name
                    : 'Pembacaan ditolak oleh ' . $actor->name . '. Alasan: ' . $rejectionReason,
            ]);

            return $reading;
        });
    }

    /**
     * Koreksi pembacaan meter dengan audit log ketat (nilai lama, baru, aktor, alasan)
     */
    public function correctReading(UtilityReading $reading, float $newCurrentMeter, string $reason, User $actor): UtilityReading
    {
        if ($actor->isTenant() || $actor->isStaff()) {
            throw new DomainException('Hanya Pemilik, Manajer, atau Kasir yang berwenang melakukan koreksi pembacaan meter.');
        }

        $this->authorizePropertyAccess($actor, $reading->property_id);

        if ($reading->is_billed) {
            throw new DomainException('Pembacaan yang telah ditagihkan ke invoice tidak boleh dikoreksi langsung. Hubungi kasir untuk penyesuaian invoice.');
        }

        if (empty(trim($reason))) {
            throw new DomainException('Alasan koreksi pembacaan meter wajib diisi.');
        }

        $previousMeter = (float) $reading->previous_meter;
        if ($newCurrentMeter < $previousMeter) {
            throw new DomainException("Nilai koreksi ({$newCurrentMeter}) tidak boleh lebih kecil dari pembacaan sebelumnya ({$previousMeter}).");
        }

        $newUsage = round($newCurrentMeter - $previousMeter, 2);
        $ratePerUnit = (float) $reading->rate_per_unit;
        $newTotalCost = round($newUsage * $ratePerUnit, 2);

        return DB::transaction(function () use ($reading, $newCurrentMeter, $newUsage, $newTotalCost, $reason, $actor) {
            $oldData = [
                'current_meter' => (float) $reading->current_meter,
                'usage' => (float) $reading->usage,
                'total_cost' => (float) $reading->total_cost,
            ];

            $reading->update([
                'current_meter' => number_format($newCurrentMeter, 2, '.', ''),
                'usage' => number_format($newUsage, 2, '.', ''),
                'total_cost' => number_format($newTotalCost, 2, '.', ''),
            ]);

            if ($reading->status === UtilityReadingStatus::APPROVED && $reading->meter) {
                $reading->meter->update([
                    'current_reading' => number_format($newCurrentMeter, 2, '.', ''),
                ]);
            }

            UtilityReadingLog::create([
                'utility_reading_id' => $reading->id,
                'user_id' => $actor->id,
                'action' => 'corrected',
                'previous_data' => $oldData,
                'new_data' => [
                    'current_meter' => $newCurrentMeter,
                    'usage' => $newUsage,
                    'total_cost' => $newTotalCost,
                ],
                'notes' => 'Koreksi pembacaan: ' . $reason,
            ]);

            return $reading;
        });
    }

    // ==========================================
    // 4. SIKLUS & INTEGRASI TAGIHAN KE INVOICE EXISTING
    // ==========================================

    /**
     * Pratinjau perhitungan tagihan utilitas sebelum diterbitkan
     */
    public function previewBill(int $readingId, User $actor): array
    {
        $reading = UtilityReading::with(['meter', 'property', 'room.roomType', 'booking.user'])
            ->findOrFail($readingId);

        $this->authorizePropertyAccess($actor, $reading->property_id);

        $tariff = $this->getEffectiveTariff($reading->property_id, $reading->utility_type, $reading->reading_date);

        return [
            'reading_id' => $reading->id,
            'property' => [
                'id' => $reading->property->id,
                'name' => $reading->property->name,
            ],
            'room' => $reading->room ? [
                'id' => $reading->room->id,
                'room_number' => $reading->room->room_number,
                'type' => $reading->room->roomType?->name,
            ] : null,
            'meter' => [
                'id' => $reading->meter?->id,
                'number' => $reading->meter?->meter_number,
                'type' => $reading->meter?->meter_type?->label() ?? 'Individual',
                'unit' => $reading->meter?->unit ?? $reading->utility_type->defaultUnit(),
            ],
            'utility_type' => $reading->utility_type->value,
            'utility_type_label' => $reading->utility_type->label(),
            'reading_date' => $reading->reading_date->format('Y-m-d'),
            'period_start' => $reading->period_start?->format('Y-m-d'),
            'period_end' => $reading->period_end?->format('Y-m-d'),
            'previous_meter' => (float) $reading->previous_meter,
            'current_meter' => (float) $reading->current_meter,
            'usage' => (float) $reading->usage,
            'rate_per_unit' => (float) $reading->rate_per_unit,
            'tariff_configured_rate' => $tariff ? (float) $tariff->rate_per_unit : null,
            'total_cost' => (float) $reading->total_cost,
            'status' => $reading->status->value,
            'is_billed' => (bool) $reading->is_billed,
            'invoice_id' => $reading->invoice_id,
            'booking' => $reading->booking ? [
                'id' => $reading->booking->id,
                'booking_code' => $reading->booking->booking_code,
                'tenant_name' => $reading->booking->user?->name,
            ] : null,
        ];
    }

    /**
     * Terbitkan tagihan utilitas ke sistem invoice existing secara idempoten
     * Menjamin tidak ada penagihan ganda menggunakan row-level locking (lockForUpdate)
     */
    public function generateUtilityInvoice(int $readingId, User $actor, ?int $targetInvoiceId = null): Invoice
    {
        if ($actor->isTenant() || $actor->isStaff()) {
            throw new DomainException('Hanya Pemilik, Manajer, atau Kasir yang berwenang menerbitkan tagihan utilitas.');
        }

        return DB::transaction(function () use ($readingId, $actor, $targetInvoiceId) {
            // Lock row reading untuk mencegah race condition atau double billing
            $reading = UtilityReading::with(['meter', 'property', 'room', 'booking.user'])
                ->where('id', $readingId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizePropertyAccess($actor, $reading->property_id);

            if ($reading->is_billed || $reading->invoice_id) {
                throw new DomainException(
                    "Tagihan untuk pembacaan meter ini sudah pernah diterbitkan sebelumnya (Invoice ID: {$reading->invoice_id})."
                );
            }

            if ($reading->status !== UtilityReadingStatus::APPROVED) {
                throw new DomainException(
                    "Pembacaan meter harus disetujui terlebih dahulu sebelum dapat diterbitkan menjadi tagihan. Status saat ini: {$reading->status->label()}."
                );
            }

            // Tentukan penyewa tujuan tagihan
            $tenantId = null;
            $bookingId = $reading->booking_id;

            if ($reading->booking) {
                $tenantId = $reading->booking->user_id;
            } elseif ($reading->room_id) {
                $activeBooking = Booking::where('room_id', $reading->room_id)
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->latest('id')
                    ->first();

                if ($activeBooking) {
                    $tenantId = $activeBooking->user_id;
                    $bookingId = $activeBooking->id;
                    $reading->booking_id = $bookingId;
                }
            }

            if (!$tenantId && !$targetInvoiceId) {
                throw new DomainException(
                    'Tidak ditemukan data penyewa aktif untuk kamar ini. Lampirkan ke invoice existing atau pastikan kamar berpenyewa.'
                );
            }

            $cost = round((float) $reading->total_cost, 2);
            $unit = $reading->meter?->unit ?? $reading->utility_type->defaultUnit();
            $roomName = $reading->room ? "Kamar {$reading->room->room_number}" : 'Meter Bersama';
            $itemName = "Tagihan {$reading->utility_type->label()} ({$roomName})";

            $metadataSnapshot = [
                'reading_id' => $reading->id,
                'meter_id' => $reading->meter_id,
                'meter_number' => $reading->meter?->meter_number,
                'previous_meter' => (float) $reading->previous_meter,
                'current_meter' => (float) $reading->current_meter,
                'usage' => (float) $reading->usage,
                'unit' => $unit,
                'rate' => (float) $reading->rate_per_unit,
                'reading_date' => $reading->reading_date->format('Y-m-d'),
                'period_start' => $reading->period_start?->format('Y-m-d'),
                'period_end' => $reading->period_end?->format('Y-m-d'),
                'issued_by' => $actor->name,
                'issued_at' => now()->toIso8601String(),
            ];

            if ($targetInvoiceId) {
                // Skenario A: Tambahkan item ke Invoice existing
                $invoice = Invoice::where('id', $targetInvoiceId)->lockForUpdate()->firstOrFail();

                if ($invoice->property_id !== $reading->property_id) {
                    throw new DomainException('Invoice tujuan harus berada pada properti yang sama.');
                }

                if ($invoice->status === InvoiceStatus::PAID) {
                    throw new DomainException('Tidak dapat menambahkan tagihan utilitas ke invoice yang sudah lunas.');
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => $reading->utility_type->value,
                    'name' => $itemName,
                    'quantity' => $reading->usage,
                    'unit' => $unit,
                    'rate' => $reading->rate_per_unit,
                    'amount' => $cost,
                    'metadata' => $metadataSnapshot,
                ]);

                $newSubtotal = round((float) $invoice->subtotal + $cost, 2);
                $newTotal = round((float) $invoice->total_amount + $cost, 2);

                $invoice->update([
                    'subtotal' => number_format($newSubtotal, 2, '.', ''),
                    'total_amount' => number_format($newTotal, 2, '.', ''),
                ]);

                $invoice->syncPaidAmountAndStatus();
            } else {
                // Skenario B: Terbitkan Invoice baru khusus tagihan utilitas
                $invoiceNumber = 'INV-UTL-' . date('Ymd') . '-' . strtoupper(Str::random(5));

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'property_id' => $reading->property_id,
                    'booking_id' => $bookingId,
                    'user_id' => $tenantId,
                    'invoice_type' => $reading->utility_type->value,
                    'period_start' => $reading->period_start ?? $reading->reading_date,
                    'period_end' => $reading->period_end ?? $reading->reading_date,
                    'subtotal' => number_format($cost, 2, '.', ''),
                    'discount' => 0.00,
                    'penalty' => 0.00,
                    'total_amount' => number_format($cost, 2, '.', ''),
                    'paid_amount' => 0.00,
                    'status' => InvoiceStatus::UNPAID,
                    'due_date' => now()->addDays(7)->toDateString(),
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => $reading->utility_type->value,
                    'name' => $itemName,
                    'quantity' => $reading->usage,
                    'unit' => $unit,
                    'rate' => $reading->rate_per_unit,
                    'amount' => $cost,
                    'metadata' => $metadataSnapshot,
                ]);
            }

            // Kunci reading agar idempoten dan tidak dapat ditagihkan lagi
            $reading->update([
                'invoice_id' => $invoice->id,
                'is_billed' => true,
            ]);

            // Audit Trail
            UtilityReadingLog::create([
                'utility_reading_id' => $reading->id,
                'user_id' => $actor->id,
                'action' => 'billed',
                'new_data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $cost,
                ],
                'notes' => "Tagihan diterbitkan ke Invoice #{$invoice->invoice_number} oleh {$actor->name}",
            ]);

            return $invoice;
        });
    }

    /**
     * Dapatkan path file foto bukti privat jika pengguna memiliki hak akses
     */
    public function getAuthorizedProofPhotoPath(UtilityReading $reading, User $user): string
    {
        if (empty($reading->proof_photo)) {
            throw new DomainException('Foto bukti tidak tersedia untuk pembacaan meter ini.');
        }

        // Cek otorisasi akses:
        // 1. Super Admin
        // 2. Pemilik / Manajer / Staf dari properti terkait
        // 3. Penyewa yang terikat dengan kamar/booking pembacaan ini
        $isAuthorized = false;

        if ($user->isSuperAdmin()) {
            $isAuthorized = true;
        } elseif (!$user->isTenant()) {
            $isAuthorized = DB::table('property_users')
                ->where('user_id', $user->id)
                ->where('property_id', $reading->property_id)
                ->exists();
        } else {
            // Tenant
            if ($reading->booking_id) {
                $isAuthorized = Booking::where('id', $reading->booking_id)
                    ->where('user_id', $user->id)
                    ->exists();
            } elseif ($reading->room_id) {
                $isAuthorized = Booking::where('room_id', $reading->room_id)
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->exists();
            }
        }

        if (!$isAuthorized) {
            throw new DomainException('Anda tidak berhak melihat foto bukti pembacaan meter ini.');
        }

        if (!Storage::disk('local')->exists($reading->proof_photo)) {
            throw new DomainException('Berkas foto bukti tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('local')->path($reading->proof_photo);
    }
}
