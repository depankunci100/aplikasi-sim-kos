<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DepositStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Booking;
use App\Models\CheckOutSettlement;
use App\Models\DepositLedger;
use App\Services\Deposit\DepositService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositController extends ApiController
{
    public function __construct(
        protected DepositService $depositService
    ) {}

    /**
     * Daftar Subledger Uang Jaminan (Deposit)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DepositLedger::with(['property', 'booking.room.roomType', 'tenant', 'latestSettlement'])
            ->latest();

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('deposit_code', 'like', "%{$search}%")
                  ->orWhereHas('booking', fn($b) => $b->where('booking_code', 'like', "%{$search}%"))
                  ->orWhereHas('tenant', fn($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $ledgers = $query->paginate($perPage);

        return $this->successResponse($ledgers, 'Daftar subledger deposit berhasil diambil.');
    }

    /**
     * Detail Subledger Deposit & Histori Mutasi
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $raw = DepositLedger::find($id);
        if (!$raw) {
            return $this->errorResponse('Data deposit tidak ditemukan.', 404);
        }

        $ledger = $this->findAuthorizedLedger($id, $user);
        if (!$ledger) {
            return $this->errorResponse('Anda tidak memiliki hak akses ke data deposit ini.', 403);
        }

        $ledger->load([
            'property',
            'booking.room.roomType',
            'tenant',
            'mutations.performer',
            'mutations.inspection',
            'settlements.deductions',
        ]);

        return $this->successResponse($ledger, 'Detail subledger deposit berhasil diambil.');
    }

    /**
     * Koreksi Audit Manual
     */
    public function recordAdjustment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageFinances()) {
            return $this->errorResponse('Hanya manajer atau pemilik kos yang berwenang melakukan koreksi audit deposit.', 403);
        }

        $ledger = $this->findAuthorizedLedger($id, $user);
        if (!$ledger) {
            return $this->errorResponse('Data deposit tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'type' => ['required', 'in:adjustment_credit,adjustment_debit'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $mutation = $this->depositService->recordAdjustment(
                $ledger,
                (float) $validated['amount'],
                $validated['type'],
                $user,
                $validated['reason']
            );

            return $this->successResponse($mutation, 'Koreksi audit berhasil dicatat.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * Daftar Penyelesaian Check-Out (Settlements)
     */
    public function settlementsIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = CheckOutSettlement::with(['property', 'booking.room.roomType', 'tenant', 'depositLedger'])
            ->latest();

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $settlements = $query->paginate($perPage);

        return $this->successResponse($settlements, 'Daftar settlement check-out berhasil diambil.');
    }

    /**
     * Inisiasi Check-Out Settlement
     */
    public function initiateSettlement(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
            'check_out_date' => ['required', 'date'],
            'inspection_id' => ['nullable', 'exists:inspections,id'],
            'refund_destination' => ['nullable', 'string', 'max:150'],
            'custom_deductions' => ['nullable', 'array'],
            'custom_deductions.*.title' => ['required_with:custom_deductions', 'string', 'max:150'],
            'custom_deductions.*.amount' => ['required_with:custom_deductions', 'numeric', 'min:0'],
            'custom_deductions.*.description' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        if ($user->isTenant() && $booking->user_id !== $user->id) {
            return $this->errorResponse('Anda tidak berhak mengajukan check-out untuk pemesanan ini.', 403);
        }

        try {
            $settlement = $this->depositService->initiateCheckOutSettlement($booking, $user, $validated);
            return $this->successResponse($settlement, 'Settlement check-out berhasil diinisiasi.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Detail Settlement Check-Out
     */
    public function settlementShow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $raw = CheckOutSettlement::find($id);
        if (!$raw) {
            return $this->errorResponse('Data settlement tidak ditemukan.', 404);
        }

        $settlement = $this->findAuthorizedSettlement($id, $user);
        if (!$settlement) {
            return $this->errorResponse('Anda tidak memiliki akses ke data settlement ini.', 403);
        }

        $settlement->load([
            'booking.room.roomType',
            'property',
            'tenant',
            'depositLedger.mutations',
            'inspection.items',
            'deductions.inspectionItem',
            'resolver',
            'refunder',
            'creator',
        ]);

        return $this->successResponse($settlement, 'Detail settlement check-out berhasil diambil.');
    }

    /**
     * Respon Penyewa: Setujui atau Sengketa (Dispute)
     */
    public function submitTenantDecision(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $settlement = $this->findAuthorizedSettlement($id, $user);

        if (!$settlement) {
            return $this->errorResponse('Settlement tidak ditemukan atau Anda tidak memiliki akses.', 404);
        }

        if ($settlement->tenant_id !== $user->id && !$user->isSuperAdmin()) {
            return $this->errorResponse('Hanya penyewa bersangkutan yang dapat memberikan respon.', 403);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,dispute'],
            'objection_notes' => ['nullable', 'string', 'max:1000'],
            'disputed_deductions' => ['nullable', 'array'],
        ]);

        $isApproved = ($validated['decision'] === 'approve');

        try {
            $updated = $this->depositService->submitTenantDecision(
                $settlement,
                $user,
                $isApproved,
                $validated['objection_notes'] ?? null,
                $validated['disputed_deductions'] ?? []
            );

            return $this->successResponse($updated, 'Respon keputusan penyewa berhasil disimpan.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * Penyelesaian Sengketa Denda (Manager / Owner)
     */
    public function resolveDispute(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageFinances()) {
            return $this->errorResponse('Hanya manajer atau pemilik kos yang berwenang menyelesaikan sengketa.', 403);
        }

        $settlement = $this->findAuthorizedSettlement($id, $user);
        if (!$settlement) {
            return $this->errorResponse('Settlement tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:1000'],
            'approved_amounts' => ['required', 'array'],
        ]);

        try {
            $resolved = $this->depositService->resolveSettlementDispute(
                $settlement,
                $user,
                $validated['approved_amounts'],
                $validated['resolution_notes']
            );

            return $this->successResponse($resolved, 'Sengketa denda berhasil diselesaikan.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        }
    }

    /**
     * Finalisasi Check-Out & Pembayaran Pengembalian Deposit
     */
    public function finalizeRefund(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageFinances()) {
            return $this->errorResponse('Hanya manajer atau pemilik kos yang berwenang memfinalisasi pengembalian dana.', 403);
        }

        $settlement = $this->findAuthorizedSettlement($id, $user);
        if (!$settlement) {
            return $this->errorResponse('Settlement tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'refund_method' => ['required', 'string', 'max:50'],
            'refund_destination' => [(float)$settlement->net_refund_amount > 0 ? 'required' : 'nullable', 'string', 'max:150'],
            'refund_payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $finalized = $this->depositService->finalizeCheckOutAndRefund(
                $settlement,
                $user,
                $validated
            );

            return $this->successResponse($finalized, 'Check-out dan refund deposit berhasil diselesaikan.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    protected function findAuthorizedLedger(int $id, $user): ?DepositLedger
    {
        $query = DepositLedger::where('id', $id);

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        return $query->first();
    }

    protected function findAuthorizedSettlement(int $id, $user): ?CheckOutSettlement
    {
        $query = CheckOutSettlement::where('id', $id);

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        return $query->first();
    }
}
