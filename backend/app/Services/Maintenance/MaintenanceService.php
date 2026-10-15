<?php

namespace App\Services\Maintenance;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\User;
use App\Services\Notification\NotificationService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MaintenanceService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Otorisasi akses pengguna ke properti tertentu
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

    /**
     * Ambil query tiket maintenance dengan filter & kontrol akses per role
     */
    public function getMaintenanceQuery(User $user, array $filters = []): Builder
    {
        $query = MaintenanceRequest::with([
            'property',
            'room.roomType',
            'roomAsset',
            'complaint',
            'reporter',
            'assignee',
            'verifier',
        ])->latest('id');

        if ($user->isTenant()) {
            // Penyewa hanya melihat tiket yang ia laporkan atau terkait kamar aktifnya
            $userBookingRooms = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->pluck('room_id')
                ->filter();

            $query->where(function (Builder $sub) use ($user, $userBookingRooms) {
                $sub->where('reporter_id', $user->id)
                    ->orWhereIn('room_id', $userBookingRooms);
            });
        } else {
            $accessiblePropertyIds = $this->getAccessiblePropertyIds($user);
            if ($accessiblePropertyIds !== null) {
                $query->whereIn('property_id', $accessiblePropertyIds);
            }

            // Jika staf teknisi biasa, sediakan opsi melihat tugas yang ditugaskan kepadanya
            if ($user->isStaff() && !empty($filters['my_assigned_only'])) {
                $query->where('assigned_to', $user->id);
            }
        }

        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_overdue']) && $filters['is_overdue'] !== '') {
            $query->where('is_overdue', filter_var($filters['is_overdue'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($sub) use ($term) {
                $sub->where('ticket_number', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhereHas('room', fn ($r) => $r->where('room_number', 'like', "%{$term}%"))
                    ->orWhereHas('property', fn ($p) => $p->where('name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    /**
     * Buat tiket maintenance baru
     */
    public function createMaintenance(array $data, ?array $beforePhotos, User $actor): MaintenanceRequest
    {
        $propertyId = (int) $data['property_id'];

        if ($actor->isTenant()) {
            // Penyewa wajib memvalidasi apakah kamar atau properti terkait booking aktifnya
            $hasActiveBooking = Booking::where('user_id', $actor->id)
                ->where('property_id', $propertyId)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->exists();

            if (!$hasActiveBooking) {
                throw new DomainException('Penyewa hanya dapat membuat tiket perbaikan pada properti yang sedang disewa.');
            }

            if (!empty($data['room_id'])) {
                $isRoomRented = Booking::where('user_id', $actor->id)
                    ->where('room_id', $data['room_id'])
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->exists();

                if (!$isRoomRented) {
                    throw new DomainException('Penyewa tidak dapat mengajukan perbaikan untuk kamar milik orang lain.');
                }
            }
        } else {
            $this->authorizePropertyAccess($actor, $propertyId);
        }

        // Validasi konsistensi room & asset
        $roomId = !empty($data['room_id']) ? (int) $data['room_id'] : null;
        if ($roomId) {
            $room = Room::where('property_id', $propertyId)->find($roomId);
            if (!$room) {
                throw new DomainException("Kamar ID {$roomId} tidak ditemukan pada properti ini.");
            }
        }

        $assetId = !empty($data['room_asset_id']) ? (int) $data['room_asset_id'] : null;
        if ($assetId) {
            $asset = RoomAsset::find($assetId);
            if (!$asset || ($roomId && $asset->room_id !== $roomId)) {
                throw new DomainException("Aset ID {$assetId} tidak sesuai dengan kamar yang dipilih.");
            }
        }

        $priorityEnum = MaintenancePriority::tryFrom($data['priority'] ?? 'normal') ?? MaintenancePriority::NORMAL;
        $categoryEnum = MaintenanceCategory::tryFrom($data['category'] ?? 'other') ?? MaintenanceCategory::OTHER;

        $ticketNumber = 'MNT-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $slaDueAt = now()->addHours($priorityEnum->slaHours());

        // Upload foto kondisi awal (before photos) ke storage privat
        $savedPhotos = [];
        if (!empty($beforePhotos)) {
            foreach ($beforePhotos as $file) {
                if ($file instanceof UploadedFile) {
                    $this->validatePhotoFile($file);
                    $path = $file->store("maintenance_photos/{$ticketNumber}/before", 'local');
                    $savedPhotos[] = $path;
                }
            }
        }

        return DB::transaction(function () use (
            $ticketNumber,
            $propertyId,
            $roomId,
            $assetId,
            $data,
            $actor,
            $priorityEnum,
            $categoryEnum,
            $slaDueAt,
            $savedPhotos
        ) {
            $maintenance = MaintenanceRequest::create([
                'ticket_number' => $ticketNumber,
                'property_id' => $propertyId,
                'room_id' => $roomId,
                'room_asset_id' => $assetId,
                'complaint_id' => $data['complaint_id'] ?? null,
                'reporter_id' => $actor->id,
                'assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $categoryEnum,
                'priority' => $priorityEnum,
                'status' => MaintenanceStatus::REPORTED,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'photos_before' => $savedPhotos,
                'sla_due_at' => $slaDueAt,
                'is_overdue' => false,
            ]);

            MaintenanceLog::create([
                'maintenance_request_id' => $maintenance->id,
                'user_id' => $actor->id,
                'action' => 'reported',
                'previous_status' => null,
                'new_status' => MaintenanceStatus::REPORTED->value,
                'notes' => 'Tiket perbaikan diajukan oleh ' . $actor->name,
            ]);

            // Kirim notifikasi internal ke pemilik / pengelola properti
            $managers = DB::table('property_users')
                ->where('property_id', $propertyId)
                ->whereIn('role', ['owner', 'manager'])
                ->pluck('user_id');

            foreach ($managers as $mgrId) {
                $this->notificationService->createNotification(
                    $mgrId,
                    'maintenance_new',
                    "Laporan Perbaikan Baru: {$maintenance->ticket_number}",
                    "Terdapat laporan kerusakan baru: '{$maintenance->title}' dengan prioritas {$priorityEnum->label()}.",
                    ['maintenance_id' => $maintenance->id, 'ticket_number' => $maintenance->ticket_number],
                    $propertyId
                );
            }

            return $maintenance;
        });
    }

    /**
     * Triase tiket maintenance (memperbarui prioritas, kategori, atau jadwal sebelum penugasan)
     */
    public function triageMaintenance(MaintenanceRequest $maintenance, array $data, User $actor): MaintenanceRequest
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak memiliki izin melakukan triase tiket perbaikan.');
        }

        $this->authorizePropertyAccess($actor, $maintenance->property_id);

        if (in_array($maintenance->status, [MaintenanceStatus::COMPLETED, MaintenanceStatus::CANCELLED, MaintenanceStatus::REJECTED])) {
            throw new DomainException("Tiket dengan status {$maintenance->status->label()} tidak dapat ditriase ulang.");
        }

        return DB::transaction(function () use ($maintenance, $data, $actor) {
            $prevStatus = $maintenance->status->value;

            if (!empty($data['priority'])) {
                $pEnum = MaintenancePriority::tryFrom($data['priority']) ?? $maintenance->priority;
                $maintenance->priority = $pEnum;
                $maintenance->sla_due_at = now()->addHours($pEnum->slaHours());
            }

            if (!empty($data['category'])) {
                $maintenance->category = MaintenanceCategory::tryFrom($data['category']) ?? $maintenance->category;
            }

            if (isset($data['scheduled_date'])) {
                $maintenance->scheduled_date = $data['scheduled_date'];
            }

            if ($maintenance->status === MaintenanceStatus::REPORTED) {
                $maintenance->status = MaintenanceStatus::TRIAGED;
            }

            $maintenance->save();

            MaintenanceLog::create([
                'maintenance_request_id' => $maintenance->id,
                'user_id' => $actor->id,
                'action' => 'triaged',
                'previous_status' => $prevStatus,
                'new_status' => $maintenance->status->value,
                'notes' => $data['notes'] ?? ('Tiket ditinjau oleh ' . $actor->name),
            ]);

            return $maintenance;
        });
    }

    /**
     * Tugaskan staf teknisi untuk perbaikan
     */
    public function assignMaintenance(MaintenanceRequest $maintenance, int $assigneeId, ?string $scheduledDate, User $actor): MaintenanceRequest
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat menugaskan teknisi.');
        }

        $this->authorizePropertyAccess($actor, $maintenance->property_id);

        if (in_array($maintenance->status, [MaintenanceStatus::COMPLETED, MaintenanceStatus::CANCELLED, MaintenanceStatus::REJECTED])) {
            throw new DomainException("Tidak dapat menugaskan teknisi pada tiket yang sudah {$maintenance->status->label()}.");
        }

        // Verifikasi bahwa teknisi terhubung ke properti ini
        $isStaffOnProperty = DB::table('property_users')
            ->where('property_id', $maintenance->property_id)
            ->where('user_id', $assigneeId)
            ->exists();

        if (!$isStaffOnProperty) {
            throw new DomainException('Teknisi yang dipilih tidak terdaftar sebagai staf pada properti ini.');
        }

        $assignee = User::findOrFail($assigneeId);

        return DB::transaction(function () use ($maintenance, $assignee, $scheduledDate, $actor) {
            $prevStatus = $maintenance->status->value;

            $maintenance->assigned_to = $assignee->id;
            if ($scheduledDate) {
                $maintenance->scheduled_date = $scheduledDate;
            }
            $maintenance->status = MaintenanceStatus::ASSIGNED;
            $maintenance->save();

            MaintenanceLog::create([
                'maintenance_request_id' => $maintenance->id,
                'user_id' => $actor->id,
                'action' => 'assigned',
                'previous_status' => $prevStatus,
                'new_status' => MaintenanceStatus::ASSIGNED->value,
                'notes' => "Ditugaskan kepada {$assignee->name} oleh {$actor->name}",
            ]);

            // Notifikasi ke teknisi
            $this->notificationService->createNotification(
                $assignee->id,
                'maintenance_assigned',
                "Tugas Perbaikan Baru: {$maintenance->ticket_number}",
                "Anda ditugaskan untuk memperbaiki: '{$maintenance->title}'.",
                ['maintenance_id' => $maintenance->id, 'ticket_number' => $maintenance->ticket_number],
                $maintenance->property_id
            );

            return $maintenance;
        });
    }

    /**
     * Mulai pengerjaan perbaikan
     */
    public function startMaintenance(MaintenanceRequest $maintenance, ?string $diagnosisNotes, User $actor): MaintenanceRequest
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak memiliki izin mengubah status pengerjaan.');
        }

        $this->authorizePropertyAccess($actor, $maintenance->property_id);

        if ($maintenance->status === MaintenanceStatus::IN_PROGRESS) {
            return $maintenance;
        }

        if (!in_array($maintenance->status, [MaintenanceStatus::REPORTED, MaintenanceStatus::TRIAGED, MaintenanceStatus::ASSIGNED])) {
            throw new DomainException("Pekerjaan tidak dapat dimulai dari status {$maintenance->status->label()}.");
        }

        return DB::transaction(function () use ($maintenance, $diagnosisNotes, $actor) {
            $prevStatus = $maintenance->status->value;

            $maintenance->status = MaintenanceStatus::IN_PROGRESS;
            $maintenance->started_at = $maintenance->started_at ?? now();
            if ($diagnosisNotes) {
                $maintenance->diagnosis_notes = $diagnosisNotes;
            }
            if (!$maintenance->assigned_to && $actor->isStaff()) {
                $maintenance->assigned_to = $actor->id;
            }
            $maintenance->save();

            MaintenanceLog::create([
                'maintenance_request_id' => $maintenance->id,
                'user_id' => $actor->id,
                'action' => 'started',
                'previous_status' => $prevStatus,
                'new_status' => MaintenanceStatus::IN_PROGRESS->value,
                'notes' => 'Pengerjaan dimulai oleh ' . $actor->name . ($diagnosisNotes ? ". Diagnosis: {$diagnosisNotes}" : ''),
            ]);

            return $maintenance;
        });
    }

    /**
     * Teknisi menyerahkan hasil perbaikan untuk diverifikasi
     */
    public function submitForVerification(
        MaintenanceRequest $maintenance,
        array $data,
        ?array $afterPhotos,
        User $actor
    ): MaintenanceRequest {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat menyerahkan hasil perbaikan.');
        }

        $this->authorizePropertyAccess($actor, $maintenance->property_id);

        if ($maintenance->status !== MaintenanceStatus::IN_PROGRESS) {
            throw new DomainException("Hanya tiket berstatus 'Sedang Dikerjakan' yang dapat diserahkan untuk verifikasi. Status saat ini: {$maintenance->status->label()}.");
        }

        if (empty(trim($data['action_taken'] ?? ''))) {
            throw new DomainException('Tindakan perbaikan yang telah dilakukan wajib diisi.');
        }

        $laborCost = round((float) ($data['labor_cost'] ?? 0), 2);
        $materialCost = round((float) ($data['material_cost'] ?? 0), 2);
        $totalCost = round($laborCost + $materialCost, 2);

        $savedAfterPhotos = $maintenance->photos_after ?? [];
        if (!empty($afterPhotos)) {
            foreach ($afterPhotos as $file) {
                if ($file instanceof UploadedFile) {
                    $this->validatePhotoFile($file);
                    $path = $file->store("maintenance_photos/{$maintenance->ticket_number}/after", 'local');
                    $savedAfterPhotos[] = $path;
                }
            }
        }

        return DB::transaction(function () use (
            $maintenance,
            $data,
            $laborCost,
            $materialCost,
            $totalCost,
            $savedAfterPhotos,
            $actor
        ) {
            $prevStatus = $maintenance->status->value;

            $maintenance->update([
                'status' => MaintenanceStatus::PENDING_VERIFICATION,
                'action_taken' => $data['action_taken'],
                'diagnosis_notes' => $data['diagnosis_notes'] ?? $maintenance->diagnosis_notes,
                'spare_parts' => !empty($data['spare_parts']) ? (is_array($data['spare_parts']) ? $data['spare_parts'] : json_decode($data['spare_parts'], true)) : $maintenance->spare_parts,
                'labor_cost' => number_format($laborCost, 2, '.', ''),
                'material_cost' => number_format($materialCost, 2, '.', ''),
                'total_cost' => number_format($totalCost, 2, '.', ''),
                'photos_after' => $savedAfterPhotos,
                'completed_at' => now(),
            ]);

            MaintenanceLog::create([
                'maintenance_request_id' => $maintenance->id,
                'user_id' => $actor->id,
                'action' => 'submitted_verification',
                'previous_status' => $prevStatus,
                'new_status' => MaintenanceStatus::PENDING_VERIFICATION->value,
                'notes' => 'Perbaikan diselesaikan oleh teknisi dan menunggu verifikasi. Biaya total: Rp ' . number_format($totalCost, 0, ',', '.'),
            ]);

            // Kirim notifikasi ke pelapor dan manajer
            $this->notificationService->createNotification(
                $maintenance->reporter_id,
                'maintenance_pending_verification',
                "Perbaikan Selesai Dikerjakan: {$maintenance->ticket_number}",
                "Pekerjaan perbaikan '{$maintenance->title}' telah selesai dikerjakan dan siap diverifikasi.",
                ['maintenance_id' => $maintenance->id, 'ticket_number' => $maintenance->ticket_number],
                $maintenance->property_id
            );

            return $maintenance;
        });
    }

    /**
     * Verifikasi dan tandai pekerjaan selesai
     */
    public function verifyAndComplete(
        MaintenanceRequest $maintenance,
        string $verificationNotes,
        User $actor
    ): MaintenanceRequest {
        $this->authorizePropertyAccess($actor, $maintenance->property_id);

        if ($maintenance->status !== MaintenanceStatus::PENDING_VERIFICATION) {
            throw new DomainException("Hanya tiket berstatus 'Menunggu Verifikasi' yang dapat diverifikasi selesai. Status saat ini: {$maintenance->status->label()}.");
        }

        // Siapa yang boleh verifikasi? Pemilik, Manajer, Super Admin, atau pelapor tiket
        $canVerify = $actor->isSuperAdmin() || $actor->isOwner() || $actor->isManager() || ($actor->id === $maintenance->reporter_id);
        if (!$canVerify) {
            throw new DomainException('Hanya Pemilik, Manajer, atau Pelapor tiket yang berwenang memverifikasi penyelesaian perbaikan.');
        }

        return DB::transaction(function () use ($maintenance, $verificationNotes, $actor) {
            $prevStatus = $maintenance->status->value;

            $maintenance->update([
                'status' => MaintenanceStatus::COMPLETED,
                'verified_at' => now(),
                'verified_by' => $actor->id,
                'verification_notes' => $verificationNotes,
            ]);

            MaintenanceLog::create([
                'maintenance_request_id' => $maintenance->id,
                'user_id' => $actor->id,
                'action' => 'completed',
                'previous_status' => $prevStatus,
                'new_status' => MaintenanceStatus::COMPLETED->value,
                'notes' => "Hasil perbaikan diverifikasi dan disetujui selesai oleh {$actor->name}. Catatan: {$verificationNotes}",
            ]);

            // Jika tiket ini terhubung dengan keluhan (complaint), update keluhan bila relevan
            if ($maintenance->complaint_id) {
                $complaint = Complaint::find($maintenance->complaint_id);
                if ($complaint && $complaint->status === \App\Enums\ComplaintStatus::IN_PROGRESS) {
                    $complaint->update([
                        'status' => \App\Enums\ComplaintStatus::RESOLVED,
                        'resolved_at' => now(),
                        'resolution_notes' => "Terselesaikan melalui perbaikan tiket {$maintenance->ticket_number}.",
                    ]);
                }
            }

            // Notifikasi ke teknisi yang mengerjakan
            if ($maintenance->assigned_to) {
                $this->notificationService->createNotification(
                    $maintenance->assigned_to,
                    'maintenance_verified',
                    "Perbaikan Diverifikasi: {$maintenance->ticket_number}",
                    "Pekerjaan perbaikan '{$maintenance->title}' telah diverifikasi selesai oleh {$actor->name}.",
                    ['maintenance_id' => $maintenance->id, 'ticket_number' => $maintenance->ticket_number],
                    $maintenance->property_id
                );
            }

            return $maintenance;
        });
    }

    /**
     * Tolak atau batalkan tiket perbaikan dengan alasan wajib
     */
    public function rejectOrCancel(
        MaintenanceRequest $maintenance,
        string $action, // 'reject' atau 'cancel'
        string $reason,
        User $actor
    ): MaintenanceRequest {
        $this->authorizePropertyAccess($actor, $maintenance->property_id);

        if (empty(trim($reason))) {
            throw new DomainException('Alasan penolakan / pembatalan wajib diisi.');
        }

        if (in_array($maintenance->status, [MaintenanceStatus::COMPLETED, MaintenanceStatus::CANCELLED, MaintenanceStatus::REJECTED])) {
            throw new DomainException("Tiket dengan status {$maintenance->status->label()} tidak dapat dibatalkan atau ditolak lagi.");
        }

        $newStatus = ($action === 'reject') ? MaintenanceStatus::REJECTED : MaintenanceStatus::CANCELLED;

        return DB::transaction(function () use ($maintenance, $newStatus, $action, $reason, $actor) {
            $prevStatus = $maintenance->status->value;

            $updateData = ['status' => $newStatus];
            if ($action === 'reject') {
                $updateData['rejection_reason'] = $reason;
            } else {
                $updateData['cancellation_reason'] = $reason;
            }

            $maintenance->update($updateData);

            MaintenanceLog::create([
                'maintenance_request_id' => $maintenance->id,
                'user_id' => $actor->id,
                'action' => $newStatus->value,
                'previous_status' => $prevStatus,
                'new_status' => $newStatus->value,
                'notes' => ($action === 'reject' ? 'Tiket ditolak' : 'Tiket dibatalkan') . " oleh {$actor->name}. Alasan: {$reason}",
            ]);

            // Kirim notifikasi ke pelapor
            $this->notificationService->createNotification(
                $maintenance->reporter_id,
                'maintenance_' . $newStatus->value,
                "Tiket Perbaikan {$newStatus->label()}: {$maintenance->ticket_number}",
                "Tiket '{$maintenance->title}' telah {$newStatus->label()}. Alasan: {$reason}",
                ['maintenance_id' => $maintenance->id, 'ticket_number' => $maintenance->ticket_number],
                $maintenance->property_id
            );

            return $maintenance;
        });
    }

    /**
     * Dapatkan path fisik privat foto tiket perbaikan dengan validasi otorisasi
     */
    public function getAuthorizedPhotoPath(MaintenanceRequest $maintenance, string $filePath, User $user): string
    {
        // 1. Cek otorisasi properti / tenant
        if ($user->isTenant()) {
            $isAuthorized = ($user->id === $maintenance->reporter_id);
            if (!$isAuthorized && $maintenance->room_id) {
                $isAuthorized = Booking::where('user_id', $user->id)
                    ->where('room_id', $maintenance->room_id)
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->exists();
            }
            if (!$isAuthorized) {
                throw new DomainException('Anda tidak berhak melihat foto perbaikan ini.');
            }
        } else {
            $this->authorizePropertyAccess($user, $maintenance->property_id);
        }

        // 2. Pastikan file terdaftar di photos_before atau photos_after tiket
        $allPhotos = array_merge($maintenance->photos_before ?? [], $maintenance->photos_after ?? []);
        if (!in_array($filePath, $allPhotos)) {
            throw new DomainException('Berkas foto tidak terdaftar pada tiket ini.');
        }

        if (!Storage::disk('local')->exists($filePath)) {
            throw new DomainException('Berkas foto tidak ditemukan pada penyimpanan server.');
        }

        return Storage::disk('local')->path($filePath);
    }

    /**
     * Periksa tiket aktif yang melewati batas SLA dan tandai sebagai terlambat
     */
    public function flagOverdueTickets(): int
    {
        return MaintenanceRequest::where('is_overdue', false)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', [MaintenanceStatus::COMPLETED->value, MaintenanceStatus::CANCELLED->value, MaintenanceStatus::REJECTED->value])
            ->update(['is_overdue' => true]);
    }

    protected function validatePhotoFile(UploadedFile $file): void
    {
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed)) {
            throw new DomainException('File foto harus berformat JPG, PNG, atau WebP.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new DomainException('Ukuran foto tidak boleh melebihi 5 MB.');
        }
    }
}
