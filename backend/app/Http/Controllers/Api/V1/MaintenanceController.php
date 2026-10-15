<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaintenanceController extends ApiController
{
    public function __construct(
        protected MaintenanceService $maintenanceService
    ) {}

    /**
     * GET /api/v1/maintenance
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only([
            'property_id',
            'room_id',
            'category',
            'priority',
            'status',
            'is_overdue',
            'assigned_to',
            'search',
            'my_assigned_only',
        ]);

        $requests = $this->maintenanceService->getMaintenanceQuery($user, $filters)->paginate(15);

        return $this->successResponse($requests, 'Daftar tiket perbaikan berhasil diambil.');
    }

    /**
     * POST /api/v1/maintenance
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'room_asset_id' => 'nullable|exists:room_assets,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'category' => 'nullable|in:electricity,water,plumbing,ac,furniture,door_lock,internet,cleanliness,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'scheduled_date' => 'nullable|date',
            'photos_before' => 'nullable|array',
            'photos_before.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $maintenance = $this->maintenanceService->createMaintenance(
                $request->all(),
                $request->file('photos_before'),
                $user
            );

            return $this->successResponse($maintenance, 'Tiket perbaikan berhasil diajukan.', 201);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/maintenance/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::with([
            'property',
            'room.roomType',
            'roomAsset',
            'complaint',
            'reporter',
            'assignee',
            'verifier',
            'logs.user',
        ])->findOrFail($id);

        try {
            if ($user->isTenant()) {
                if ($user->id !== $maintenance->reporter_id && $maintenance->room_id) {
                    $isRoomRented = \App\Models\Booking::where('user_id', $user->id)
                        ->where('room_id', $maintenance->room_id)
                        ->whereIn('status', ['confirmed', 'checked_in'])
                        ->exists();

                    if (!$isRoomRented) {
                        return $this->errorResponse('Anda tidak berhak melihat tiket perbaikan ini.', 403);
                    }
                }
            } else {
                $this->maintenanceService->authorizePropertyAccess($user, $maintenance->property_id);
            }
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        return $this->successResponse($maintenance, 'Detail tiket perbaikan berhasil diambil.');
    }

    /**
     * POST /api/v1/maintenance/{id}/triage
     */
    public function triage(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);

        $request->validate([
            'category' => 'nullable|in:electricity,water,plumbing,ac,furniture,door_lock,internet,cleanliness,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $triaged = $this->maintenanceService->triageMaintenance($maintenance, $request->all(), $user);
            return $this->successResponse($triaged, 'Tiket perbaikan berhasil ditriase.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/maintenance/{id}/assign
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'scheduled_date' => 'nullable|date',
        ]);

        try {
            $assigned = $this->maintenanceService->assignMaintenance(
                $maintenance,
                (int) $request->assigned_to,
                $request->scheduled_date,
                $user
            );

            return $this->successResponse($assigned, 'Teknisi berhasil ditugaskan.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/maintenance/{id}/start
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);

        $request->validate([
            'diagnosis_notes' => 'nullable|string',
        ]);

        try {
            $started = $this->maintenanceService->startMaintenance(
                $maintenance,
                $request->diagnosis_notes,
                $user
            );

            return $this->successResponse($started, 'Pekerjaan perbaikan telah dimulai.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/maintenance/{id}/submit-verification
     */
    public function submitVerification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);

        $request->validate([
            'action_taken' => 'required|string',
            'diagnosis_notes' => 'nullable|string',
            'spare_parts' => 'nullable',
            'labor_cost' => 'nullable|numeric|min:0',
            'material_cost' => 'nullable|numeric|min:0',
            'photos_after' => 'nullable|array',
            'photos_after.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $submitted = $this->maintenanceService->submitForVerification(
                $maintenance,
                $request->all(),
                $request->file('photos_after'),
                $user
            );

            return $this->successResponse($submitted, 'Hasil perbaikan berhasil diserahkan untuk verifikasi.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/maintenance/{id}/verify
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);

        $request->validate([
            'verification_notes' => 'required|string|min:3|max:500',
        ]);

        try {
            $verified = $this->maintenanceService->verifyAndComplete(
                $maintenance,
                $request->verification_notes,
                $user
            );

            return $this->successResponse($verified, 'Perbaikan telah diverifikasi dan ditandai selesai.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/maintenance/{id}/reject-cancel
     */
    public function rejectOrCancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);

        $request->validate([
            'action' => 'required|in:reject,cancel',
            'reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $result = $this->maintenanceService->rejectOrCancel(
                $maintenance,
                $request->action,
                $request->reason,
                $user
            );

            $msg = ($request->action === 'reject') ? 'Tiket perbaikan ditolak.' : 'Tiket perbaikan dibatalkan.';
            return $this->successResponse($result, $msg);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/maintenance/{id}/photo
     */
    public function photo(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $maintenance = MaintenanceRequest::findOrFail($id);

        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $filePath = $this->maintenanceService->getAuthorizedPhotoPath($maintenance, $request->path, $user);
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
            || str_contains($msg, 'tidak berhak')
            || str_contains($msg, 'hanya dapat membuat tiket perbaikan pada properti yang sedang disewa');

        return $this->errorResponse($msg, $isAuth ? 403 : 422);
    }
}
