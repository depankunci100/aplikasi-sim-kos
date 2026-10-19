<?php

namespace App\Services\Room;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomChangeHistory;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomService
{
    /**
     * Dapatkan query daftar kamar dengan isolasi properti dan multi-kriteria filter.
     */
    public function getRoomsQuery(User $user, array $filters = []): Builder
    {
        $query = Room::query()->with(['property', 'roomType', 'assets']);

        // 1. Property Isolation
        if (!$user->isSuperAdmin()) {
            $allowedPropertyIds = $user->properties()->pluck('properties.id')->toArray();
            $query->whereIn('rooms.property_id', $allowedPropertyIds);
        }

        // 2. Filter Properti
        if (!empty($filters['property_id'])) {
            $query->where('rooms.property_id', (int) $filters['property_id']);
        }

        // 3. Filter Tipe Kamar
        if (!empty($filters['room_type_id'])) {
            $query->where('rooms.room_type_id', (int) $filters['room_type_id']);
        }

        // 4. Filter Status
        if (!empty($filters['status'])) {
            $query->where('rooms.status', $filters['status']);
        }

        // 5. Filter Gedung & Lantai
        if (!empty($filters['building'])) {
            $query->where('rooms.building', $filters['building']);
        }

        if (isset($filters['floor']) && $filters['floor'] !== '') {
            $query->where('rooms.floor', (int) $filters['floor']);
        }

        // 6. Filter Aktif / Nonaktif
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('rooms.is_active', (bool) $filters['is_active']);
        }

        // 6. Filter Building
        if (!empty($filters['building_id'])) {
            $query->where('rooms.building_id', $filters['building_id']);
        }

