<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\HousekeepingTask;
use App\Services\Housekeeping\HousekeepingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HousekeepingController extends ApiController
{
    public function __construct(
        protected HousekeepingService $housekeepingService
    ) {}

    /**
     * GET /api/v1/housekeeping
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only([
            'property_id',
            'room_id',
            'status',
            'type',
            'scheduled_date',
            'assigned_to',
            'my_assigned_only',
        ]);

        $tasks = $this->housekeepingService->getTasksQuery($user, $filters)->paginate(15);

        return $this->successResponse($tasks, 'Daftar tugas kebersihan berhasil diambil.');
    }

    /**
     * POST /api/v1/housekeeping
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'area_name' => 'nullable|string|max:100',
            'type' => 'nullable|in:room_cleaning,turnover_cleaning,deep_cleaning,common_area_cleaning,on_demand',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'scheduled_date' => 'nullable|date',
            'scheduled_slot' => 'nullable|string|max:50',
            'assigned_to' => 'nullable|exists:users,id',
            'checklist' => 'nullable|array',
            'notes' => 'nullable|string',
            'allow_duplicate' => 'nullable|boolean',
        ]);

        try {
            $task = $this->housekeepingService->createTask($request->all(), $user);
            return $this->successResponse($task, 'Tugas kebersihan berhasil dijadwalkan.', 201);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/housekeeping/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::with([
            'property',
            'room.roomType',
            'assignee',
            'assigner',
            'verifier',
            'logs.user',
        ])->findOrFail($id);

        try {
            if ($user->isTenant()) {
                $isRoomRented = false;
                if ($task->room_id) {
                    $isRoomRented = \App\Models\Booking::where('user_id', $user->id)
                        ->where('room_id', $task->room_id)
                        ->whereIn('status', ['confirmed', 'checked_in'])
                        ->exists();
                }
                if (!$isRoomRented) {
                    return $this->errorResponse('Anda tidak berhak melihat tugas kebersihan ini.', 403);
                }
            } else {
                $this->housekeepingService->authorizePropertyAccess($user, $task->property_id);
            }
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        return $this->successResponse($task, 'Detail tugas kebersihan berhasil diambil.');
    }

    /**
     * POST /api/v1/housekeeping/{id}/assign
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        try {
            $assigned = $this->housekeepingService->assignTask($task, (int) $request->assigned_to, $user);
            return $this->successResponse($assigned, 'Staf kebersihan berhasil ditugaskan.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/housekeeping/{id}/start
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'photos_before' => 'nullable|array',
            'photos_before.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $started = $this->housekeepingService->startTask($task, $request->file('photos_before'), $user);
            return $this->successResponse($started, 'Pekerjaan kebersihan telah dimulai.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/housekeeping/{id}/submit-verification
     */
    public function submitVerification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'checklist' => 'required|array',
            'checklist.*.id' => 'required',
            'checklist.*.is_done' => 'required|boolean',
            'damaged_or_missing_items' => 'nullable|string',
            'notes' => 'nullable|string',
            'photos_after' => 'nullable|array',
            'photos_after.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $submitted = $this->housekeepingService->submitTaskVerification(
                $task,
                $request->checklist,
                $request->file('photos_after'),
                $request->damaged_or_missing_items,
                $request->notes,
                $user
            );

            return $this->successResponse($submitted, 'Tugas kebersihan berhasil diserahkan untuk verifikasi.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/housekeeping/{id}/verify
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'verification_notes' => 'required|string|min:3|max:500',
        ]);

        try {
            $verified = $this->housekeepingService->verifyTask($task, $request->verification_notes, $user);
            return $this->successResponse($verified, 'Tugas kebersihan telah diverifikasi selesai.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/housekeeping/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $cancelled = $this->housekeepingService->cancelTask($task, $request->reason, $user);
            return $this->successResponse($cancelled, 'Tugas kebersihan telah dibatalkan.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/housekeeping/{id}/photo
     */
    public function photo(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $task = HousekeepingTask::findOrFail($id);

        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $filePath = $this->housekeepingService->getAuthorizedPhotoPath($task, $request->path, $user);
            return response()->file($filePath, [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    private function handleDomainException(DomainException $e): JsonResponse
    {
        $msg = $e->getMessage();
        $isAuth = str_contains($msg, 'hak akses')
            || str_contains($msg, 'izin')
            || str_contains($msg, 'orang lain')
            || str_contains($msg, 'tidak berhak');

        return $this->errorResponse($msg, $isAuth ? 403 : 422);
    }
}
