<?php

namespace App\Services\Inspection;

use App\Enums\InspectionItemCondition;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Booking;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionLog;
use App\Models\InspectionMedia;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InspectionService
{
    /**
     * Standard default checklist template when room assets are not populated
     */
    public const DEFAULT_CHECKLIST_ITEMS = [
        ['item_name' => 'Kasur, Dipan & Bantal', 'category' => 'furniture'],
        ['item_name' => 'Meja & Kursi Belajar', 'category' => 'furniture'],
        ['item_name' => 'Lemari Pakaian & Gantungan', 'category' => 'furniture'],
        ['item_name' => 'Unit AC & Remote Kontrol', 'category' => 'electronics'],
        ['item_name' => 'Lampu Kamar & Saklar Listrik', 'category' => 'electronics'],
        ['item_name' => 'Kloset, Kran Air & Shower', 'category' => 'bathroom'],
        ['item_name' => 'Dinding, Lantai Keramik & Plafon', 'category' => 'structure'],
        ['item_name' => 'Jendela, Kaca & Gorden', 'category' => 'structure'],
        ['item_name' => 'Kunci Kamar & Handle Pintu', 'category' => 'keys_access'],
        ['item_name' => 'Kebersihan Keseluruhan Ruangan', 'category' => 'cleanliness'],
    ];

    /**
     * Jadwalkan inspeksi kamar baru
     */
    public function createInspection(array $data, User $creator): Inspection
    {
        $property = Property::findOrFail($data['property_id']);
        $room = Room::where('property_id', $property->id)->findOrFail($data['room_id']);

        $booking = null;
        if (!empty($data['booking_id'])) {
            $booking = Booking::where('room_id', $room->id)->find($data['booking_id']);
        }

        $tenantId = $data['tenant_id'] ?? $booking?->user_id;

        return DB::transaction(function () use ($data, $creator, $property, $room, $booking, $tenantId) {
            $inspection = Inspection::create([
                'inspection_code' => Inspection::generateCode(),
                'property_id' => $property->id,
                'room_id' => $room->id,
                'booking_id' => $booking?->id,
                'inspector_id' => $data['inspector_id'] ?? $creator->id,
                'tenant_id' => $tenantId,
                'created_by' => $creator->id,
                'baseline_inspection_id' => $data['baseline_inspection_id'] ?? null,
                'type' => $data['type'] ?? InspectionType::CHECK_IN->value,
                'status' => InspectionStatus::SCHEDULED->value,
                'scheduled_date' => $data['scheduled_date'] ?? now()->toDateString(),
                'inspector_notes' => $data['inspector_notes'] ?? null,
                'tenant_due_at' => now()->addDays(2),
            ]);

            // Auto-populate checklist dari Room Assets atau template standar
            $roomAssets = RoomAsset::where('room_id', $room->id)->get();

            if ($roomAssets->isNotEmpty()) {
                foreach ($roomAssets as $asset) {
                    InspectionItem::create([
                        'inspection_id' => $inspection->id,
                        'room_asset_id' => $asset->id,
                        'item_name' => $asset->item_name,
                        'category' => 'furniture',
                        'condition' => InspectionItemCondition::GOOD->value,
                        'notes' => $asset->notes,
                    ]);
                }
            } else {
                foreach (self::DEFAULT_CHECKLIST_ITEMS as $tpl) {
                    InspectionItem::create([
                        'inspection_id' => $inspection->id,
                        'item_name' => $tpl['item_name'],
                        'category' => $tpl['category'],
                        'condition' => InspectionItemCondition::GOOD->value,
                    ]);
                }
            }

            $this->logAction($inspection, $creator, 'created', 'Jadwal inspeksi berhasil dibuat.');

            return $inspection->load(['room', 'property', 'inspector', 'tenant', 'items']);
        });
    }

    /**
     * Memulai pelaksanaan inspeksi di lapangan
     */
    public function startInspection(Inspection $inspection, User $user): Inspection
    {
        if ($inspection->status !== InspectionStatus::SCHEDULED) {
            throw new DomainException("Inspeksi tidak dapat dimulai karena berstatus '{$inspection->status->label()}'.");
        }

        $inspection->update([
            'status' => InspectionStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'inspector_id' => $inspection->inspector_id ?? $user->id,
        ]);

        $this->logAction($inspection, $user, 'started', 'Petugas mulai melakukan pemeriksaan fisik kamar.');

        return $inspection->fresh(['items', 'media']);
    }

    /**
     * Memperbarui kondisi dan catatan item checklist
     */
    public function updateItem(InspectionItem $item, array $data, User $user): InspectionItem
    {
        $inspection = $item->inspection;

        if (in_array($inspection->status, [InspectionStatus::COMPLETED, InspectionStatus::APPROVED_BY_TENANT, InspectionStatus::CANCELLED])) {
            throw new DomainException('Item inspeksi tidak dapat diubah karena inspeksi telah selesai, disetujui, atau dibatalkan.');
        }

        $item->update([
            'condition' => $data['condition'] ?? $item->condition->value,
            'notes' => $data['notes'] ?? $item->notes,
            'repair_required' => $data['repair_required'] ?? $item->repair_required,
            'estimated_cost' => $data['estimated_cost'] ?? $item->estimated_cost,
        ]);

        $this->logAction($inspection, $user, 'item_updated', "Item '{$item->item_name}' diperbarui ke kondisi '{$item->condition->label()}'.");

        return $item;
    }

    /**
     * Mengunggah bukti foto / media inspeksi secara privat
     */
    public function addMedia(Inspection $inspection, UploadedFile $file, ?int $itemId, ?string $caption, User $user): InspectionMedia
    {
        if ($itemId !== null) {
            $item = InspectionItem::where('inspection_id', $inspection->id)->find($itemId);
            if (!$item) {
                throw new DomainException('Item checklist tidak ditemukan pada inspeksi ini.');
            }
        }

        $path = $file->store("inspections/{$inspection->id}", 'local');

        $media = InspectionMedia::create([
            'inspection_id' => $inspection->id,
            'inspection_item_id' => $itemId,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'caption' => $caption,
            'media_type' => 'photo',
        ]);

        $this->logAction($inspection, $user, 'photo_uploaded', "Foto bukti '{$file->getClientOriginalName()}' berhasil diunggah.");

        return $media;
    }

    /**
     * Menyelesaikan inspeksi dan mengirim ke penyewa untuk verifikasi
     */
    public function completeInspection(Inspection $inspection, array $summaryData, User $user): Inspection
    {
        if ($inspection->status === InspectionStatus::CANCELLED) {
            throw new DomainException('Inspeksi yang dibatalkan tidak dapat diselesaikan.');
        }

        $inspection->update([
            'status' => InspectionStatus::COMPLETED->value,
            'completed_at' => now(),
            'overall_condition' => $summaryData['overall_condition'] ?? 'good',
            'inspector_notes' => $summaryData['inspector_notes'] ?? $inspection->inspector_notes,
            'tenant_due_at' => now()->addDays(2),
        ]);

        $this->logAction($inspection, $user, 'completed', 'Inspeksi telah diselesaikan dan menunggu konfirmasi penyewa.');

        return $inspection->fresh(['items', 'media', 'tenant', 'inspector']);
    }

    /**
     * Penyewa menyetujui hasil inspeksi kamar
     */
    public function tenantApprove(Inspection $inspection, ?string $feedback, User $tenant): Inspection
    {
        return DB::transaction(function () use ($inspection, $feedback, $tenant) {
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            if (!$locked->tenant_id || $locked->tenant_id !== $tenant->id) {
                throw new DomainException('Anda tidak memiliki kewenangan untuk menyetujui inspeksi kamar ini.');
            }

            if ($locked->status !== InspectionStatus::COMPLETED && $locked->status !== InspectionStatus::OBJECTED_BY_TENANT) {
                throw new DomainException("Hanya inspeksi berstatus selesai yang dapat disetujui.");
            }

            $locked->update([
                'status' => InspectionStatus::APPROVED_BY_TENANT->value,
                'tenant_feedback' => $feedback ?? 'Penyewa menyetujui kondisi kamar.',
                'tenant_decision_at' => now(),
            ]);

            $this->logAction($locked, $tenant, 'tenant_approved', 'Penyewa menyetujui hasil inspeksi kamar.');

            return $locked->fresh();
        });
    }

    /**
     * Penyewa mengajukan keberatan / tanggapan atas hasil inspeksi
     */
    public function tenantObject(Inspection $inspection, string $objectionNotes, User $tenant, ?array $problematicItems = null): Inspection
    {
        return DB::transaction(function () use ($inspection, $objectionNotes, $tenant, $problematicItems) {
            $locked = Inspection::lockForUpdate()->findOrFail($inspection->id);

            if (!$locked->tenant_id || $locked->tenant_id !== $tenant->id) {
                throw new DomainException('Anda tidak memiliki kewenangan untuk mengajukan keberatan pada inspeksi ini.');
            }

            if ($locked->status !== InspectionStatus::COMPLETED) {
                throw new DomainException("Keberatan hanya dapat diajukan pada inspeksi yang telah diselesaikan petugas.");
            }

            $feedbackText = $objectionNotes;
            if (!empty($problematicItems)) {
                $feedbackText .= ' [Item bermasalah: ' . implode(', ', $problematicItems) . ']';
            }

            $locked->update([
                'status' => InspectionStatus::OBJECTED_BY_TENANT->value,
                'tenant_feedback' => $feedbackText,
                'tenant_decision_at' => now(),
            ]);

            $this->logAction($locked, $tenant, 'tenant_objected', "Penyewa mengajukan keberatan: {$feedbackText}", [
                'problematic_items' => $problematicItems,
            ]);

            // Beri notifikasi ke staf dan manajer
            $notificationService = app(\App\Services\Notification\NotificationService::class);
            $supervisors = DB::table('property_users')
                ->where('property_id', $locked->property_id)
                ->whereIn('role', ['owner', 'manager'])
                ->pluck('user_id');

            foreach ($supervisors as $supId) {
                $notificationService->createNotification(
                    user: $supId,
                    type: 'inspection_objected',
                    title: "Keberatan Hasil Inspeksi: {$locked->inspection_code}",
                    message: "Penyewa {$tenant->name} mengajukan keberatan: {$objectionNotes}",
                    data: ['inspection_id' => $locked->id],
                    propertyId: $locked->property_id
                );
            }

            return $locked->fresh();
        });
    }

    /**
     * Tugaskan inspektor ke pemeriksaan kamar
     */
    public function assignInspector(Inspection $inspection, int $inspectorId, User $actor): Inspection
    {
        if ($actor->isTenant() || $actor->isCashier()) {
            throw new DomainException('Penyewa atau kasir tidak memiliki izin menugaskan inspektor.');
        }

        if (!$actor->isSuperAdmin()) {
            $isAuthorized = DB::table('property_users')
                ->where('property_id', $inspection->property_id)
                ->where('user_id', $actor->id)
                ->whereIn('role', ['owner', 'manager', 'receptionist'])
                ->exists();

            if (!$isAuthorized) {
                throw new DomainException('Anda tidak memiliki wewenang menugaskan inspektor pada properti ini.');
            }
        }

        $inspector = User::findOrFail($inspectorId);

        return DB::transaction(function () use ($inspection, $inspector, $actor) {
            $prevInspector = $inspection->inspector_id;
            $inspection->update([
                'inspector_id' => $inspector->id,
            ]);

            $this->logAction($inspection, $actor, 'assigned', "Inspektor ditugaskan ke {$inspector->name} oleh {$actor->name}.", [
                'previous_inspector_id' => $prevInspector,
                'new_inspector_id' => $inspector->id,
            ]);

            $notificationService = app(\App\Services\Notification\NotificationService::class);
            $notificationService->createNotification(
                user: $inspector->id,
                type: 'inspection_assigned',
                title: "Penugasan Inspeksi Kamar",
                message: "Anda ditugaskan melakukan inspeksi kamar ({$inspection->inspection_code}) pada tanggal {$inspection->scheduled_date->format('Y-m-d')}.",
                data: [
                    'inspection_id' => $inspection->id,
                    'inspection_code' => $inspection->inspection_code,
                    'action_url' => "/inspections/{$inspection->id}",
                ],
                propertyId: $inspection->property_id
            );

            return $inspection->fresh(['room', 'property', 'inspector', 'tenant']);
        });
    }

    /**
     * Jadwalkan ulang tanggal inspeksi kamar
     */
    public function rescheduleInspection(Inspection $inspection, string $newDate, ?string $reason, User $actor): Inspection
    {
        if ($actor->isTenant() || $actor->isCashier()) {
            throw new DomainException('Hanya Pemilik atau Manajer yang dapat menjadwalkan ulang inspeksi.');
        }

        if (!$actor->isSuperAdmin()) {
            $isAuthorized = DB::table('property_users')
                ->where('property_id', $inspection->property_id)
                ->where('user_id', $actor->id)
                ->whereIn('role', ['owner', 'manager', 'receptionist'])
                ->exists();

            if (!$isAuthorized) {
                throw new DomainException('Anda tidak memiliki wewenang mengubah jadwal pada properti ini.');
            }
        }

        if (in_array($inspection->status, [InspectionStatus::COMPLETED, InspectionStatus::APPROVED_BY_TENANT, InspectionStatus::CANCELLED])) {
            throw new DomainException("Inspeksi dengan status {$inspection->status->label()} tidak dapat dijadwalkan ulang.");
        }

        return DB::transaction(function () use ($inspection, $newDate, $reason, $actor) {
            $oldDate = $inspection->scheduled_date ? $inspection->scheduled_date->format('Y-m-d') : '-';
            $inspection->update([
                'scheduled_date' => $newDate,
            ]);

            $this->logAction($inspection, $actor, 'rescheduled', "Jadwal inspeksi diubah dari {$oldDate} ke {$newDate} oleh {$actor->name}. Catatan: " . ($reason ?? '-'));

            return $inspection->fresh();
        });
    }

    /**
     * Membandingkan dua inspeksi (misalnya Check-In baseline vs Check-Out)
     */
    public function compareInspections(Inspection $current, Inspection $baseline): array
    {
        if ($current->room_id !== $baseline->room_id || $current->property_id !== $baseline->property_id) {
            throw new DomainException('Inspeksi baseline harus berasal dari kamar dan properti yang sama.');
        }

        $currentItems = $current->items->keyBy('item_name');
        $baselineItems = $baseline->items->keyBy('item_name');

        $differences = [];
        $totalEstimatedCost = 0.00;

        foreach ($currentItems as $name => $curr) {
            $base = $baselineItems->get($name);

            $baseCond = $base ? $base->condition->value : 'not_recorded';
            $currCond = $curr->condition->value;

            $isDegraded = $this->isConditionDegraded($baseCond, $currCond);
            $isWearAndTear = ($baseCond === 'good' && $currCond === 'fair' && !$curr->repair_required);

            if ($isDegraded || $curr->repair_required || $curr->estimated_cost > 0) {
                // Biaya hanya dikalkulasi jika ada perbaikan yang dibutuhkan atau kerusakan nyata (bukan sekadar pemakaian wajar)
                $itemCost = ($curr->repair_required || in_array($currCond, ['poor', 'damaged', 'missing']))
                    ? (float)$curr->estimated_cost
                    : 0.00;

                $totalEstimatedCost += $itemCost;

                $differences[] = [
                    'item_name' => $name,
                    'category' => $curr->category,
                    'baseline_condition' => $baseCond,
                    'current_condition' => $currCond,
                    'is_degraded' => $isDegraded,
                    'is_wear_and_tear' => $isWearAndTear,
                    'repair_required' => $curr->repair_required,
                    'estimated_cost' => (float)$curr->estimated_cost,
                    'notes' => $curr->notes,
                ];
            }
        }

        return [
            'current_inspection' => [
                'id' => $current->id,
                'code' => $current->inspection_code,
                'type' => $current->type->value,
                'date' => $current->scheduled_date->toDateString(),
            ],
            'baseline_inspection' => [
                'id' => $baseline->id,
                'code' => $baseline->inspection_code,
                'type' => $baseline->type->value,
                'date' => $baseline->scheduled_date->toDateString(),
            ],
            'differences' => $differences,
            'total_damage_cost' => $totalEstimatedCost,
            'has_degradation' => !empty($differences),
            'financial_notice' => 'Hasil perbandingan ini merupakan temuan estimasi teknis dan tidak otomatis memotong saldo deposit atau menerbitkan invoice tagihan.',
        ];
    }

    /**
     * Pengecekan degradasi kondisi fisik
     */
    protected function isConditionDegraded(string $baseline, string $current): bool
    {
        $rank = [
            'good' => 4,
            'fair' => 3,
            'poor' => 2,
            'damaged' => 1,
            'missing' => 0,
        ];

        $baseScore = $rank[$baseline] ?? 4;
        $currScore = $rank[$current] ?? 4;

        return $currScore < $baseScore;
    }

    /**
     * Periksa otorisasi akses membaca inspeksi
     */
    public function canAccessInspection(Inspection $inspection, User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isTenant()) {
            return $inspection->tenant_id === $user->id;
        }

        // Staf cabang (Owner, Manager, Staff, Receptionist)
        return DB::table('property_users')
            ->where('property_id', $inspection->property_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Periksa otorisasi mengelola/memulai/melakukan inspeksi
     */
    public function canExecuteInspection(Inspection $inspection, User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isTenant()) {
            return false;
        }

        // Petugas yang ditugaskan atau manager/owner properti
        if ($inspection->inspector_id === $user->id) {
            return true;
        }

        return DB::table('property_users')
            ->where('property_id', $inspection->property_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->exists();
    }

    /**
     * Pengajuan Inspeksi Kamar oleh Penyewa (Tenant Request)
     */
    public function requestInspection(User $tenant, array $data, array $photos = []): Inspection
    {
        if (!$tenant->isTenant()) {
            throw new DomainException('Hanya penyewa yang dapat mengajukan permohonan inspeksi kamar melalui menu ini.');
        }

        // Cari booking aktif penyewa
        $booking = Booking::with(['room.roomType', 'property'])
            ->where(function ($q) use ($tenant) {
                $q->where('user_id', $tenant->id)
                  ->orWhere('guest_email', $tenant->email);
            })
            ->whereIn('status', ['confirmed', 'active', 'checked_in'])
            ->latest('id')
            ->first();

        if (!$booking || !$booking->room_id) {
            throw new DomainException('Anda tidak memiliki kamar aktif yang terdaftar untuk mengajukan inspeksi.');
        }

        $propertyId = $booking->property_id;
        $roomId = $booking->room_id;

        // Cegah duplikasi pengajuan inspeksi aktif yang belum selesai
        $existingActive = Inspection::where('room_id', $roomId)
            ->whereIn('status', [
                InspectionStatus::SCHEDULED,
                InspectionStatus::IN_PROGRESS,
                InspectionStatus::COMPLETED,
            ])
            ->exists();

        if ($existingActive) {
            throw new DomainException('Sudah ada inspeksi aktif atau menunggu respons penyewa untuk kamar Anda. Harap selesaikan inspeksi sebelumnya terlebih dahulu.');
        }

        $typeVal = $data['type'] ?? 'periodic';
        $typeEnum = InspectionType::tryFrom($typeVal) ?? InspectionType::PERIODIC;
        $scheduledDate = $data['scheduled_date'] ?? now()->toDateString();
        $reason = $data['reason'] ?? 'Pemeriksaan Rutin/Kondisi Kamar';
        $description = $data['description'] ?? '';

        return DB::transaction(function () use (
            $tenant,
            $booking,
            $propertyId,
            $roomId,
            $typeEnum,
            $scheduledDate,
            $reason,
            $description,
            $photos
        ) {
            $inspection = Inspection::create([
                'inspection_code' => Inspection::generateCode(),
                'property_id' => $propertyId,
                'room_id' => $roomId,
                'booking_id' => $booking->id,
                'inspector_id' => null,
                'tenant_id' => $tenant->id,
                'created_by' => $tenant->id,
                'type' => $typeEnum,
                'status' => InspectionStatus::SCHEDULED,
                'scheduled_date' => $scheduledDate,
                'inspector_notes' => "[Pengajuan Penyewa] Alasan: {$reason}. Deskripsi: {$description}",
            ]);

            // Inisialisasi item checklist dari room assets atau default template
            $roomAssets = RoomAsset::where('room_id', $roomId)->get();
            if ($roomAssets->isNotEmpty()) {
                foreach ($roomAssets as $asset) {
                    InspectionItem::create([
                        'inspection_id' => $inspection->id,
                        'room_asset_id' => $asset->id,
                        'item_name' => $asset->name,
                        'category' => $asset->category ?? 'general',
                        'condition' => InspectionItemCondition::GOOD,
                        'is_acceptable' => true,
                    ]);
                }
            } else {
                foreach (self::DEFAULT_CHECKLIST_ITEMS as $item) {
                    InspectionItem::create([
                        'inspection_id' => $inspection->id,
                        'item_name' => $item['item_name'],
                        'category' => $item['category'],
                        'condition' => InspectionItemCondition::GOOD,
                        'is_acceptable' => true,
                    ]);
                }
            }

            // Simpan foto pendukung penyewa di private storage jika dilampirkan
            foreach ($photos as $photo) {
                if ($photo instanceof UploadedFile && $photo->isValid()) {
                    $path = $photo->store("inspections/{$inspection->id}", 'local');
                    InspectionMedia::create([
                        'inspection_id' => $inspection->id,
                        'file_path' => $path,
                        'file_type' => 'photo',
                        'caption' => 'Foto Lampiran Pengajuan Penyewa',
                        'uploaded_by' => $tenant->id,
                    ]);
                }
            }

            $this->logAction(
                $inspection,
                $tenant,
                'requested',
                "Penyewa mengajukan inspeksi kamar ({$typeEnum->label()}). Alasan: {$reason}. Catatan: {$description}"
            );

            // Beri notifikasi in-app kepada staf & manajer properti
            $staffUserIds = DB::table('property_users')
                ->where('property_id', $propertyId)
                ->pluck('user_id');

            $notificationService = app(\App\Services\Notification\NotificationService::class);
            $roomNumber = $booking->room->room_number ?? "Kamar {$roomId}";

            foreach ($staffUserIds as $staffId) {
                $notificationService->createNotification(
                    user: $staffId,
                    type: 'inspection_requested',
                    title: "Pengajuan Inspeksi: Kamar {$roomNumber}",
                    message: "Penyewa {$tenant->name} mengajukan inspeksi {$typeEnum->label()} untuk tanggal {$scheduledDate}.",
                    data: [
                        'inspection_id' => $inspection->id,
                        'inspection_code' => $inspection->inspection_code,
                        'action_url' => "/inspections/{$inspection->id}",
                    ],
                    propertyId: $propertyId
                );
            }

            return $inspection;
        });
    }

    /**
     * Catat riwayat audit log
     */
    protected function logAction(Inspection $inspection, User $user, string $action, string $description, ?array $metadata = null): InspectionLog
    {
        return InspectionLog::create([
            'inspection_id' => $inspection->id,
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
