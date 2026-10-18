<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Property;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingApiController extends ApiController
{
    public function __construct(
        protected ChartOfAccountService $coaService,
        protected JournalPostingService $journalService,
        protected AccountingReportService $reportService
    ) {}

    /**
     * Otorisasi properti untuk API
     */
    protected function checkPropertyAccess(User $user, int $propertyId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isTenant()) {
            return false;
        }

        return $user->properties()->where('properties.id', $propertyId)->exists();
    }

    /**
     * Dapatkan daftar ID properti yang diizinkan untuk pengguna
     */
    protected function getAuthorizedPropertyIds(User $user, ?int $requestedPropertyId = null): array
    {
        if ($user->isTenant()) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            if ($requestedPropertyId) {
                return [$requestedPropertyId];
            }
            return Property::active()->pluck('id')->toArray();
        }

        $allowed = $user->properties()->where('properties.is_active', true)->pluck('properties.id')->toArray();
        if ($requestedPropertyId) {
            return in_array($requestedPropertyId, $allowed) ? [$requestedPropertyId] : [];
        }

        return $allowed;
    }

    /**
     * 1. GET /api/v1/finance/accounting/coa
     */
    public function coa(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Penyewa tidak memiliki wewenang mengakses bagan akun.', 403);
        }

        $propertyId = (int) $request->query('property_id');
        if (!$propertyId) {
            $allowed = $this->getAuthorizedPropertyIds($user);
            if (empty($allowed)) {
                return $this->errorResponse('Tidak ada properti yang ditugaskan ke akun Anda.', 403);
            }
            $propertyId = $allowed[0];
        }

        if (!$this->checkPropertyAccess($user, $propertyId)) {
            return $this->errorResponse('Anda tidak memiliki izin mengakses data properti ini.', 403);
        }

        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $query = Account::where('property_id', $propertyId);

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $accounts = $query->orderBy('code')->get();

        return $this->successResponse($accounts, 'Daftar bagan akun berhasil diambil.');
    }

    /**
     * 2. POST /api/v1/finance/accounting/coa
     */
    public function storeAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant() || $user->isStaff()) {
            return $this->errorResponse('Hanya Pemilik atau Manajer yang berwenang menambah akun.', 403);
        }

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'nullable|string|in:debit,credit',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        if (!$this->checkPropertyAccess($user, (int) $validated['property_id'])) {
            return $this->errorResponse('Akses properti ditolak.', 403);
        }

        try {
            $account = $this->coaService->createAccount((int) $validated['property_id'], $validated);
            return $this->successResponse($account, 'Akun baru berhasil dibuat.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * 3. GET /api/v1/finance/accounting/journals
     */
    public function journals(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Penyewa tidak memiliki wewenang mengakses jurnal umum.', 403);
        }

        $propertyId = $request->query('property_id') ? (int) $request->query('property_id') : null;
        $allowedIds = $this->getAuthorizedPropertyIds($user, $propertyId);

        if (empty($allowedIds)) {
            return $this->errorResponse('Tidak ada properti yang diizinkan.', 403);
        }

        $query = JournalEntry::with(['lines.account', 'creator', 'property'])
            ->whereIn('property_id', $allowedIds);

        if ($request->filled('start_date')) {
            $query->whereDate('entry_date', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('entry_date', '<=', $request->query('end_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->query('source_type'));
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));
        $journals = $query->orderBy('entry_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse($journals, 'Daftar jurnal berhasil diambil.');
    }

    /**
     * 4. GET /api/v1/finance/accounting/journals/{id}
     */
    public function journalDetail(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $journal = JournalEntry::with(['lines.account', 'creator', 'property', 'reversedJournal'])->findOrFail($id);

        if (!$this->checkPropertyAccess($user, $journal->property_id)) {
            return $this->errorResponse('Akses properti ditolak.', 403);
        }

        return $this->successResponse($journal, 'Rincian jurnal berhasil diambil.');
    }

    /**
     * 5. POST /api/v1/finance/accounting/journals/{id}/reverse
     */
    public function reverseJournal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant() || $user->isStaff()) {
            return $this->errorResponse('Hanya Pemilik atau Manajer yang dapat melakukan pembalikan jurnal.', 403);
        }

        $journal = JournalEntry::findOrFail($id);
        if (!$this->checkPropertyAccess($user, $journal->property_id)) {
            return $this->errorResponse('Akses properti ditolak.', 403);
        }

        $validated = $request->validate([
            'reversal_reason' => 'required|string|min:5|max:255',
        ]);

        try {
            $reversal = $this->journalService->reverseJournal($journal, $user, $validated['reversal_reason']);
            return $this->successResponse($reversal, 'Jurnal pembalik berhasil dibuat.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * 6. GET /api/v1/finance/accounting/reports/trial-balance
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses laporan akuntansi ditolak.', 403);
        }

        $propertyId = (int) $request->query('property_id');
        if (!$propertyId) {
            $allowed = $this->getAuthorizedPropertyIds($user);
            $propertyId = $allowed[0] ?? 0;
        }

        if (!$this->checkPropertyAccess($user, $propertyId)) {
            return $this->errorResponse('Akses properti ditolak.', 403);
        }

        $asOfDate = $request->query('as_of_date', now()->toDateString());
        $startDate = $request->query('start_date');

        $data = $this->reportService->getTrialBalance($propertyId, $asOfDate, $startDate);

        return $this->successResponse($data, 'Laporan neraca saldo berhasil diambil.');
    }

    /**
     * 7. GET /api/v1/finance/accounting/reports/income-statement
     */
    public function incomeStatement(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses laporan akuntansi ditolak.', 403);
        }

        $propertyId = (int) $request->query('property_id');
        if (!$propertyId) {
            $allowed = $this->getAuthorizedPropertyIds($user);
            $propertyId = $allowed[0] ?? 0;
        }

        if (!$this->checkPropertyAccess($user, $propertyId)) {
            return $this->errorResponse('Akses properti ditolak.', 403);
        }

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $data = $this->reportService->getIncomeStatement($propertyId, $startDate, $endDate);

        return $this->successResponse($data, 'Laporan laba rugi berhasil diambil.');
    }

    /**
     * 8. GET /api/v1/finance/accounting/reports/balance-sheet
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant() || $user->isStaff()) {
            return $this->errorResponse('Akses neraca keuangan terbatas untuk manajemen.', 403);
        }

        $propertyId = (int) $request->query('property_id');
        if (!$propertyId) {
            $allowed = $this->getAuthorizedPropertyIds($user);
            $propertyId = $allowed[0] ?? 0;
        }

        if (!$this->checkPropertyAccess($user, $propertyId)) {
            return $this->errorResponse('Akses properti ditolak.', 403);
        }

        $asOfDate = $request->query('as_of_date', now()->toDateString());
        $data = $this->reportService->getBalanceSheet($propertyId, $asOfDate);

        return $this->successResponse($data, 'Laporan neraca keuangan berhasil diambil.');
    }

    /**
     * 9. GET /api/v1/finance/accounting/reports/reconciliation
     */
    public function reconciliation(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant() || $user->isStaff()) {
            return $this->errorResponse('Akses rekonsiliasi terbatas untuk manajemen.', 403);
        }

        $propertyId = (int) $request->query('property_id');
        if (!$propertyId) {
            $allowed = $this->getAuthorizedPropertyIds($user);
            $propertyId = $allowed[0] ?? 0;
        }

        if (!$this->checkPropertyAccess($user, $propertyId)) {
            return $this->errorResponse('Akses properti ditolak.', 403);
        }

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $data = $this->reportService->getReconciliationReport($propertyId, $startDate, $endDate);

        return $this->successResponse($data, 'Laporan rekonsiliasi subledger berhasil diambil.');
    }
}
