<?php

namespace App\Services\Housekeeping;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingType;
use App\Models\Booking;
use App\Models\HousekeepingLog;
use App\Models\HousekeepingTask;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Notification\NotificationService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HousekeepingService
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
     * Query tugas housekeeping dengan kontrol akses per role
     */
    public function getTasksQuery(User $user, array $filters = []): Builder
    {
        $query = HousekeepingTask::with([
            'property',
            'room.roomType',
            'assignee',
            'assigner',
            'verifier',
        ])->latest('scheduled_date')->latest('id');

        if ($user->isTenant()) {
            // Penyewa hanya melihat jadwal kebersihan kamar miliknya
            $activeRoomIds = Booking::where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                ->pluck('room_id')
                ->filter();

            $query->whereIn('room_id', $activeRoomIds);
        } else {
            $accessiblePropertyIds = $this->getAccessiblePropertyIds($user);
            if ($accessiblePropertyIds !== null) {
                $query->whereIn('property_id', $accessiblePropertyIds);
            }

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

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['scheduled_date'])) {
            $query->where('scheduled_date', $filters['scheduled_date']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($sub) use ($term) {
                $sub->where('task_number', 'like', "%{$term}%")
                    ->orWhere('area_name', 'like', "%{$term}%")
                    ->orWhereHas('room', fn ($r) => $r->where('room_number', 'like', "%{$term}%"))
                    ->orWhereHas('assignee', fn ($u) => $u->where('name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    /**
     * Buat jadwal / tugas housekeeping baru
     */
    public function createTask(array $data, User $actor): HousekeepingTask
    {
        if ($actor->isTenant() || $actor->isCashier()) {
            throw new DomainException('Kasir atau penyewa tidak dapat membuat jadwal tugas housekeeping.');
        }

        $propertyId = (int) $data['property_id'];
        $this->authorizePropertyAccess($actor, $propertyId);

        $roomId = !empty($data['room_id']) ? (int) $data['room_id'] : null;
        if ($roomId) {
            $room = Room::where('property_id', $propertyId)->find($roomId);
            if (!$room) {
                throw new DomainException("Kamar ID {$roomId} tidak ditemukan pada properti ini.");
            }
        }

        $scheduledDate = $data['scheduled_date'] ?? now()->toDateString();

        // Pencegahan Tugas Duplikat Aktif:
        // Cek apakah sudah ada tugas aktif (scheduled/assigned/in_progress) untuk kamar yang sama di tanggal yang sama
        if ($roomId && empty($data['allow_duplicate'])) {
            $existingActive = HousekeepingTask::where('room_id', $roomId)
                ->whereDate('scheduled_date', $scheduledDate)
                ->whereIn('status', [
                    HousekeepingStatus::SCHEDULED,
                    HousekeepingStatus::ASSIGNED,
                    HousekeepingStatus::IN_PROGRESS,
                    HousekeepingStatus::PENDING_VERIFICATION,
                ])
                ->exists();

            if ($existingActive) {
                throw new DomainException("Sudah ada tugas kebersihan aktif untuk kamar ini pada tanggal {$scheduledDate}. Gunakan tugas yang sudah ada atau batalkan terlebih dahulu.");
            }
        }

        $typeEnum = HousekeepingType::tryFrom($data['type'] ?? 'room_cleaning') ?? HousekeepingType::ROOM_CLEANING;
        $taskNumber = 'HSK-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        // Checklist bawaan jika tidak disediakan
        $checklist = !empty($data['checklist']) ? (is_array($data['checklist']) ? $data['checklist'] : json_decode($data['checklist'], true)) : [
            ['id' => 1, 'task' => 'Sapu dan pel lantai', 'is_done' => false],
            ['id' => 2, 'task' => 'Ganti sprei & sarung bantal', 'is_done' => false],
            ['id' => 3, 'task' => 'Bersihkan kamar mandi & kloset', 'is_done' => false],
            ['id' => 4, 'task' => 'Lap meja, lemari, & jendela', 'is_done' => false],
            ['id' => 5, 'task' => 'Kosongkan dan buang tempat sampah', 'is_done' => false],
            ['id' => 6, 'task' => 'Cek kelengkapan fasilitas & lampu', 'is_done' => false],
        ];

        $initialStatus = !empty($data['assigned_to']) ? HousekeepingStatus::ASSIGNED : HousekeepingStatus::SCHEDULED;

        return DB::transaction(function () use (
            $taskNumber,
            $propertyId,
            $roomId,
            $data,
            $scheduledDate,
            $typeEnum,
            $checklist,
            $initialStatus,
            $actor
        ) {
            $task = HousekeepingTask::create([
                'task_number' => $taskNumber,
                'property_id' => $propertyId,
                'room_id' => $roomId,
                'area_name' => $data['area_name'] ?? ($roomId ? "Kamar {$roomId}" : 'Area Bersama'),
                'type' => $typeEnum,
                'priority' => $data['priority'] ?? 'normal',
                'scheduled_date' => $scheduledDate,
                'scheduled_slot' => $data['scheduled_slot'] ?? null,
                'assigned_to' => !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null,
                'assigned_by' => !empty($data['assigned_to']) ? $actor->id : null,
                'status' => $initialStatus,
                'checklist' => $checklist,
                'notes' => $data['notes'] ?? null,
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'scheduled',
                'previous_status' => null,
                'new_status' => $initialStatus->value,
                'notes' => 'Jadwal kebersihan dibuat oleh ' . $actor->name,
            ]);

            if ($task->assigned_to) {
                $this->notificationService->createNotification(
                    $task->assigned_to,
                    'housekeeping_assigned',
                    "Tugas Kebersihan Baru: {$task->task_number}",
                    "Anda ditugaskan membersihkan {$task->area_name} pada tanggal {$task->scheduled_date->format('Y-m-d')}.",
                    ['task_id' => $task->id, 'task_number' => $task->task_number],
                    $propertyId
                );
            }

            return $task;
        });
    }

    /**
     * Tugaskan staf housekeeping
     */
    public function assignTask(HousekeepingTask $task, int $staffId, User $actor): HousekeepingTask
    {
        if ($actor->isTenant() || $actor->isCashier()) {
            throw new DomainException('Kasir atau penyewa tidak dapat menugaskan staf housekeeping.');
        }

        $this->authorizePropertyAccess($actor, $task->property_id);

        if (in_array($task->status, [HousekeepingStatus::COMPLETED, HousekeepingStatus::CANCELLED])) {
            throw new DomainException("Tidak dapat menugaskan tugas dengan status {$task->status->label()}.");
        }

        $isStaffOnProperty = DB::table('property_users')
            ->where('property_id', $task->property_id)
            ->where('user_id', $staffId)
            ->exists();

        if (!$isStaffOnProperty) {
            throw new DomainException('Staf yang dipilih tidak terdaftar pada properti ini.');
        }

        $staff = User::findOrFail($staffId);

        return DB::transaction(function () use ($task, $staff, $actor) {
            $prevStatus = $task->status->value;

            $task->update([
                'assigned_to' => $staff->id,
                'assigned_by' => $actor->id,
                'status' => HousekeepingStatus::ASSIGNED,
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'assigned',
                'previous_status' => $prevStatus,
                'new_status' => HousekeepingStatus::ASSIGNED->value,
                'notes' => "Ditugaskan kepada {$staff->name} oleh {$actor->name}",
            ]);

            $this->notificationService->createNotification(
                $staff->id,
                'housekeeping_assigned',
                "Tugas Kebersihan Baru: {$task->task_number}",
                "Anda ditugaskan membersihkan {$task->area_name}.",
                ['task_id' => $task->id, 'task_number' => $task->task_number],
                $task->property_id
            );

            return $task;
        });
    }

    /**
     * Mulai tugas kebersihan & unggah foto kondisi awal
     */
    public function startTask(HousekeepingTask $task, ?array $beforePhotos, User $actor): HousekeepingTask
    {
        if ($actor->isTenant() || $actor->isCashier()) {
            throw new DomainException('Kasir atau penyewa tidak dapat memulai tugas housekeeping.');
        }

        $this->authorizePropertyAccess($actor, $task->property_id);

        if ($task->status === HousekeepingStatus::IN_PROGRESS) {
            return $task;
        }

        if (!in_array($task->status, [HousekeepingStatus::SCHEDULED, HousekeepingStatus::ASSIGNED])) {
            throw new DomainException("Tugas tidak dapat dimulai dari status {$task->status->label()}.");
        }

        $savedPhotos = [];
        if (!empty($beforePhotos)) {
            foreach ($beforePhotos as $file) {
                if ($file instanceof UploadedFile) {
                    $this->validatePhotoFile($file);
                    $path = $file->store("housekeeping_photos/{$task->task_number}/before", 'local');
                    $savedPhotos[] = $path;
                }
            }
        }

        return DB::transaction(function () use ($task, $savedPhotos, $actor) {
            $prevStatus = $task->status->value;

            $task->update([
                'status' => HousekeepingStatus::IN_PROGRESS,
                'started_at' => now(),
                'photos_before' => !empty($savedPhotos) ? $savedPhotos : $task->photos_before,
                'assigned_to' => $task->assigned_to ?? ($actor->isStaff() ? $actor->id : null),
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'started',
                'previous_status' => $prevStatus,
                'new_status' => HousekeepingStatus::IN_PROGRESS->value,
                'notes' => 'Pembersihan dimulai oleh ' . $actor->name,
            ]);

            return $task;
        });
    }

    /**
     * Staf menyerahkan hasil tugas kebersihan untuk diverifikasi supervisor
     */
    public function submitTaskVerification(
        HousekeepingTask $task,
        array $checklistResult,
        ?array $afterPhotos,
        ?string $damagedOrMissing,
        ?string $notes,
        User $actor
    ): HousekeepingTask {
        if ($actor->isTenant() || $actor->isCashier()) {
            throw new DomainException('Kasir atau penyewa tidak dapat menyerahkan tugas housekeeping.');
        }

        $this->authorizePropertyAccess($actor, $task->property_id);

        if ($task->status !== HousekeepingStatus::IN_PROGRESS) {
            throw new DomainException("Hanya tugas berstatus 'Sedang Dikerjakan' yang dapat diserahkan untuk verifikasi. Status saat ini: {$task->status->label()}.");
        }

        $savedAfterPhotos = $task->photos_after ?? [];
        if (!empty($afterPhotos)) {
            foreach ($afterPhotos as $file) {
                if ($file instanceof UploadedFile) {
                    $this->validatePhotoFile($file);
                    $path = $file->store("housekeeping_photos/{$task->task_number}/after", 'local');
                    $savedAfterPhotos[] = $path;
                }
            }
        }

        return DB::transaction(function () use (
            $task,
            $checklistResult,
            $savedAfterPhotos,
            $damagedOrMissing,
            $notes,
            $actor
        ) {
            $prevStatus = $task->status->value;

            $task->update([
                'status' => HousekeepingStatus::PENDING_VERIFICATION,
                'checklist' => $checklistResult,
                'photos_after' => $savedAfterPhotos,
                'damaged_or_missing_items' => $damagedOrMissing,
                'notes' => $notes ?? $task->notes,
                'completed_at' => now(),
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'submitted_verification',
                'previous_status' => $prevStatus,
                'new_status' => HousekeepingStatus::PENDING_VERIFICATION->value,
                'notes' => 'Pembersihan selesai dikerjakan oleh staf dan diserahkan untuk verifikasi supervisor.',
            ]);

            // Beri tahu supervisor / manager
            $supervisors = DB::table('property_users')
                ->where('property_id', $task->property_id)
                ->whereIn('role', ['owner', 'manager'])
                ->pluck('user_id');

            foreach ($supervisors as $supId) {
                $this->notificationService->createNotification(
                    $supId,
                    'housekeeping_pending_verification',
                    "Verifikasi Kebersihan: {$task->task_number}",
                    "Tugas pembersihan {$task->area_name} telah selesai dikerjakan dan siap diverifikasi.",
                    ['task_id' => $task->id, 'task_number' => $task->task_number],
                    $task->property_id
                );
            }

            return $task;
        });
    }

    /**
     * Supervisor memverifikasi dan menandai tugas selesai
     * (PENTING: Tidak otomatis mengubah status ketersediaan kamar atau booking)
     */
    public function verifyTask(
        HousekeepingTask $task,
        string $verificationNotes,
        User $actor
    ): HousekeepingTask {
        if ($actor->isTenant() || $actor->isStaff() || $actor->isCashier()) {
            throw new DomainException('Hanya Pemilik, Manajer, atau Supervisor yang berwenang memverifikasi tugas kebersihan.');
        }

        $this->authorizePropertyAccess($actor, $task->property_id);

        if ($task->status !== HousekeepingStatus::PENDING_VERIFICATION) {
            throw new DomainException("Hanya tugas berstatus 'Menunggu Verifikasi' yang dapat disetujui. Status saat ini: {$task->status->label()}.");
        }

        return DB::transaction(function () use ($task, $verificationNotes, $actor) {
            $prevStatus = $task->status->value;

            $task->update([
                'status' => HousekeepingStatus::COMPLETED,
                'verified_at' => now(),
                'verified_by' => $actor->id,
                'verification_notes' => $verificationNotes,
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'completed',
                'previous_status' => $prevStatus,
                'new_status' => HousekeepingStatus::COMPLETED->value,
                'notes' => "Hasil kebersihan diverifikasi dan disetujui oleh {$actor->name}. Catatan: {$verificationNotes}",
            ]);

            return $task;
        });
    }

    /**
     * Validasi hak akses penyewa terhadap kamar yang dibersihkan
     */
    public function validateTenantRoomAccess(HousekeepingTask $task, User $tenant): void
    {
        if (!$tenant->isTenant()) {
            throw new DomainException('Hanya penyewa yang berhak melakukan konfirmasi hasil kebersihan.');
        }

        if (!$task->room_id) {
            throw new DomainException('Tugas kebersihan ini bukan untuk unit kamar.');
        }

        $hasActiveBooking = Booking::where(function ($q) use ($tenant) {
                $q->where('user_id', $tenant->id)
                  ->orWhere('guest_email', $tenant->email);
            })
            ->where('room_id', $task->room_id)
            ->whereIn('status', ['confirmed', 'active', 'checked_in'])
            ->exists();

        if (!$hasActiveBooking) {
            throw new DomainException('Anda tidak memiliki hak akses hunian pada kamar ini.');
        }
    }

    /**
     * Penyewa mengonfirmasi hasil kebersihan ("Sesuai — Konfirmasi Selesai")
     */
    public function tenantApproveTask(HousekeepingTask $task, ?string $feedback, User $tenant): HousekeepingTask
    {
        $this->validateTenantRoomAccess($task, $tenant);

        if ($task->status !== HousekeepingStatus::PENDING_VERIFICATION) {
            throw new DomainException("Hanya tugas berstatus 'Menunggu Verifikasi' yang dapat disetujui. Status saat ini: {$task->status->label()}.");
        }

        return DB::transaction(function () use ($task, $feedback, $tenant) {
            $prevStatus = $task->status->value;

            $task->update([
                'status' => HousekeepingStatus::COMPLETED,
                'verified_at' => now(),
                'verified_by' => $tenant->id,
                'verification_notes' => $feedback ?? 'Disetujui oleh penyewa: Hasil pembersihan kamar sesuai.',
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $tenant->id,
                'action' => 'tenant_approved',
                'previous_status' => $prevStatus,
                'new_status' => HousekeepingStatus::COMPLETED->value,
                'notes' => "Penyewa {$tenant->name} mengonfirmasi pekerjaan kebersihan selesai. Catatan: " . ($feedback ?? '-'),
            ]);

            if ($task->assigned_to) {
                $this->notificationService->createNotification(
                    $task->assigned_to,
                    'housekeeping_tenant_approved',
                    "Hasil Pembersihan Dikonfirmasi Selesai",
                    "Penyewa kamar {$task->area_name} telah menyetujui hasil pembersihan.",
                    ['task_id' => $task->id, 'task_number' => $task->task_number],
                    $task->property_id
                );
            }

            return $task;
        });
    }

    /**
     * Penyewa mengajukan permintaan perbaikan ("Belum Sesuai — Minta Perbaikan")
     */
    public function tenantRequestRevision(HousekeepingTask $task, string $reason, ?array $problematicItems, User $tenant): HousekeepingTask
    {
        $this->validateTenantRoomAccess($task, $tenant);

        if ($task->status !== HousekeepingStatus::PENDING_VERIFICATION) {
            throw new DomainException("Permintaan perbaikan hanya dapat diajukan jika tugas menunggu verifikasi. Status saat ini: {$task->status->label()}.");
        }

        if (empty(trim($reason))) {
            throw new DomainException('Alasan atau catatan perbaikan wajib diisi.');
        }

        return DB::transaction(function () use ($task, $reason, $problematicItems, $tenant) {
            $prevStatus = $task->status->value;

            $itemsSummary = !empty($problematicItems) ? ' [Item bermasalah: ' . implode(', ', $problematicItems) . ']' : '';
            $revisionNote = "[Permintaan Perbaikan Penyewa] {$reason}{$itemsSummary}";

            $task->update([
                'notes' => ($task->notes ? $task->notes . "\n" : '') . $revisionNote,
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $tenant->id,
                'action' => 'revision_requested',
                'previous_status' => $prevStatus,
                'new_status' => $task->status->value,
                'notes' => $revisionNote,
            ]);

            $supervisors = DB::table('property_users')
                ->where('property_id', $task->property_id)
                ->whereIn('role', ['owner', 'manager'])
                ->pluck('user_id');

            foreach ($supervisors as $supId) {
                $this->notificationService->createNotification(
                    $supId,
                    'housekeeping_revision_requested',
                    "Permintaan Perbaikan Kebersihan: {$task->area_name}",
                    "Penyewa {$tenant->name} meminta perbaikan: {$reason}",
                    ['task_id' => $task->id, 'task_number' => $task->task_number],
                    $task->property_id
                );
            }

            if ($task->assigned_to) {
                $this->notificationService->createNotification(
                    $task->assigned_to,
                    'housekeeping_revision_requested',
                    "Permintaan Perbaikan Kebersihan: {$task->area_name}",
                    "Penyewa meminta perbaikan kebersihan: {$reason}",
                    ['task_id' => $task->id, 'task_number' => $task->task_number],
                    $task->property_id
                );
            }

            return $task;
        });
    }

    /**
     * Jadwalkan ulang tanggal/slot tugas kebersihan
     */
    public function rescheduleTask(
        HousekeepingTask $task,
        string $newDate,
        ?string $newSlot,
        ?string $reason,
        User $actor
    ): HousekeepingTask {
        if ($actor->isTenant() || $actor->isStaff() || $actor->isCashier()) {
            throw new DomainException('Hanya Pemilik atau Manajer yang dapat menjadwalkan ulang tugas kebersihan.');
        }

        $this->authorizePropertyAccess($actor, $task->property_id);

        if (in_array($task->status, [HousekeepingStatus::COMPLETED, HousekeepingStatus::CANCELLED])) {
            throw new DomainException("Tugas dengan status {$task->status->label()} tidak dapat dijadwalkan ulang.");
        }

        return DB::transaction(function () use ($task, $newDate, $newSlot, $reason, $actor) {
            $oldDate = $task->scheduled_date->format('Y-m-d');
            $task->update([
                'scheduled_date' => $newDate,
                'scheduled_slot' => $newSlot ?? $task->scheduled_slot,
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'rescheduled',
                'previous_status' => $task->status->value,
                'new_status' => $task->status->value,
                'notes' => "Jadwal diubah dari {$oldDate} ke {$newDate} (slot: " . ($newSlot ?? '-') . ") oleh {$actor->name}. Alasan: " . ($reason ?? '-'),
            ]);

            return $task;
        });
    }

    /**
     * Batalkan tugas kebersihan dengan alasan wajib
     */
    public function cancelTask(HousekeepingTask $task, string $reason, User $actor): HousekeepingTask
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat membatalkan tugas kebersihan.');
        }

        $this->authorizePropertyAccess($actor, $task->property_id);

        if (empty(trim($reason))) {
            throw new DomainException('Alasan pembatalan tugas kebersihan wajib diisi.');
        }

        if (in_array($task->status, [HousekeepingStatus::COMPLETED, HousekeepingStatus::CANCELLED])) {
            throw new DomainException("Tugas dengan status {$task->status->label()} tidak dapat dibatalkan lagi.");
        }

        return DB::transaction(function () use ($task, $reason, $actor) {
            $prevStatus = $task->status->value;

            $task->update([
                'status' => HousekeepingStatus::CANCELLED,
                'cancellation_reason' => $reason,
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $actor->id,
                'action' => 'cancelled',
                'previous_status' => $prevStatus,
                'new_status' => HousekeepingStatus::CANCELLED->value,
                'notes' => "Tugas kebersihan dibatalkan oleh {$actor->name}. Alasan: {$reason}",
            ]);

            return $task;
        });
    }

    /**
     * Dapatkan path fisik foto privat housekeeping
     */
    public function getAuthorizedPhotoPath(HousekeepingTask $task, string $filePath, User $user): string
    {
        if ($user->isTenant()) {
            $isRoomRented = false;
            if ($task->room_id) {
                $isRoomRented = Booking::where('user_id', $user->id)
                    ->where('room_id', $task->room_id)
                    ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                    ->exists();
            }
            if (!$isRoomRented) {
                throw new DomainException('Anda tidak berhak melihat foto tugas kebersihan ini.');
            }
        } else {
            $this->authorizePropertyAccess($user, $task->property_id);
        }

        $allPhotos = array_merge($task->photos_before ?? [], $task->photos_after ?? []);
        if (!in_array($filePath, $allPhotos)) {
            throw new DomainException('Berkas foto tidak terdaftar pada tugas ini.');
        }

        if (!Storage::disk('local')->exists($filePath)) {
            throw new DomainException('Berkas foto tidak ditemukan pada penyimpanan server.');
        }

        return Storage::disk('local')->path($filePath);
    }

    /**
     * Pengajuan Pembersihan Kamar oleh Penyewa (Tenant Request)
     */
    public function requestRoomCleaning(User $tenant, array $data): HousekeepingTask
    {
        if (!$tenant->isTenant()) {
            throw new DomainException('Hanya penyewa yang dapat mengajukan pembersihan kamar melalui menu ini.');
        }

        // Cari booking aktif penyewa
        $booking = Booking::with('room.roomType', 'property')
            ->where(function ($q) use ($tenant) {
                $q->where('user_id', $tenant->id)
                  ->orWhere('guest_email', $tenant->email);
            })
            ->whereIn('status', ['confirmed', 'active', 'checked_in'])
            ->latest('id')
            ->first();

        if (!$booking || !$booking->room_id) {
            throw new DomainException('Anda tidak memiliki kamar aktif yang terdaftar untuk mengajukan pembersihan.');
        }

        $propertyId = $booking->property_id;
        $roomId = $booking->room_id;
        $scheduledDate = $data['scheduled_date'] ?? now()->toDateString();

        // Cegah duplikasi pengajuan yang belum selesai pada tanggal yang sama
        $existingActive = HousekeepingTask::where('room_id', $roomId)
            ->whereDate('scheduled_date', $scheduledDate)
            ->whereIn('status', [
                HousekeepingStatus::SCHEDULED,
                HousekeepingStatus::ASSIGNED,
                HousekeepingStatus::IN_PROGRESS,
                HousekeepingStatus::PENDING_VERIFICATION,
            ])
            ->exists();

        if ($existingActive) {
            throw new DomainException("Sudah ada jadwal atau pengajuan pembersihan aktif untuk kamar Anda pada tanggal {$scheduledDate}.");
        }

        $taskNumber = 'HK-REQ-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $roomNumber = $booking->room->room_number ?? "Kamar {$roomId}";

        $checklist = [
            ['id' => 1, 'task' => 'Sapu dan pel lantai kamar', 'is_done' => false],
            ['id' => 2, 'task' => 'Ganti sprei & sarung bantal', 'is_done' => false],
            ['id' => 3, 'task' => 'Bersihkan kamar mandi & kloset', 'is_done' => false],
            ['id' => 4, 'task' => 'Kosongkan dan buang tempat sampah', 'is_done' => false],
            ['id' => 5, 'task' => 'Cek kelengkapan fasilitas & perabot', 'is_done' => false],
        ];

        return DB::transaction(function () use (
            $taskNumber,
            $propertyId,
            $roomId,
            $roomNumber,
            $scheduledDate,
            $data,
            $checklist,
            $tenant
        ) {
            $task = HousekeepingTask::create([
                'task_number' => $taskNumber,
                'property_id' => $propertyId,
                'room_id' => $roomId,
                'area_name' => "Kamar {$roomNumber}",
                'type' => HousekeepingType::ON_DEMAND,
                'priority' => $data['priority'] ?? 'normal',
                'scheduled_date' => $scheduledDate,
                'scheduled_slot' => $data['scheduled_slot'] ?? 'Pagi (08:00 - 11:00)',
                'assigned_to' => null,
                'assigned_by' => null,
                'status' => HousekeepingStatus::SCHEDULED,
                'checklist' => $checklist,
                'notes' => '[Pengajuan Penyewa: ' . $tenant->name . '] ' . ($data['notes'] ?? ''),
            ]);

            HousekeepingLog::create([
                'housekeeping_task_id' => $task->id,
                'user_id' => $tenant->id,
                'action' => 'requested',
                'previous_status' => null,
                'new_status' => HousekeepingStatus::SCHEDULED->value,
                'notes' => 'Penyewa mengajukan pembersihan kamar. Catatan: ' . ($data['notes'] ?? '-'),
            ]);

            // Beri notifikasi in-app kepada staf & manajer properti
            $staffUserIds = DB::table('property_users')
                ->where('property_id', $propertyId)
                ->pluck('user_id');

            foreach ($staffUserIds as $staffId) {
                $this->notificationService->createNotification(
                    user: $staffId,
                    type: 'housekeeping_requested',
                    title: "Pengajuan Pembersihan: Kamar {$roomNumber}",
                    message: "Penyewa {$tenant->name} mengajukan pembersihan kamar untuk tanggal {$scheduledDate}.",
                    data: [
                        'task_id' => $task->id,
                        'task_number' => $task->task_number,
                        'action_url' => "/housekeeping/{$task->id}",
                    ],
                    propertyId: $propertyId
                );
            }

            return $task;
        });
    }

    /**
     * Batalkan pengajuan pembersihan kamar oleh penyewa
     */
    public function cancelCleaningRequest(int $taskId, User $tenant, ?string $reason = null): HousekeepingTask
    {
        $task = HousekeepingTask::findOrFail($taskId);

        if ($tenant->isTenant()) {
            $activeRoomIds = Booking::where('user_id', $tenant->id)
                ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                ->pluck('room_id')
                ->toArray();

            if (!in_array($task->room_id, $activeRoomIds, true)) {
                throw new DomainException('Anda tidak berhak membatalkan pengajuan pada kamar ini.');
            }

            if ($task->status !== HousekeepingStatus::SCHEDULED) {
                throw new DomainException('Pengajuan hanya dapat dibatalkan jika belum mulai dikerjakan oleh petugas.');
            }
        }

        $prevStatus = $task->status->value;
        $task->update([
            'status' => HousekeepingStatus::CANCELLED,
            'cancellation_reason' => $reason ?? 'Dibatalkan atas permintaan penyewa.',
        ]);

        HousekeepingLog::create([
            'housekeeping_task_id' => $task->id,
            'user_id' => $tenant->id,
            'action' => 'cancelled',
            'previous_status' => $prevStatus,
            'new_status' => HousekeepingStatus::CANCELLED->value,
            'notes' => $reason ?? 'Dibatalkan oleh penyewa.',
        ]);

        return $task;
    }

    /**
     * Ambil data kamar aktif milik penyewa
     */
    public function getActiveRoomForTenant(User $tenant): ?array
    {
        $booking = Booking::with(['room.roomType', 'property'])
            ->where(function ($q) use ($tenant) {
                $q->where('user_id', $tenant->id)
                  ->orWhere('guest_email', $tenant->email);
            })
            ->whereIn('status', ['confirmed', 'active', 'checked_in'])
            ->latest('id')
            ->first();

        if (!$booking || !$booking->room) {
            return null;
        }

        return [
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'property_id' => $booking->property_id,
            'property_name' => $booking->property->name ?? '-',
            'room_id' => $booking->room_id,
            'room_number' => $booking->room->room_number,
            'room_type' => $booking->room->roomType->name ?? 'Standard',
            'check_in_date' => $booking->start_date ? $booking->start_date->format('Y-m-d') : null,
            'check_out_date' => $booking->end_date ? $booking->end_date->format('Y-m-d') : null,
        ];
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
