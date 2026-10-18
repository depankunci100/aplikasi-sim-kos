<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Announcement;
use App\Services\Announcement\AnnouncementService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends ApiController
{
    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    /**
     * GET /api/v1/announcements
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['property_id', 'is_read', 'search']);

        $announcements = $this->announcementService
            ->getAnnouncementsQuery($user, $filters)
            ->paginate(15);

        // Tambahkan metadata apakah sudah dibaca oleh user yang sedang login
        $announcements->getCollection()->transform(function (Announcement $announcement) use ($user) {
            $announcement->is_read = $announcement->isReadBy($user);
            return $announcement;
        });

        $unreadCount = $this->announcementService->getUnreadCount($user);

        return $this->successResponse([
            'unread_count' => $unreadCount,
            'announcements' => $announcements,
        ], 'Daftar pengumuman berhasil diambil.');
    }

    /**
     * GET /api/v1/announcements/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->announcementService->getUnreadCount($user);

        return $this->successResponse(['unread_count' => $count], 'Jumlah pengumuman belum dibaca.');
    }

    /**
     * GET /api/v1/announcements/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $announcement = $this->announcementService->getAnnouncementDetail($id, $user, true);
        $announcement->is_read = true;

        return $this->successResponse($announcement, 'Detail pengumuman berhasil diambil.');
    }

    /**
     * POST /api/v1/announcements/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $success = $this->announcementService->markAsRead($id, $user);

        return $this->successResponse(['marked' => $success], 'Pengumuman telah ditandai sebagai dibaca.');
    }

    /**
     * POST /api/v1/announcements
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_role' => 'nullable|in:all,tenant,staff',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        try {
            $announcement = $this->announcementService->createAnnouncement(
                $request->all(),
                $user,
                $request->file('attachment')
            );

            return $this->successResponse($announcement, 'Pengumuman berhasil diterbitkan.', 201);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * PUT /api/v1/announcements/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'target_role' => 'nullable|in:all,tenant,staff',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        try {
            $announcement = $this->announcementService->updateAnnouncement(
                $id,
                $request->all(),
                $user,
                $request->file('attachment')
            );

            return $this->successResponse($announcement, 'Pengumuman berhasil diperbarui.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * DELETE /api/v1/announcements/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $this->announcementService->deleteAnnouncement($id, $user);
            return $this->successResponse(null, 'Pengumuman berhasil dihapus.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }
}