        // 7. Pencarian Text
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('rooms.room_number', 'like', "%{$search}%")
                    ->orWhere('rooms.building', 'like', "%{$search}%")
                    ->orWhereHas('buildingMaster', function (Builder $bQ) use ($search) {
                        $bQ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhere('rooms.description', 'like', "%{$search}%")
                    ->orWhereHas('roomType', function (Builder $typeQ) use ($search) {
                        $typeQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('rooms.property_id')->orderBy('rooms.floor')->orderBy('rooms.room_number');
    }

    /**
     * Tambah data kamar baru dengan validasi keunikan dan audit trail.
     */
    public function createRoom(User $user, array $data): Room
    {
        $propertyId = (int) $data['property_id'];

        // Cek otorisasi properti
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $propertyId)->exists()) {
            throw new DomainException('Anda tidak memiliki akses untuk menambah kamar pada cabang properti ini.');
        }

        // Validasi keunikan nomor kamar per properti
        $roomNumber = trim($data['room_number']);
        $exists = Room::where('property_id', $propertyId)
            ->where('room_number', $roomNumber)
            ->exists();

        if ($exists) {
            throw new DomainException("Nomor kamar '{$roomNumber}' sudah terdaftar pada properti ini.");
        }

        $buildingId = !empty($data['building_id']) ? (int) $data['building_id'] : null;
        $buildingName = !empty($data['building']) ? trim($data['building']) : null;
        if ($buildingId && empty($buildingName)) {
            $building = \App\Models\Building::find($buildingId);
            if ($building) {
                $buildingName = $building->name;
            }
        }

        return DB::transaction(function () use ($user, $data, $propertyId, $roomNumber, $buildingId, $buildingName) {
            $room = Room::create([
                'property_id' => $propertyId,
                'room_type_id' => (int) $data['room_type_id'],
                'room_number' => $roomNumber,
                'floor' => (int) ($data['floor'] ?? 1),
                'building_id' => $buildingId,
                'building' => $buildingName,
                'size_m2' => isset($data['size_m2']) && $data['size_m2'] !== '' ? (float) $data['size_m2'] : null,
                'capacity' => isset($data['capacity']) && $data['capacity'] !== '' ? (int) $data['capacity'] : null,
                'description' => $data['description'] ?? null,
                'facilities' => isset($data['facilities']) ? (is_array($data['facilities']) ? $data['facilities'] : json_decode($data['facilities'], true)) : null,
                'price_daily' => isset($data['price_daily']) && $data['price_daily'] !== '' ? (float) $data['price_daily'] : null,
                'price_monthly' => isset($data['price_monthly']) && $data['price_monthly'] !== '' ? (float) $data['price_monthly'] : null,
                'price_yearly' => isset($data['price_yearly']) && $data['price_yearly'] !== '' ? (float) $data['price_yearly'] : null,
                'deposit_amount' => isset($data['deposit_amount']) && $data['deposit_amount'] !== '' ? (float) $data['deposit_amount'] : null,
                'down_payment_amount' => isset($data['down_payment_amount']) && $data['down_payment_amount'] !== '' ? (float) $data['down_payment_amount'] : null,
                'photos' => isset($data['photos']) ? (is_array($data['photos']) ? $data['photos'] : json_decode($data['photos'], true)) : null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'status' => $data['status'] ?? RoomStatus::AVAILABLE->value,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ]);

            // Catat riwayat pembuatan
            RoomChangeHistory::create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'action' => 'created',
                'new_values' => $room->only([
                    'property_id', 'room_type_id', 'room_number', 'floor', 'building',
                    'price_monthly', 'status', 'is_active'
                ]),
                'notes' => 'Master kamar baru berhasil didaftarkan.',
            ]);

            return $room->load(['property', 'roomType']);
        });
    }

    /**
     * Edit kamar yang ada dengan audit trail per perubahan.
     */
    public function updateRoom(Room $room, User $user, array $data): Room
    {
        // Cek otorisasi properti
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $room->property_id)->exists()) {
            throw new DomainException('Anda tidak memiliki akses untuk mengubah kamar pada cabang properti ini.');
        }

        // Cegah perpindahan property_id jika sudah ada booking atau histori
        if (isset($data['property_id']) && (int) $data['property_id'] !== (int) $room->property_id) {
            if ($room->bookings()->exists()) {
                throw new DomainException('Properti kamar tidak dapat diubah karena kamar telah memiliki data riwayat booking.');
            }
        }

        // Validasi keunikan nomor kamar baru
        if (isset($data['room_number']) && trim($data['room_number']) !== $room->room_number) {
            $newNumber = trim($data['room_number']);
            $targetPropertyId = (int) ($data['property_id'] ?? $room->property_id);
            $exists = Room::where('property_id', $targetPropertyId)
                ->where('room_number', $newNumber)
                ->where('id', '!=', $room->id)
                ->exists();

            if ($exists) {
                throw new DomainException("Nomor kamar '{$newNumber}' sudah digunakan pada properti ini.");
            }
        }

        return DB::transaction(function () use ($room, $user, $data) {
            $oldValues = $room->only([
                'room_type_id', 'room_number', 'floor', 'building', 'size_m2',
                'capacity', 'description', 'price_monthly', 'deposit_amount',
                'status', 'is_active', 'internal_notes'
            ]);

            $updatePayload = [];

            if (isset($data['room_type_id'])) $updatePayload['room_type_id'] = (int) $data['room_type_id'];
            if (isset($data['room_number'])) $updatePayload['room_number'] = trim($data['room_number']);
            if (isset($data['floor'])) $updatePayload['floor'] = (int) $data['floor'];
            if (array_key_exists('building_id', $data)) {
                $updatePayload['building_id'] = !empty($data['building_id']) ? (int) $data['building_id'] : null;
                if (!empty($updatePayload['building_id']) && empty($data['building'])) {
                    $b = \App\Models\Building::find($updatePayload['building_id']);
                    if ($b) {
                        $updatePayload['building'] = $b->name;
                    }
                }
            }
            if (array_key_exists('building', $data)) $updatePayload['building'] = !empty($data['building']) ? trim($data['building']) : null;
            if (array_key_exists('size_m2', $data)) $updatePayload['size_m2'] = $data['size_m2'] !== '' && $data['size_m2'] !== null ? (float) $data['size_m2'] : null;
            if (array_key_exists('capacity', $data)) $updatePayload['capacity'] = $data['capacity'] !== '' && $data['capacity'] !== null ? (int) $data['capacity'] : null;
            if (array_key_exists('description', $data)) $updatePayload['description'] = $data['description'];
            if (array_key_exists('facilities', $data)) {
                $updatePayload['facilities'] = is_array($data['facilities']) ? $data['facilities'] : json_decode($data['facilities'], true);
            }
            if (array_key_exists('price_daily', $data)) $updatePayload['price_daily'] = $data['price_daily'] !== '' && $data['price_daily'] !== null ? (float) $data['price_daily'] : null;
            if (array_key_exists('price_monthly', $data)) $updatePayload['price_monthly'] = $data['price_monthly'] !== '' && $data['price_monthly'] !== null ? (float) $data['price_monthly'] : null;
            if (array_key_exists('price_yearly', $data)) $updatePayload['price_yearly'] = $data['price_yearly'] !== '' && $data['price_yearly'] !== null ? (float) $data['price_yearly'] : null;
            if (array_key_exists('deposit_amount', $data)) $updatePayload['deposit_amount'] = $data['deposit_amount'] !== '' && $data['deposit_amount'] !== null ? (float) $data['deposit_amount'] : null;
            if (array_key_exists('down_payment_amount', $data)) $updatePayload['down_payment_amount'] = $data['down_payment_amount'] !== '' && $data['down_payment_amount'] !== null ? (float) $data['down_payment_amount'] : null;
            if (array_key_exists('photos', $data)) {
                $updatePayload['photos'] = is_array($data['photos']) ? $data['photos'] : json_decode($data['photos'], true);
            }
            if (array_key_exists('internal_notes', $data)) $updatePayload['internal_notes'] = $data['internal_notes'];

            // Jika status ingin diubah, periksa validasi ketersediaan
            if (isset($data['status']) && $data['status'] !== $room->status->value) {
                $newStatus = RoomStatus::from($data['status']);
                if ($newStatus === RoomStatus::AVAILABLE && $room->hasActiveBooking()) {
                    throw new DomainException('Status kamar tidak dapat diubah menjadi Tersedia karena ada booking aktif yang menempati kamar.');
                }
                $updatePayload['status'] = $newStatus;
            }

            if (isset($data['is_active'])) {
                $newActive = (bool) $data['is_active'];
                if (!$newActive && $room->hasActiveBooking()) {
                    throw new DomainException('Kamar tidak dapat dinonaktifkan karena sedang dihuni oleh penyewa aktif.');
                }
                $updatePayload['is_active'] = $newActive;
            }

            $room->update($updatePayload);

            $newValues = $room->fresh()->only(array_keys($oldValues));

            // Rekam perubahan jika ada nilai yang berbeda
            RoomChangeHistory::create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'action' => 'updated',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'notes' => $data['change_reason'] ?? 'Pembaruan data spesifikasi/harga master kamar.',
            ]);

            return $room->fresh(['property', 'roomType']);
        });
    }

    /**
     * Aktifkan / Nonaktifkan kamar dengan perlindungan booking aktif.
     */
    public function toggleActive(Room $room, User $user, ?string $reason = null): Room
    {
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $room->property_id)->exists()) {
            throw new DomainException('Anda tidak memiliki hak mengubah status aktif kamar di cabang ini.');
        }

        $targetState = !$room->is_active;

        if (!$targetState) {
            // Menonaktifkan: pastikan tidak sedang ditempati
            if ($room->hasActiveBooking()) {
                throw new DomainException('Kamar tidak dapat dinonaktifkan karena sedang memiliki pemesanan aktif / ditempati.');
            }
            if ($room->status === RoomStatus::OCCUPIED) {
                throw new DomainException('Kamar berstatus Ditempati (Occupied) tidak dapat dinonaktifkan.');
            }
        }

        return DB::transaction(function () use ($room, $user, $targetState, $reason) {
            $room->is_active = $targetState;
            $room->save();

            RoomChangeHistory::create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'action' => $targetState ? 'activated' : 'deactivated',
                'old_values' => ['is_active' => !$targetState],
                'new_values' => ['is_active' => $targetState],
                'notes' => $reason ?: ($targetState ? 'Kamar diaktifkan kembali untuk operasional.' : 'Kamar dinonaktifkan dari operasional.'),
            ]);

            return $room;
        });
    }

    /**
     * Ubah status operasional kamar (Tersedia, Ditempati, Perbaikan, Kotor).
     */
    public function changeStatus(Room $room, User $user, RoomStatus $newStatus, ?string $notes = null): Room
    {
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $room->property_id)->exists()) {
            throw new DomainException('Anda tidak memiliki hak mengubah status kamar di properti ini.');
        }

        if ($newStatus === RoomStatus::AVAILABLE && $room->hasActiveBooking()) {
            throw new DomainException('Kamar tidak dapat diubah menjadi Tersedia karena sedang memiliki booking aktif.');
        }

        return DB::transaction(function () use ($room, $user, $newStatus, $notes) {
            $oldStatus = $room->status;
            $room->status = $newStatus;
            $room->save();

            RoomChangeHistory::create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'action' => 'status_changed',
                'old_values' => ['status' => $oldStatus->value],
                'new_values' => ['status' => $newStatus->value],
                'notes' => $notes ?: "Status kamar diubah dari {$oldStatus->label()} menjadi {$newStatus->label()}.",
            ]);

            return $room;
        });
    }

    /**
     * Hapus kamar jika belum ada relasi historis.
     */
    public function deleteRoom(Room $room, User $user): bool
    {
        if (!$user->isSuperAdmin() && !$user->properties()->where('properties.id', $room->property_id)->exists()) {
            throw new DomainException('Anda tidak memiliki hak menghapus kamar di properti ini.');
        }

        if ($room->bookings()->exists()) {
            throw new DomainException('Kamar tidak dapat dihapus karena memiliki riwayat pemesanan. Gunakan opsi nonaktifkan.');
        }

        if ($room->inspections()->exists()) {
            throw new DomainException('Kamar memiliki riwayat inspeksi dan tidak dapat dihapus.');
        }

        if ($room->maintenanceRequests()->exists()) {
            throw new DomainException('Kamar memiliki riwayat perbaikan (maintenance) dan tidak dapat dihapus.');
        }

        return (bool) $room->delete();
    }
}
