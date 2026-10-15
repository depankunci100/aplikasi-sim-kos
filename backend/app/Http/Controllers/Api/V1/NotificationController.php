<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOLEAN);

        $notifications = $this->notificationService->getNotificationsQuery($user, $unreadOnly)->paginate(20);

        return $this->successResponse($notifications, 'Daftar notifikasi berhasil diambil.');
    }

    /**
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->notificationService->getNotificationsQuery($user, true)->count();

        return $this->successResponse(['unread_count' => $count], 'Jumlah notifikasi belum dibaca.');
    }

    /**
     * POST /api/v1/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $success = $this->notificationService->markAsRead($id, $user);

        return $this->successResponse(['marked' => $success], 'Status notifikasi berhasil diperbarui.');
    }

    /**
     * POST /api/v1/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->notificationService->markAllAsRead($user);

        return $this->successResponse(['marked_count' => $count], 'Semua notifikasi telah ditandai dibaca.');
    }
}
