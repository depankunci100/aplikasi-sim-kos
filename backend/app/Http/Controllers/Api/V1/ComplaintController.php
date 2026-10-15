<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Complaint;
use App\Services\Complaint\ComplaintService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ComplaintController extends ApiController
{
    public function __construct(
        protected ComplaintService $complaintService
    ) {}

    /**
     * GET /api/v1/complaints
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
            'search',
            'my_assigned_only',
        ]);

        $complaints = $this->complaintService->getComplaintsQuery($user, $filters)->paginate(15);

        return $this->successResponse($complaints, 'Daftar keluhan berhasil diambil.');
    }

    /**
     * POST /api/v1/complaints
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'category' => 'nullable|in:room,facility,service,billing,environment,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $complaint = $this->complaintService->createComplaint(
                $request->all(),
                $request->file('photos'),
                $user
            );

            return $this->successResponse($complaint, 'Keluhan berhasil diajukan.', 201);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/complaints/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::with([
            'property',
            'room.roomType',
            'user',
            'assignee',
            'acknowledger',
            'closer',
            'maintenanceRequest',
            'logs.user',
            'comments' => fn ($q) => $user->isTenant() ? $q->where('is_internal', false)->with('user') : $q->with('user'),
        ])->findOrFail($id);

        try {
            if ($user->isTenant()) {
                if ($user->id !== $complaint->user_id) {
                    return $this->errorResponse('Anda tidak berhak melihat keluhan penyewa lain.', 403);
                }
            } else {
                $this->complaintService->authorizePropertyAccess($user, $complaint->property_id);
            }
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        return $this->successResponse($complaint, 'Detail keluhan berhasil diambil.');
    }

    /**
     * POST /api/v1/complaints/{id}/acknowledge
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        try {
            $ack = $this->complaintService->acknowledgeComplaint($complaint, $user);
            return $this->successResponse($ack, 'Keluhan telah diterima dan ditinjau petugas.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/complaints/{id}/assign
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        try {
            $assigned = $this->complaintService->assignComplaint($complaint, (int) $request->assigned_to, $user);
            return $this->successResponse($assigned, 'Penanggung jawab keluhan berhasil ditetapkan.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/complaints/{id}/comments
     */
    public function addComment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $request->validate([
            'comment' => 'required|string|max:1000',
            'is_internal' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        try {
            $comment = $this->complaintService->addComment(
                $complaint,
                $request->comment,
                $request->file('attachments'),
                filter_var($request->is_internal ?? false, FILTER_VALIDATE_BOOLEAN),
                $user
            );

            return $this->successResponse($comment, 'Tanggapan berhasil dikirim.', 201);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/complaints/{id}/link-maintenance
     */
    public function linkMaintenance(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $request->validate([
            'category' => 'nullable|in:electricity,water,plumbing,ac,furniture,door_lock,internet,cleanliness,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            $maintenance = $this->complaintService->linkToMaintenance($complaint, $request->all(), $user);
            return $this->successResponse($maintenance, 'Keluhan berhasil ditautkan ke tiket perbaikan.', 201);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/complaints/{id}/resolve
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $request->validate([
            'resolution_notes' => 'required|string|min:3|max:500',
        ]);

        try {
            $resolved = $this->complaintService->resolveComplaint($complaint, $request->resolution_notes, $user);
            return $this->successResponse($resolved, 'Keluhan telah ditandai selesai oleh petugas.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/complaints/{id}/confirm-reopen
     */
    public function confirmOrReopen(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $request->validate([
            'action' => 'required|in:confirm,reopen',
            'reason' => 'required_if:action,reopen|nullable|string|min:3|max:500',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->complaintService->confirmOrReopen(
                $complaint,
                $request->action,
                $request->reason,
                $request->rating ? (int) $request->rating : null,
                $request->feedback,
                $user
            );

            $msg = ($request->action === 'confirm')
                ? 'Keluhan berhasil dikonfirmasi selesai dan ditutup.'
                : 'Keluhan telah dibuka kembali untuk penanganan lebih lanjut.';

            return $this->successResponse($result, $msg);
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * POST /api/v1/complaints/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $rejected = $this->complaintService->rejectComplaint($complaint, $request->rejection_reason, $user);
            return $this->successResponse($rejected, 'Keluhan telah ditolak.');
        } catch (DomainException $e) {
            return $this->handleDomainException($e);
        }
    }

    /**
     * GET /api/v1/complaints/{id}/photo
     */
    public function photo(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            $filePath = $this->complaintService->getAuthorizedPhotoPath($complaint, $request->path, $user);
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
            || str_contains($msg, 'Penyewa hanya dapat membuat keluhan pada properti yang sedang disewa');

        return $this->errorResponse($msg, $isAuth ? 403 : 422);
    }
}
