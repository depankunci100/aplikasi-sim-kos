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

        if (in_array($inspection->status, [InspectionStatus::APPROVED_BY_TENANT, InspectionStatus::CANCELLED])) {
            throw new DomainException('Item inspeksi tidak dapat diubah karena inspeksi telah selesai atau dibatalkan.');
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
        if ($inspection->tenant_id && $inspection->tenant_id !== $tenant->id) {
            throw new DomainException('Anda tidak memiliki kewenangan untuk menyetujui inspeksi kamar ini.');
        }

        if ($inspection->status !== InspectionStatus::COMPLETED && $inspection->status !== InspectionStatus::OBJECTED_BY_TENANT) {
            throw new DomainException("Hanya inspeksi berstatus selesai yang dapat disetujui.");
        }

        $inspection->update([
            'status' => InspectionStatus::APPROVED_BY_TENANT->value,
            'tenant_feedback' => $feedback ?? 'Penyewa menyetujui kondisi kamar.',
            'tenant_decision_at' => now(),
        ]);

        $this->logAction($inspection, $tenant, 'tenant_approved', 'Penyewa menyetujui hasil inspeksi kamar.');

        return $inspection->fresh();
    }

    /**
     * Penyewa mengajukan keberatan / tanggapan atas hasil inspeksi
     */
    public function tenantObject(Inspection $inspection, string $objectionNotes, User $tenant): Inspection
    {
        if ($inspection->tenant_id && $inspection->tenant_id !== $tenant->id) {
            throw new DomainException('Anda tidak memiliki kewenangan untuk mengajukan keberatan pada inspeksi ini.');
        }

        if ($inspection->status !== InspectionStatus::COMPLETED) {
            throw new DomainException("Keberatan hanya dapat diajukan pada inspeksi yang telah diselesaikan petugas.");
        }

        $inspection->update([
            'status' => InspectionStatus::OBJECTED_BY_TENANT->value,
            'tenant_feedback' => $objectionNotes,
            'tenant_decision_at' => now(),
        ]);

        $this->logAction($inspection, $tenant, 'tenant_objected', "Penyewa mengajukan keberatan: {$objectionNotes}");

        return $inspection->fresh();
    }

    /**
     * Membandingkan dua inspeksi (misalnya Check-In baseline vs Check-Out)
     */
    public function compareInspections(Inspection $current, Inspection $baseline): array
    {
        $currentItems = $current->items->keyBy('item_name');
        $baselineItems = $baseline->items->keyBy('item_name');

        $differences = [];
        $totalEstimatedCost = 0.00;

        foreach ($currentItems as $name => $curr) {
            $base = $baselineItems->get($name);

            $baseCond = $base ? $base->condition->value : 'not_recorded';
            $currCond = $curr->condition->value;

            $isDegraded = $this->isConditionDegraded($baseCond, $currCond);

            if ($isDegraded || $curr->repair_required || $curr->estimated_cost > 0) {
                $totalEstimatedCost += (float)$curr->estimated_cost;

                $differences[] = [
                    'item_name' => $name,
                    'category' => $curr->category,
                    'baseline_condition' => $baseCond,
                    'current_condition' => $currCond,
                    'is_degraded' => $isDegraded,
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
