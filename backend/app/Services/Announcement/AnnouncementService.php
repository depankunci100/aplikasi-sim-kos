<?php

namespace App\Services\Announcement;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\Notification\NotificationService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Ambil query pengumuman dengan cakupan akses dan filter
     */
    public function getAnnouncementsQuery(User $user, array $filters = []): Builder
    {
        $query = Announcement::forUser($user)
            ->with(['property', 'author'])
            ->latest('published_at')
            ->latest('id');

        if (!empty($filters['property_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('property_id', $filters['property_id'])
                    ->orWhereNull('property_id');
            });
        }

        if (isset($filters['is_read'])) {
            $isRead = filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN);
            if ($isRead) {
                $query->whereHas('reads', fn ($q) => $q->where('user_id', $user->id));
            } else {
                $query->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id));
            }
        }

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Hitung pengumuman yang belum dibaca oleh user
     */
    public function getUnreadCount(User $user): int
    {
        return Announcement::forUser($user)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    /**
     * Ambil detail pengumuman dan otomatis tandai telah dibaca jika dipanggil oleh penyewa
     */
    public function getAnnouncementDetail(int $id, User $user, bool $autoMarkRead = true): Announcement
    {
        $announcement = Announcement::forUser($user)
            ->with(['property', 'author'])
            ->findOrFail($id);

        if ($autoMarkRead) {
            $this->markAsRead($announcement->id, $user);
        }

        return $announcement;
    }

    /**
     * Tandai pengumuman telah dibaca secara idempoten
     */
    public function markAsRead(int $announcementId, User $user): bool
    {
        // Pastikan user berhak mengakses pengumuman
        $announcement = Announcement::forUser($user)->findOrFail($announcementId);

        AnnouncementRead::firstOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ],
            [
                'read_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Buat pengumuman baru (Staf/Manajer/Owner/Super Admin)
     */
    public function createAnnouncement(array $data, User $actor, ?UploadedFile $attachment = null): Announcement
    {
        if ($actor->isTenant()) {
            throw new DomainException('Penyewa tidak memiliki izin untuk menerbitkan pengumuman.');
        }

        $propertyId = !empty($data['property_id']) ? (int) $data['property_id'] : null;

        if ($propertyId !== null && !$actor->isSuperAdmin()) {
            $hasAccess = DB::table('property_users')
                ->where('user_id', $actor->id)
                ->where('property_id', $propertyId)
                ->exists();

            if (!$hasAccess) {
                throw new DomainException('Anda tidak memiliki hak kelola pada properti yang dipilih.');
            }
        }

        $attachmentPath = null;
        if ($attachment && $attachment->isValid()) {
            $attachmentPath = $attachment->store('announcements', 'public');
        }

        $announcement = Announcement::create([
            'property_id' => $propertyId,
            'author_id' => $actor->id,
            'title' => trim($data['title']),
            'content' => trim($data['content']),
            'attachment_path' => $attachmentPath,
            'target_role' => $data['target_role'] ?? 'all',
            'is_published' => isset($data['is_published']) ? (bool) $data['is_published'] : true,
            'published_at' => !empty($data['published_at']) ? $data['published_at'] : now(),
        ]);

        // Kirim in-app notification ke penerima target jika dipublikasikan
        if ($announcement->is_published) {
            $this->notifyTargetUsers($announcement);
        }

        return $announcement;
    }

    /**
     * Perbarui pengumuman
     */
    public function updateAnnouncement(int $id, array $data, User $actor, ?UploadedFile $attachment = null): Announcement
    {
        $announcement = Announcement::findOrFail($id);

        if (!$actor->isSuperAdmin() && $announcement->author_id !== $actor->id) {
            if ($announcement->property_id) {
                $hasAccess = DB::table('property_users')
                    ->where('user_id', $actor->id)
                    ->where('property_id', $announcement->property_id)
                    ->exists();

                if (!$hasAccess) {
                    throw new DomainException('Anda tidak berwenang memperbarui pengumuman ini.');
                }
            } else {
                throw new DomainException('Hanya pembuat pengumuman atau Super Admin yang dapat mengedit pengumuman global.');
            }
        }

        if ($attachment && $attachment->isValid()) {
            if ($announcement->attachment_path) {
                Storage::disk('public')->delete($announcement->attachment_path);
            }
            $announcement->attachment_path = $attachment->store('announcements', 'public');
        }

        $announcement->update([
            'property_id' => !empty($data['property_id']) ? (int) $data['property_id'] : null,
            'title' => trim($data['title'] ?? $announcement->title),
            'content' => trim($data['content'] ?? $announcement->content),
            'target_role' => $data['target_role'] ?? $announcement->target_role,
            'is_published' => isset($data['is_published']) ? (bool) $data['is_published'] : $announcement->is_published,
            'published_at' => !empty($data['published_at']) ? $data['published_at'] : $announcement->published_at,
        ]);

        return $announcement;
    }

    /**
     * Hapus pengumuman
     */
    public function deleteAnnouncement(int $id, User $actor): void
    {
        $announcement = Announcement::findOrFail($id);

        if (!$actor->isSuperAdmin() && $announcement->author_id !== $actor->id) {
            throw new DomainException('Anda tidak memiliki izin untuk menghapus pengumuman ini.');
        }

        if ($announcement->attachment_path) {
            Storage::disk('public')->delete($announcement->attachment_path);
        }

        $announcement->delete();
    }

    /**
     * Dispatch in-app notification ke pengguna yang berhak menerima
     */
    protected function notifyTargetUsers(Announcement $announcement): void
    {
        $query = User::where('is_active', true)->where('id', '!=', $announcement->author_id);

        if ($announcement->target_role === 'tenant') {
            $query->where('role', 'tenant');
        } elseif ($announcement->target_role === 'staff') {
            $query->whereIn('role', ['staff', 'receptionist']);
        }

        if ($announcement->property_id) {
            if ($announcement->target_role === 'tenant') {
                $tenantUserIds = Booking::where('property_id', $announcement->property_id)
                    ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                    ->pluck('user_id')
                    ->filter();

                $query->whereIn('id', $tenantUserIds);
            } else {
                $staffUserIds = DB::table('property_users')
                    ->where('property_id', $announcement->property_id)
                    ->pluck('user_id');

                $query->whereIn('id', $staffUserIds);
            }
        }

        $recipientIds = $query->limit(100)->pluck('id');

        foreach ($recipientIds as $userId) {
            $this->notificationService->createNotification(
                user: $userId,
                type: 'announcement_new',
                title: 'Pengumuman: ' . Str::limit($announcement->title, 40),
                message: Str::limit($announcement->content, 120),
                data: [
                    'announcement_id' => $announcement->id,
                    'action_url' => '/announcements/' . $announcement->id,
                ],
                propertyId: $announcement->property_id
            );
        }
    }
}
