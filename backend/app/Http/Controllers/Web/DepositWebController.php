<?php

namespace App\Http\Controllers\Web;

use App\Enums\DepositStatus;
use App\Enums\InspectionType;
use App\Enums\Role;
use App\Enums\SettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CheckOutSettlement;
use App\Models\DepositLedger;
use App\Models\Inspection;
use App\Models\Property;
use App\Services\Deposit\DepositService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DepositWebController extends Controller
{
    public function __construct(
        protected DepositService $depositService
    ) {}

    /**
     * Halaman Utama Daftar Subledger Deposit
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $query = DepositLedger::with(['property', 'booking.room.roomType', 'tenant', 'latestSettlement'])
            ->latest();

        // Otorisasi dan Scoping Properti Multi-Tenant
        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereIn('property_id', $propertyIds);
        }

        // Hitung Summary KPIs
        $kpiQuery = clone $query;
        $kpis = [
            'total_held_amount' => (float) (clone $kpiQuery)->where('status', DepositStatus::HELD->value)->sum('current_balance'),
            'count_held' => (clone $kpiQuery)->where('status', DepositStatus::HELD->value)->count(),
            'count_pending' => (clone $kpiQuery)->where('status', DepositStatus::PENDING->value)->count(),
            'count_settled' => (clone $kpiQuery)->whereIn('status', [DepositStatus::SETTLED->value, DepositStatus::REFUNDED->value])->count(),
            'total_refunded' => (float) (clone $kpiQuery)->sum('total_refunded'),
        ];

        // Filters
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

        $ledgers = $query->paginate(15)->withQueryString();

        // Data Properti untuk filter
        if ($user->isSuperAdmin()) {
            $properties = Property::where('is_active', true)->orderBy('name')->get();
        } elseif ($user->isTenant()) {
            $properties = collect();
        } else {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $properties = Property::whereIn('id', $propertyIds)->where('is_active', true)->orderBy('name')->get();
        }

        return view('dashboard.deposits.index', compact('ledgers', 'kpis', 'properties'));
    }

    /**
     * Halaman Detail Subledger Deposit & Histori Mutasi
     */
    public function show(int $id): View
    {
        $user = auth()->user();
        $raw = DepositLedger::find($id);
        if (!$raw) {
            abort(404, 'Data uang jaminan (deposit) tidak ditemukan.');
        }

        $ledger = $this->findAuthorizedLedger($id, $user);
        if (!$ledger) {
            abort(403, 'Anda tidak memiliki hak akses ke data uang jaminan ini.');
        }

        $ledger->load([
            'property',
            'booking.room.roomType',
            'tenant',
            'mutations.performer',
            'mutations.inspection',
            'settlements.inspection.inspector',
            'settlements.deductions',
        ]);

        return view('dashboard.deposits.show', compact('ledger'));
    }

    /**
     * Input Penyesuaian Koreksi Manual (Drawer)
     */
    public function recordAdjustment(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        if (!$user->canManageFinances()) {
            abort(403, 'Hanya manajer atau pemilik kos yang berwenang melakukan koreksi audit deposit.');
        }

        $ledger = $this->findAuthorizedLedger($id, $user);
        if (!$ledger) {
            abort(404, 'Ledger deposit tidak ditemukan.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'type' => ['required', 'in:adjustment_credit,adjustment_debit'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->depositService->recordAdjustment(
                $ledger,
                (float) $validated['amount'],
                $validated['type'],
                $user,
                $validated['reason']
            );

            return back()->with('success', 'Koreksi audit deposit berhasil dicatat dalam riwayat mutasi.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Halaman Daftar Check-Out Settlements
     */
    public function settlementsIndex(Request $request): View
    {
        $user = auth()->user();

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

        $settlements = $query->paginate(15)->withQueryString();

        // Ambil daftar booking yang sedang aktif atau overstay untuk modal inisiasi check-out
        $activeBookingsQuery = Booking::with(['room.roomType', 'property', 'user', 'depositLedger'])
            ->whereIn('status', [\App\Enums\BookingStatus::ACTIVE, \App\Enums\BookingStatus::OVERSTAY, 'active', 'overstay']);

        if (!$user->isSuperAdmin() && !$user->isTenant()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $activeBookingsQuery->whereIn('property_id', $propertyIds);
        } elseif ($user->isTenant()) {
            $activeBookingsQuery->where('user_id', $user->id);
        }

        $activeBookings = $activeBookingsQuery->get();

        // Ambil daftar inspeksi check-out yang belum di-settle
        $checkOutInspections = Inspection::where('type', InspectionType::CHECK_OUT)
            ->whereIn('status', ['completed', 'approved_by_tenant', 'objected_by_tenant'])
            ->latest()
            ->limit(30)
            ->get();

        return view('dashboard.deposits.settlements_index', compact('settlements', 'activeBookings', 'checkOutInspections'));
    }

    /**
     * Halaman Detail & Wizard Penyelesaian Check-Out Settlement
     */
    public function settlementShow(int $id): View
    {
        $user = auth()->user();
        $raw = CheckOutSettlement::find($id);
        if (!$raw) {
            abort(404, 'Data penyelesaian check-out tidak ditemukan.');
        }

        $settlement = $this->findAuthorizedSettlement($id, $user);
        if (!$settlement) {
            abort(403, 'Anda tidak memiliki akses ke settlement check-out ini.');
        }

        $settlement->load([
            'booking.room.roomType',
            'property',
            'tenant',
            'depositLedger.mutations',
            'inspection.items.roomAsset',
            'deductions.inspectionItem',
            'resolver',
            'refunder',
            'creator',
        ]);

        return view('dashboard.deposits.settlement_show', compact('settlement'));
    }

    /**
     * Inisiasi Check-Out Settlement Baru
     */
    public function initiateSettlement(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
            'check_out_date' => ['required', 'date'],
            'inspection_id' => ['nullable', 'exists:inspections,id'],
            'refund_destination' => ['nullable', 'string', 'max:150'],
            'custom_deduction_title' => ['nullable', 'string', 'max:150'],
            'custom_deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'custom_deduction_desc' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        // Hak akses: staf properti atau penyewa itu sendiri
        if ($user->isTenant() && $booking->user_id !== $user->id) {
            abort(403, 'Anda tidak berhak mengajukan check-out untuk pemesanan ini.');
        }

        $payload = [
            'check_out_date' => $validated['check_out_date'],
            'inspection_id' => $validated['inspection_id'] ?? null,
            'refund_destination' => $validated['refund_destination'] ?? null,
            'custom_deductions' => [],
        ];

        if (!empty($validated['custom_deduction_title']) && !empty($validated['custom_deduction_amount'])) {
            $payload['custom_deductions'][] = [
                'title' => $validated['custom_deduction_title'],
                'amount' => (float) $validated['custom_deduction_amount'],
                'description' => $validated['custom_deduction_desc'] ?? null,
            ];
        }

        try {
            $settlement = $this->depositService->initiateCheckOutSettlement($booking, $user, $payload);
            return redirect()->route('dashboard.deposits.settlements.show', $settlement->id)
                ->with('success', "Proses check-out settlement ({$settlement->settlement_code}) berhasil dibuat.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Penyewa memberikan persetujuan atau mengajukan keberatan terhadap usulan denda
     */
    public function submitTenantDecision(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $settlement = $this->findAuthorizedSettlement($id, $user);

        if (!$settlement) {
            abort(404, 'Settlement tidak ditemukan.');
        }

        // Validasi bahwa user adalah tenant dari settlement ini atau super admin
        if ($settlement->tenant_id !== $user->id && !$user->isSuperAdmin()) {
            abort(403, 'Hanya penyewa yang bersangkutan yang dapat memberikan respon.');
        }

        $decision = $request->input('decision'); // 'approve' atau 'dispute'
        $isApproved = ($decision === 'approve');
        $objectionNotes = $request->input('objection_notes');
        $disputedIds = $request->input('disputed_deductions', []);

        try {
            $this->depositService->submitTenantDecision(
                $settlement,
                $user,
                $isApproved,
                $objectionNotes,
                is_array($disputedIds) ? $disputedIds : []
            );

            $msg = $isApproved
                ? 'Terima kasih, Anda telah menyetujui rincian settlement deposit.'
                : 'Keberatan Anda telah tercatat dan akan ditinjau oleh pihak manajemen.';

            return back()->with('success', $msg);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Penyelesaian Sengketa Denda oleh Manajer / Pemilik Kos
     */
    public function resolveDispute(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        if (!$user->canManageFinances()) {
            abort(403, 'Hanya manajer atau pemilik kos yang berwenang menyelesaikan sengketa denda.');
        }

        $settlement = $this->findAuthorizedSettlement($id, $user);
        if (!$settlement) {
            abort(404, 'Settlement tidak ditemukan.');
        }

        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:1000'],
            'approved_amounts' => ['required', 'array'],
        ]);

        try {
            $this->depositService->resolveSettlementDispute(
                $settlement,
                $user,
                $validated['approved_amounts'],
                $validated['resolution_notes']
            );

            return back()->with('success', 'Sengketa potongan denda berhasil diselesaikan dan status disahkan.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Finalisasi Check-Out & Pembayaran Pengembalian Deposit
     */
    public function finalizeRefund(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        if (!$user->canManageFinances()) {
            abort(403, 'Hanya manajer atau pemilik kos yang berwenang memfinalisasi pengembalian dana.');
        }

        $settlement = $this->findAuthorizedSettlement($id, $user);
        if (!$settlement) {
            abort(404, 'Settlement tidak ditemukan.');
        }

        $validated = $request->validate([
            'refund_method' => ['required', 'string', 'max:50'],
            'refund_destination' => [(float)$settlement->net_refund_amount > 0 ? 'required' : 'nullable', 'string', 'max:150'],
            'refund_payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->depositService->finalizeCheckOutAndRefund(
                $settlement,
                $user,
                $validated
            );

            return redirect()->route('dashboard.deposits.settlements.show', $settlement->id)
                ->with('success', 'Check-out berhasil difinalisasi! Sisa uang jaminan telah dicatat keluar dan kamar telah dialihkan ke status Kotor (Perlu Housekeeping).');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
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
