<?php

namespace App\Services\Complaint;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\ComplaintLog;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\Maintenance\MaintenanceService;
use App\Services\Notification\NotificationService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MaintenanceService $maintenanceService
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
     * Ambil query keluhan dengan kontrol keamanan per role
     */
    public function getComplaintsQuery(User $user, array $filters = []): Builder
    {
        $query = Complaint::with([
            'property',
            'room.roomType',
            'user',
            'assignee',
            'maintenanceRequest',
        ])->latest('id');

        if ($user->isTenant()) {
            // Penyewa HANYA melihat keluhan miliknya sendiri
            $query->where('user_id', $user->id);
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

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($sub) use ($term) {
                $sub->where('ticket_number', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('room', fn ($r) => $r->where('room_number', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    /**
     * Ajukan keluhan baru
     */
    public function createComplaint(array $data, ?array $photos, User $actor): Complaint
    {
        $propertyId = (int) $data['property_id'];

        if ($actor->isTenant()) {
            // Pastikan properti disewa oleh penyewa
            $hasActiveBooking = Booking::where('user_id', $actor->id)
                ->where('property_id', $propertyId)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->exists();

            if (!$hasActiveBooking) {
                throw new DomainException('Penyewa hanya dapat mengajukan keluhan pada properti yang sedang disewa.');
            }

            // Jika keluhan terikat kamar tertentu, pastikan itu kamar penyewa
            if (!empty($data['room_id'])) {
                $isRoomRented = Booking::where('user_id', $actor->id)
                    ->where('room_id', $data['room_id'])
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->exists();

                if (!$isRoomRented) {
                    throw new DomainException('Penyewa tidak dapat mengajukan keluhan untuk kamar milik penyewa lain.');
                }
            }
        } else {
            $this->authorizePropertyAccess($actor, $propertyId);
        }

        $roomId = !empty($data['room_id']) ? (int) $data['room_id'] : null;
        if ($roomId) {
            $room = Room::where('property_id', $propertyId)->find($roomId);
            if (!$room) {
                throw new DomainException("Kamar ID {$roomId} tidak ditemukan pada properti ini.");
            }
        }

        $categoryEnum = ComplaintCategory::tryFrom($data['category'] ?? 'room') ?? ComplaintCategory::ROOM;
        $priorityEnum = ComplaintPriority::tryFrom($data['priority'] ?? 'normal') ?? ComplaintPriority::NORMAL;
        $ticketNumber = 'CMP-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        $savedPhotos = [];
        if (!empty($photos)) {
            foreach ($photos as $file) {
                if ($file instanceof UploadedFile) {
                    $this->validatePhotoFile($file);
                    $path = $file->store("complaint_photos/{$ticketNumber}", 'local');
                    $savedPhotos[] = $path;
                }
            }
        }

        return DB::transaction(function () use (
            $ticketNumber,
            $propertyId,
            $roomId,
            $actor,
            $categoryEnum,
            $priorityEnum,
            $data,
            $savedPhotos
        ) {
            $complaint = Complaint::create([
                'ticket_number' => $ticketNumber,
                'property_id' => $propertyId,
                'room_id' => $roomId,
                'user_id' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $categoryEnum,
                'priority' => $priorityEnum,
                'status' => ComplaintStatus::SUBMITTED,
                'photos' => $savedPhotos,
            ]);

            ComplaintLog::create([
                'complaint_id' => $complaint->id,
                'user_id' => $actor->id,
                'action' => 'submitted',
                'previous_status' => null,
                'new_status' => ComplaintStatus::SUBMITTED->value,
                'notes' => 'Keluhan diajukan oleh ' . $actor->name,
            ]);

            // Kirim notifikasi ke pengelola properti
            $staffUsers = DB::table('property_users')
                ->where('property_id', $propertyId)
                ->whereIn('role', ['owner', 'manager', 'receptionist'])
                ->pluck('user_id');

            foreach ($staffUsers as $staffId) {
                $this->notificationService->createNotification(
                    $staffId,
                    'complaint_new',
                    "Keluhan Baru: {$complaint->ticket_number}",
                    "Keluhan baru dari {$actor->name}: '{$complaint->title}'. Kategori: {$categoryEnum->label()}.",
                    ['complaint_id' => $complaint->id, 'ticket_number' => $complaint->ticket_number],
                    $propertyId
                );
            }

            return $complaint;
        });
    }

    /**
     * Staf menerima dan mengakui keluhan (triase / acknowledge)
     */
    public function acknowledgeComplaint(Complaint $complaint, User $actor): Complaint
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat melakukan triase keluhan.');
        }

        $this->authorizePropertyAccess($actor, $complaint->property_id);

        if ($complaint->status !== ComplaintStatus::SUBMITTED && $complaint->status !== ComplaintStatus::REOPENED) {
            return $complaint;
        }

        return DB::transaction(function () use ($complaint, $actor) {
            $prevStatus = $complaint->status->value;

            $complaint->update([
                'status' => ComplaintStatus::ACKNOWLEDGED,
                'acknowledged_at' => now(),
                'acknowledged_by' => $actor->id,
                'first_responded_at' => $complaint->first_responded_at ?? now(),
            ]);

            ComplaintLog::create([
                'complaint_id' => $complaint->id,
                'user_id' => $actor->id,
                'action' => 'acknowledged',
                'previous_status' => $prevStatus,
                'new_status' => ComplaintStatus::ACKNOWLEDGED->value,
                'notes' => 'Keluhan diterima dan ditinjau oleh ' . $actor->name,
            ]);

            // Notifikasi ke pelapor
            $this->notificationService->createNotification(
                $complaint->user_id,
                'complaint_acknowledged',
                "Keluhan Sedang Ditinjau: {$complaint->ticket_number}",
                "Keluhan Anda telah diterima oleh petugas dan sedang dalam tahap penanganan.",
                ['complaint_id' => $complaint->id, 'ticket_number' => $complaint->ticket_number],
                $complaint->property_id
            );

            return $complaint;
        });
    }

    /**
     * Menetapkan penanggung jawab keluhan
     */
    public function assignComplaint(Complaint $complaint, int $assigneeId, User $actor): Complaint
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat menetapkan petugas.');
        }

        $this->authorizePropertyAccess($actor, $complaint->property_id);

        $isStaffOnProperty = DB::table('property_users')
            ->where('property_id', $complaint->property_id)
            ->where('user_id', $assigneeId)
            ->exists();

        if (!$isStaffOnProperty) {
            throw new DomainException('Petugas yang dipilih tidak bertugas pada properti ini.');
        }

        $assignee = User::findOrFail($assigneeId);

        return DB::transaction(function () use ($complaint, $assignee, $actor) {
            $prevStatus = $complaint->status->value;

            $complaint->update([
                'assigned_to' => $assignee->id,
                'status' => ComplaintStatus::IN_PROGRESS,
                'first_responded_at' => $complaint->first_responded_at ?? now(),
            ]);

            ComplaintLog::create([
                'complaint_id' => $complaint->id,
                'user_id' => $actor->id,
                'action' => 'assigned',
                'previous_status' => $prevStatus,
                'new_status' => ComplaintStatus::IN_PROGRESS->value,
                'notes' => "Penanganan ditugaskan kepada {$assignee->name} oleh {$actor->name}",
            ]);

            $this->notificationService->createNotification(
                $assignee->id,
                'complaint_assigned',
                "Penugasan Keluhan: {$complaint->ticket_number}",
                "Anda ditugaskan menangani keluhan: '{$complaint->title}'.",
                ['complaint_id' => $complaint->id, 'ticket_number' => $complaint->ticket_number],
                $complaint->property_id
            );

            return $complaint;
        });
    }

    /**
     * Tambahkan tanggapan / komentar pada keluhan
     */
    public function addComment(
        Complaint $complaint,
        string $commentText,
        ?array $attachmentFiles,
        bool $isInternal,
        User $actor
    ): ComplaintComment {
        if ($actor->isTenant()) {
            if ($actor->id !== $complaint->user_id) {
                throw new DomainException('Anda tidak berhak memberi komentar pada keluhan penyewa lain.');
            }
            $isInternal = false; // Tenant tidak dapat membuat catatan internal
        } else {
            $this->authorizePropertyAccess($actor, $complaint->property_id);
        }

        if (empty(trim($commentText))) {
            throw new DomainException('Isi tanggapan tidak boleh kosong.');
        }

        $attachments = [];
        if (!empty($attachmentFiles)) {
            foreach ($attachmentFiles as $file) {
                if ($file instanceof UploadedFile) {
                    $this->validatePhotoFile($file);
                    $path = $file->store("complaint_photos/{$complaint->ticket_number}/comments", 'local');
                    $attachments[] = $path;
                }
            }
        }

        return DB::transaction(function () use ($complaint, $commentText, $attachments, $isInternal, $actor) {
            $comment = ComplaintComment::create([
                'complaint_id' => $complaint->id,
                'user_id' => $actor->id,
                'comment' => $commentText,
                'attachments' => $attachments,
                'is_internal' => $isInternal,
            ]);

            // Jika staf/manager memberi tanggapan pertama
            if (!$actor->isTenant() && !$complaint->first_responded_at) {
                $complaint->update(['first_responded_at' => now()]);
            }

            // Kirim notifikasi jika bukan internal note
            if (!$isInternal) {
                $recipientId = ($actor->id === $complaint->user_id)
                    ? ($complaint->assigned_to ?? $complaint->property->users()->first()?->id)
                    : $complaint->user_id;

                if ($recipientId) {
                    $this->notificationService->createNotification(
                        $recipientId,
                        'complaint_comment',
                        "Tanggapan Baru: {$complaint->ticket_number}",
                        "{$actor->name} mengirim tanggapan pada keluhan '{$complaint->title}'.",
                        ['complaint_id' => $complaint->id, 'ticket_number' => $complaint->ticket_number],
                        $complaint->property_id
                    );
                }
            }

            return $comment;
        });
    }

    /**
     * Tautkan keluhan ke tiket perbaikan (Maintenance) secara idempoten (anti-duplikasi)
     */
    public function linkToMaintenance(Complaint $complaint, array $maintenanceData, User $actor): MaintenanceRequest
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat membuat tiket perbaikan teknis dari keluhan.');
        }

        $this->authorizePropertyAccess($actor, $complaint->property_id);

        // Jaminan Anti-Duplikasi: Jika sudah ada tiket perbaikan tertaut, kembalikan tiket tersebut!
        if ($complaint->maintenance_request_id) {
            $existing = MaintenanceRequest::find($complaint->maintenance_request_id);
            if ($existing) {
                return $existing;
            }
        }

        $maintenanceData['property_id'] = $complaint->property_id;
        $maintenanceData['room_id'] = $complaint->room_id;
        $maintenanceData['complaint_id'] = $complaint->id;
        $maintenanceData['title'] = $maintenanceData['title'] ?? ("Perbaikan: " . $complaint->title);
        $maintenanceData['description'] = $maintenanceData['description'] ?? $complaint->description;
        $maintenanceData['priority'] = $maintenanceData['priority'] ?? $complaint->priority->value;

        return DB::transaction(function () use ($complaint, $maintenanceData, $actor) {
            $maintenance = $this->maintenanceService->createMaintenance($maintenanceData, null, $actor);

            $complaint->update([
                'maintenance_request_id' => $maintenance->id,
                'status' => ComplaintStatus::IN_PROGRESS,
            ]);

            ComplaintLog::create([
                'complaint_id' => $complaint->id,
                'user_id' => $actor->id,
                'action' => 'linked_maintenance',
                'previous_status' => $complaint->status->value,
                'new_status' => ComplaintStatus::IN_PROGRESS->value,
                'notes' => "Dibuatkan tiket perbaikan #{$maintenance->ticket_number} oleh {$actor->name}",
            ]);

            return $maintenance;
        });
    }

    /**
     * Selesaikan keluhan oleh petugas
     */
    public function resolveComplaint(Complaint $complaint, string $resolutionNotes, User $actor): Complaint
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat menyelesaikan keluhan secara sepihak.');
        }

        $this->authorizePropertyAccess($actor, $complaint->property_id);

        if (empty(trim($resolutionNotes))) {
            throw new DomainException('Catatan penyelesaian keluhan wajib diisi.');
        }

        if (in_array($complaint->status, [ComplaintStatus::CLOSED, ComplaintStatus::REJECTED])) {
            throw new DomainException("Keluhan berstatus {$complaint->status->label()} tidak dapat diselesaikan lagi.");
        }

        return DB::transaction(function () use ($complaint, $resolutionNotes, $actor) {
            $prevStatus = $complaint->status->value;

            $complaint->update([
                'status' => ComplaintStatus::RESOLVED,
                'resolution_notes' => $resolutionNotes,
                'resolved_at' => now(),
            ]);

            ComplaintLog::create([
                'complaint_id' => $complaint->id,
                'user_id' => $actor->id,
                'action' => 'resolved',
                'previous_status' => $prevStatus,
                'new_status' => ComplaintStatus::RESOLVED->value,
                'notes' => "Keluhan ditandai selesai oleh {$actor->name}. Catatan: {$resolutionNotes}",
            ]);

            // Beri tahu penyewa agar mengonfirmasi atau membuka kembali
            $this->notificationService->createNotification(
                $complaint->user_id,
                'complaint_resolved',
                "Keluhan Selesai Ditangani: {$complaint->ticket_number}",
                "Keluhan Anda telah ditandai selesai oleh petugas. Silakan konfirmasi atau buka kembali bila belum tuntas.",
                ['complaint_id' => $complaint->id, 'ticket_number' => $complaint->ticket_number],
                $complaint->property_id
            );

            return $complaint;
        });
    }

    /**
     * Konfirmasi penyelesaian (tutup) atau buka kembali oleh penyewa
     */
    public function confirmOrReopen(
        Complaint $complaint,
        string $action, // 'confirm' atau 'reopen'
        ?string $reason,
        ?int $rating,
        ?string $feedback,
        User $tenant
    ): Complaint {
        if ($tenant->id !== $complaint->user_id && !$tenant->isSuperAdmin()) {
            throw new DomainException('Hanya penyewa yang mengajukan keluhan yang berhak mengonfirmasi penyelesaian atau membukanya kembali.');
        }

        if ($complaint->status !== ComplaintStatus::RESOLVED) {
            throw new DomainException("Hanya keluhan dengan status 'Terselesaikan' yang dapat dikonfirmasi atau dibuka kembali. Status saat ini: {$complaint->status->label()}.");
        }

        return DB::transaction(function () use ($complaint, $action, $reason, $rating, $feedback, $tenant) {
            $prevStatus = $complaint->status->value;

            if ($action === 'confirm') {
                $complaint->update([
                    'status' => ComplaintStatus::CLOSED,
                    'closed_at' => now(),
                    'closed_by' => $tenant->id,
                    'rating' => $rating,
                    'tenant_feedback' => $feedback,
                ]);

                ComplaintLog::create([
                    'complaint_id' => $complaint->id,
                    'user_id' => $tenant->id,
                    'action' => 'closed',
                    'previous_status' => $prevStatus,
                    'new_status' => ComplaintStatus::CLOSED->value,
                    'notes' => "Keluhan dikonfirmasi selesai dan ditutup oleh penyewa. Rating: " . ($rating ?? '-') . "/5",
                ]);
            } else {
                if (empty(trim($reason ?? ''))) {
                    throw new DomainException('Alasan pembukaan kembali keluhan wajib diisi.');
                }

                $complaint->update([
                    'status' => ComplaintStatus::REOPENED,
                    'reopened_at' => now(),
                    'reopen_reason' => $reason,
                ]);

                ComplaintLog::create([
                    'complaint_id' => $complaint->id,
                    'user_id' => $tenant->id,
                    'action' => 'reopened',
                    'previous_status' => $prevStatus,
                    'new_status' => ComplaintStatus::REOPENED->value,
                    'notes' => "Keluhan dibuka kembali oleh penyewa. Alasan: {$reason}",
                ]);

                // Notifikasi ke petugas
                if ($complaint->assigned_to) {
                    $this->notificationService->createNotification(
                        $complaint->assigned_to,
                        'complaint_reopened',
                        "Keluhan Dibuka Kembali: {$complaint->ticket_number}",
                        "Keluhan '{$complaint->title}' dibuka kembali oleh penyewa. Alasan: {$reason}",
                        ['complaint_id' => $complaint->id, 'ticket_number' => $complaint->ticket_number],
                        $complaint->property_id
                    );
                }
            }

            return $complaint;
        });
    }

    /**
     * Tolak keluhan dengan alasan wajib
     */
    public function rejectComplaint(Complaint $complaint, string $rejectionReason, User $actor): Complaint
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak dapat menolak keluhan.');
        }

        $this->authorizePropertyAccess($actor, $complaint->property_id);

        if (empty(trim($rejectionReason))) {
            throw new DomainException('Alasan penolakan keluhan wajib diisi.');
        }

        if (in_array($complaint->status, [ComplaintStatus::CLOSED, ComplaintStatus::REJECTED])) {
            throw new DomainException("Keluhan dengan status {$complaint->status->label()} tidak dapat ditolak lagi.");
        }

        return DB::transaction(function () use ($complaint, $rejectionReason, $actor) {
            $prevStatus = $complaint->status->value;

            $complaint->update([
                'status' => ComplaintStatus::REJECTED,
                'rejection_reason' => $rejectionReason,
            ]);

            ComplaintLog::create([
                'complaint_id' => $complaint->id,
                'user_id' => $actor->id,
                'action' => 'rejected',
                'previous_status' => $prevStatus,
                'new_status' => ComplaintStatus::REJECTED->value,
                'notes' => "Keluhan ditolak oleh {$actor->name}. Alasan: {$rejectionReason}",
            ]);

            $this->notificationService->createNotification(
                $complaint->user_id,
                'complaint_rejected',
                "Keluhan Ditolak: {$complaint->ticket_number}",
                "Keluhan Anda ditolak oleh petugas. Alasan: {$rejectionReason}",
                ['complaint_id' => $complaint->id, 'ticket_number' => $complaint->ticket_number],
                $complaint->property_id
            );

            return $complaint;
        });
    }

    /**
     * Dapatkan path fisik foto privat keluhan dengan verifikasi otorisasi
     */
    public function getAuthorizedPhotoPath(Complaint $complaint, string $filePath, User $user): string
    {
        if ($user->isTenant()) {
            if ($user->id !== $complaint->user_id) {
                throw new DomainException('Anda tidak berhak melihat foto lampiran keluhan ini.');
            }
        } else {
            $this->authorizePropertyAccess($user, $complaint->property_id);
        }

        if (!in_array($filePath, $complaint->photos ?? [])) {
            // Cek apakah ada di attachments komentar
            $existsInComments = ComplaintComment::where('complaint_id', $complaint->id)
                ->whereJsonContains('attachments', $filePath)
                ->exists();

            if (!$existsInComments) {
                throw new DomainException('Berkas foto tidak terdaftar pada keluhan ini.');
            }
        }

        if (!Storage::disk('local')->exists($filePath)) {
            throw new DomainException('Berkas foto tidak ditemukan di server.');
        }

        return Storage::disk('local')->path($filePath);
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
