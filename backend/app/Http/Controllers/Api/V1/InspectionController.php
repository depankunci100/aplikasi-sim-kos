<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionMedia;
use App\Services\Inspection\InspectionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InspectionController extends ApiController
{
    public function __construct(
        protected InspectionService $inspectionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Inspection::with(['property', 'room.roomType', 'inspector', 'tenant'])->latest('scheduled_date');

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && !empty($request->type)) {
            $query->where('type', $request->type);
        }

        if ($request->has('room_id') && !empty($request->room_id)) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('property_id') && !empty($request->property_id)) {
            $query->where('property_id', $request->property_id);
        }

        $inspections = $query->paginate(15);

        return $this->successResponse($inspections, 'Daftar data inspeksi kamar berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isTenant()) {
            return $this->errorResponse('Penyewa tidak memiliki izin untuk menjadwalkan inspeksi kamar.', 403);
        }

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'inspector_id' => ['nullable', 'exists:users,id'],
            'tenant_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'in:check_in,periodic,check_out,maintenance'],
            'scheduled_date' => ['required', 'date'],
            'baseline_inspection_id' => ['nullable', 'exists:inspections,id'],
            'inspector_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Validasi hak kelola properti
        if (!$user->isSuperAdmin()) {
            $isAssigned = DB::table('property_users')
                ->where('property_id', $validated['property_id'])
                ->where('user_id', $user->id)
                ->whereIn('role', ['owner', 'manager'])
                ->exists();

            if (!$isAssigned) {
                return $this->errorResponse('Anda tidak memiliki wewenang menjadwalkan inspeksi pada properti ini.', 403);
            }
        }

        try {
            $inspection = $this->inspectionService->createInspection($validated, $user);
            return $this->successResponse($inspection, 'Jadwal inspeksi kamar berhasil dibuat.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/inspections/request
     * Pengajuan Inspeksi Kamar oleh Penyewa
     */
    public function tenantRequest(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['nullable', 'in:periodic,maintenance,check_out'],
            'reason' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scheduled_date' => ['nullable', 'date'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $photos = $request->file('photos', []);
        if (!is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }

        try {
            $inspection = $this->inspectionService->requestInspection($user, $validated, $photos);
            return $this->successResponse($inspection, 'Permohonan inspeksi kamar berhasil diajukan ke pengelola.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::with([
            'property',
            'room.roomType',
            'booking',
            'inspector',
            'tenant',
            'items.media',
            'media',
            'logs.user',
            'baselineInspection',
        ])->find($id);

        if (!$inspection) {
            return $this->errorResponse('Data inspeksi kamar tidak ditemukan.', 404);
        }

        if (!$this->inspectionService->canAccessInspection($inspection, $user)) {
            return $this->errorResponse('Anda tidak memiliki izin mengakses data inspeksi ini.', 403);
        }

        return $this->successResponse($inspection, 'Detail inspeksi kamar berhasil diambil.');
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return $this->errorResponse('Data inspeksi tidak ditemukan.', 404);
        }

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            return $this->errorResponse('Anda tidak memiliki hak untuk melaksanakan inspeksi ini.', 403);
        }

        try {
            $started = $this->inspectionService->startInspection($inspection, $user);
            return $this->successResponse($started, 'Inspeksi kamar telah dimulai.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return $this->errorResponse('Data inspeksi tidak ditemukan.', 404);
        }

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            return $this->errorResponse('Anda tidak memiliki izin mengubah item inspeksi ini.', 403);
        }

        $item = InspectionItem::where('inspection_id', $inspection->id)->find($itemId);
        if (!$item) {
            return $this->errorResponse('Item inspeksi tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'condition' => ['required', 'in:good,fair,poor,damaged,missing'],
            'notes' => ['nullable', 'string', 'max:500'],
            'repair_required' => ['nullable', 'boolean'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $updated = $this->inspectionService->updateItem($item, $validated, $user);
            return $this->successResponse($updated, 'Kondisi item checklist berhasil diperbarui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function uploadMedia(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return $this->errorResponse('Data inspeksi tidak ditemukan.', 404);
        }

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            return $this->errorResponse('Anda tidak memiliki izin mengunggah foto inspeksi ini.', 403);
        }

        $validated = $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'inspection_item_id' => ['nullable', 'exists:inspection_items,id'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $media = $this->inspectionService->addMedia(
                $inspection,
                $request->file('photo'),
                $validated['inspection_item_id'] ?? null,
                $validated['caption'] ?? null,
                $user
            );

            return $this->successResponse($media, 'Foto bukti inspeksi berhasil diunggah.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function streamMedia(Request $request, int $id, int $mediaId): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return $this->errorResponse('Data inspeksi tidak ditemukan.', 404);
        }

        if (!$this->inspectionService->canAccessInspection($inspection, $user)) {
            return $this->errorResponse('Akses ke foto bukti inspeksi ini ditolak.', 403);
        }

        $media = InspectionMedia::where('inspection_id', $inspection->id)->find($mediaId);
        if (!$media || !Storage::disk('local')->exists($media->file_path)) {
            return $this->errorResponse('Berkas fisik foto bukti tidak ditemukan.', 404);
        }

        $fullPath = Storage::disk('local')->path($media->file_path);
        $mimeType = Storage::disk('local')->mimeType($media->file_path) ?? 'image/jpeg';

        $fileResponse = response()->file($fullPath, [
            'Content-Type' => $mimeType,
        ]);
        $fileResponse->setPrivate();
        $fileResponse->headers->addCacheControlDirective('no-cache', true);

        return $fileResponse;
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::find($id);

        if (!$inspection) {
            return $this->errorResponse('Data inspeksi tidak ditemukan.', 404);
        }

        if (!$this->inspectionService->canExecuteInspection($inspection, $user)) {
            return $this->errorResponse('Anda tidak memiliki izin menyelesaikan inspeksi ini.', 403);
        }

        $validated = $request->validate([
            'overall_condition' => ['required', 'in:good,fair,poor'],
            'inspector_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $completed = $this->inspectionService->completeInspection($inspection, $validated, $user);
            return $this->successResponse($completed, 'Inspeksi kamar telah diselesaikan dan diteruskan ke penyewa.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenant()) {
            return $this->errorResponse('Hanya penyewa yang berhak menyetujui hasil inspeksi kamar.', 403);
        }

        $inspection = Inspection::find($id);
        if (!$inspection) {
            return $this->errorResponse('Data inspeksi tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'feedback' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $approved = $this->inspectionService->tenantApprove($inspection, $validated['feedback'] ?? null, $user);
            return $this->successResponse($approved, 'Hasil inspeksi kamar berhasil disetujui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function object(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenant()) {
            return $this->errorResponse('Hanya penyewa yang berhak mengajukan keberatan inspeksi kamar.', 403);
        }

        $inspection = Inspection::find($id);
        if (!$inspection) {
            return $this->errorResponse('Data inspeksi tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
            'problematic_items' => ['nullable', 'array'],
            'problematic_items.*' => ['string', 'max:100'],
        ]);

        try {
            $objected = $this->inspectionService->tenantObject(
                $inspection,
                $validated['notes'],
                $user,
                $validated['problematic_items'] ?? null
            );
            return $this->successResponse($objected, 'Keberatan hasil inspeksi kamar berhasil dicatat untuk tindak lanjut pengelola.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/inspections/{id}/assign
     * Penugasan inspektor oleh manajer
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::findOrFail($id);

        $request->validate([
            'inspector_id' => 'required|exists:users,id',
        ]);

        try {
            $assigned = $this->inspectionService->assignInspector($inspection, (int) $request->inspector_id, $user);
            return $this->successResponse($assigned, 'Inspektor berhasil ditugaskan.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/inspections/{id}/reschedule
     * Penjadwalan ulang tanggal inspeksi kamar oleh manajer
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $inspection = Inspection::findOrFail($id);

        $request->validate([
            'scheduled_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $rescheduled = $this->inspectionService->rescheduleInspection($inspection, $request->scheduled_date, $request->reason, $user);
            return $this->successResponse($rescheduled, 'Jadwal inspeksi kamar berhasil diperbarui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function compare(Request $request, int $id, int $baselineId): JsonResponse
    {
        $user = $request->user();
        $current = Inspection::with('items')->find($id);
        $baseline = Inspection::with('items')->find($baselineId);

        if (!$current || !$baseline) {
            return $this->errorResponse('Satu atau kedua data inspeksi tidak ditemukan.', 404);
        }

        if (!$this->inspectionService->canAccessInspection($current, $user) || !$this->inspectionService->canAccessInspection($baseline, $user)) {
            return $this->errorResponse('Anda tidak memiliki izin membandingkan inspeksi ini.', 403);
        }

        if ($current->room_id !== $baseline->room_id || $current->property_id !== $baseline->property_id) {
            return $this->errorResponse('Inspeksi baseline harus berasal dari kamar dan properti yang sama.', 422);
        }

        try {
            $comparison = $this->inspectionService->compareInspections($current, $baseline);
            return $this->successResponse($comparison, 'Hasil perbandingan kondisi kamar berhasil dikalkulasi.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
