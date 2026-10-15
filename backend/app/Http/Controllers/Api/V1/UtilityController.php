<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use App\Services\Utility\UtilityService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UtilityController extends ApiController
{
    public function __construct(
        protected UtilityService $utilityService
    ) {}

    // ==========================================
    // 1. METER ENDPOINTS
    // ==========================================

    /**
     * GET /api/v1/utilities/meters
     */
    public function indexMeters(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['property_id', 'room_id', 'utility_type', 'meter_type', 'is_active', 'search']);

        $meters = $this->utilityService->getMetersQuery($user, $filters)->paginate(15);

        return $this->successResponse($meters, 'Daftar meter utilitas berhasil diambil.');
    }

    /**
     * POST /api/v1/utilities/meters
     */
    public function storeMeter(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_id' => 'nullable|exists:rooms,id',
            'utility_type' => 'required|in:electricity,water',
            'meter_number' => 'required|string|max:100|unique:utility_meters,meter_number',
            'meter_type' => 'nullable|in:individual,shared',
            'unit' => 'nullable|string|max:20',
            'initial_reading' => 'nullable|numeric|min:0',
            'max_reading' => 'nullable|numeric|min:0',
            'installed_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $meter = $this->utilityService->createMeter($request->all(), $user);
            return $this->successResponse($meter, 'Meter utilitas berhasil didaftarkan.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/utilities/meters/{id}
     */
    public function showMeter(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $meter = UtilityMeter::with(['property', 'room.roomType', 'readings' => fn ($q) => $q->latest('reading_date')->limit(10)])
            ->findOrFail($id);

        try {
            $this->utilityService->authorizePropertyAccess($user, $meter->property_id);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        return $this->successResponse($meter, 'Detail meter utilitas berhasil diambil.');
    }

    /**
     * PUT /api/v1/utilities/meters/{id}
     */
    public function updateMeter(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $meter = UtilityMeter::findOrFail($id);

        $request->validate([
            'room_id' => 'nullable|exists:rooms,id',
            'meter_number' => "nullable|string|max:100|unique:utility_meters,meter_number,{$id}",
            'unit' => 'nullable|string|max:20',
            'max_reading' => 'nullable|numeric|min:0',
            'installed_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $updated = $this->utilityService->updateMeter($meter, $request->all(), $user);
            return $this->successResponse($updated, 'Konfigurasi meter utilitas berhasil diperbarui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ==========================================
    // 2. TARIFF ENDPOINTS
    // ==========================================

    /**
     * GET /api/v1/utilities/tariffs
     */
    public function indexTariffs(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['property_id', 'utility_type', 'is_active']);

        $tariffs = $this->utilityService->getTariffsQuery($user, $filters)->paginate(15);

        return $this->successResponse($tariffs, 'Daftar tarif utilitas berhasil diambil.');
    }

    /**
     * POST /api/v1/utilities/tariffs
     */
    public function storeTariff(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'utility_type' => 'required|in:electricity,water',
            'rate_per_unit' => 'required|numeric|min:0.01',
            'unit' => 'nullable|string|max:20',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $tariff = $this->utilityService->createTariff($request->all(), $user);
            return $this->successResponse($tariff, 'Tarif utilitas berhasil dikonfigurasi.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/utilities/tariffs/effective
     */
    public function getEffectiveTariff(Request $request): JsonResponse
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'utility_type' => 'required|in:electricity,water',
            'date' => 'nullable|date',
        ]);

        $date = $request->date ?? now()->toDateString();
        $tariff = $this->utilityService->getEffectiveTariff(
            (int) $request->property_id,
            $request->utility_type,
            $date
        );

        if (!$tariff) {
            return $this->errorResponse('Tidak ada konfigurasi tarif aktif untuk tanggal tersebut.', 404);
        }

        return $this->successResponse($tariff, 'Tarif aktif berhasil ditemukan.');
    }

    // ==========================================
    // 3. READING & AUDIT ENDPOINTS
    // ==========================================

    /**
     * GET /api/v1/utilities/readings
     */
    public function indexReadings(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['meter_id', 'property_id', 'room_id', 'utility_type', 'status', 'is_billed']);

        $readings = $this->utilityService->getReadingsQuery($user, $filters)->paginate(15);

        return $this->successResponse($readings, 'Daftar pembacaan meter berhasil diambil.');
    }

    /**
     * POST /api/v1/utilities/readings
     */
    public function storeReading(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'meter_id' => 'required|exists:utility_meters,id',
            'current_meter' => 'required|numeric|min:0',
            'reading_date' => 'nullable|date',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'room_id' => 'nullable|exists:rooms,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'proof_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'status' => 'nullable|in:draft,pending_review,approved',
            'notes' => 'nullable|string',
        ]);

        try {
            $reading = $this->utilityService->recordReading(
                $request->all(),
                $request->file('proof_photo'),
                $user
            );

            return $this->successResponse($reading, 'Pembacaan meter berhasil dicatat.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/utilities/readings/{id}
     */
    public function showReading(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $reading = UtilityReading::with(['meter', 'property', 'room.roomType', 'booking.user', 'invoice', 'recorder', 'reviewer', 'logs.user'])
            ->findOrFail($id);

        try {
            $this->utilityService->authorizePropertyAccess($user, $reading->property_id);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }

        return $this->successResponse($reading, 'Detail pembacaan meter berhasil diambil.');
    }

    /**
     * POST /api/v1/utilities/readings/{id}/review
     */
    public function reviewReading(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $reading = UtilityReading::findOrFail($id);

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        try {
            $reviewed = $this->utilityService->reviewReading(
                $reading,
                $request->status,
                $request->rejection_reason,
                $user
            );

            return $this->successResponse($reviewed, "Pembacaan meter berhasil ditinjau ({$request->status}).");
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/utilities/readings/{id}/correct
     */
    public function correctReading(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $reading = UtilityReading::findOrFail($id);

        $request->validate([
            'current_meter' => 'required|numeric|min:0',
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $corrected = $this->utilityService->correctReading(
                $reading,
                (float) $request->current_meter,
                $request->reason,
                $user
            );

            return $this->successResponse($corrected, 'Koreksi pembacaan meter berhasil disimpan dalam audit trail.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/utilities/readings/{id}/preview-bill
     */
    public function previewBill(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $preview = $this->utilityService->previewBill($id, $user);
            return $this->successResponse($preview, 'Pratinjau perhitungan tagihan utilitas berhasil diambil.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/utilities/readings/{id}/generate-invoice
     */
    public function generateInvoice(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'target_invoice_id' => 'nullable|exists:invoices,id',
        ]);

        try {
            $invoice = $this->utilityService->generateUtilityInvoice(
                $id,
                $user,
                $request->target_invoice_id ? (int) $request->target_invoice_id : null
            );

            return $this->successResponse($invoice, 'Tagihan utilitas berhasil diterbitkan ke sistem invoice.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/utilities/readings/{id}/proof-photo
     */
    public function proofPhoto(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $reading = UtilityReading::findOrFail($id);

        try {
            $filePath = $this->utilityService->getAuthorizedProofPhotoPath($reading, $user);
            return response()->file($filePath, [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    /**
     * GET /api/v1/utilities/tenant/consumption
     */
    public function tenantConsumption(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isTenant()) {
            return $this->errorResponse('Endpoint ini khusus untuk penyewa.', 403);
        }

        $readings = $this->utilityService->getReadingsQuery($user)->paginate(15);

        return $this->successResponse($readings, 'Riwayat pemakaian utilitas kamar berhasil diambil.');
    }
}
