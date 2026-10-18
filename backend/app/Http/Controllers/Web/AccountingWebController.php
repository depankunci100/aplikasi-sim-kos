<?php

namespace App\Http\Controllers\Web;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Property;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingWebController extends Controller
{
    public function __construct(
        protected ChartOfAccountService $coaService,
        protected JournalPostingService $journalService,
        protected AccountingReportService $reportService
    ) {}

    /**
     * Helper: Dapatkan ID properti yang diizinkan untuk pengguna saat ini
     */
    protected function getAuthorizedProperties(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Property::active()->orderBy('name')->get()->all();
        }

        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki wewenang mengakses modul akuntansi.');
        }

        return $user->properties()->where('properties.is_active', true)->orderBy('name')->get()->all();
    }

    /**
     * Helper: Validasi kepemilikan/akses properti tertentu
     */
    protected function authorizePropertyAccess(User $user, int $propertyId): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isTenant()) {
            abort(403, 'Akses akuntansi ditolak.');
        }

        $hasAccess = $user->properties()->where('properties.id', $propertyId)->exists();
        if (!$hasAccess) {
            abort(403, 'Anda tidak memiliki hak akses ke data akuntansi properti ini.');
        }
    }

    /**
     * 1. Halaman Bagan Akun (Chart of Accounts)
     */
    public function coa(Request $request): View
    {
        $user = Auth::user();
        $properties = $this->getAuthorizedProperties($user);

        if (empty($properties)) {
            abort(403, 'Tidak ada properti aktif yang ditugaskan ke akun Anda.');
        }

        $selectedPropertyId = (int) ($request->query('property_id') ?: $properties[0]->id);
        $this->authorizePropertyAccess($user, $selectedPropertyId);

        // Pastikan akun bawaan terinisialisasi
        $this->coaService->ensureDefaultAccountsForProperty($selectedPropertyId);

        $query = Account::where('property_id', $selectedPropertyId);

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        if ($request->filled('search')) {
            $s = trim($request->query('search'));
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%{$s}%")
                  ->orWhere('name', 'like', "%{$s}%");
            });
        }

        $accounts = $query->orderBy('code')->get();

        return view('dashboard.accounting.coa', [
            'properties' => $properties,
            'selectedPropertyId' => $selectedPropertyId,
            'accounts' => $accounts,
            'accountTypes' => AccountType::cases(),
            'normalBalances' => AccountNormalBalance::cases(),
        ]);
    }

    /**
     * Tambah Akun Baru
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'nullable|string|in:debit,credit',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $this->authorizePropertyAccess($user, (int) $validated['property_id']);

        try {
            $account = $this->coaService->createAccount((int) $validated['property_id'], $validated);
            return back()->with('success', "Akun '{$account->code} - {$account->name}' berhasil ditambahkan.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Perbarui Akun
     */
    public function updateAccount(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        $account = Account::findOrFail($id);
        $this->authorizePropertyAccess($user, $account->property_id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_active' => 'required|boolean',
        ]);

        try {
            $this->coaService->updateAccount($account, $validated);
            return back()->with('success', "Akun '{$account->code}' berhasil diperbarui.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * 2. Halaman Jurnal Umum (Journal Entries List)
     */
    public function journals(Request $request): View
    {
        $user = Auth::user();
        $properties = $this->getAuthorizedProperties($user);

        if (empty($properties)) {
            abort(403, 'Tidak ada properti aktif.');
        }

        $selectedPropertyId = (int) ($request->query('property_id') ?: $properties[0]->id);
        $this->authorizePropertyAccess($user, $selectedPropertyId);

        $query = JournalEntry::with(['lines.account', 'creator', 'property'])
            ->where('property_id', $selectedPropertyId);

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

        if ($request->filled('search')) {
            $s = trim($request->query('search'));
            $query->where(function ($q) use ($s) {
                $q->where('journal_number', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $journals = $query->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.accounting.journals_index', [
            'properties' => $properties,
            'selectedPropertyId' => $selectedPropertyId,
            'journals' => $journals,
            'journalStatuses' => JournalEntryStatus::cases(),
        ]);
    }

    /**
     * Rincian Jurnal (JSON untuk drawer/modal)
     */
    public function journalDetail(int $id)
    {
        $user = Auth::user();
        $journal = JournalEntry::with(['lines.account', 'creator', 'property', 'reversedJournal'])->findOrFail($id);
        $this->authorizePropertyAccess($user, $journal->property_id);

        return response()->json([
            'success' => true,
            'data' => $journal,
        ]);
    }

    /**
     * Pembalikan Jurnal (Reversal)
     */
    public function reverseJournal(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if ($user->isStaff() || $user->isTenant()) {
            abort(403, 'Hanya Pemilik atau Manajer yang dapat melakukan pembalikan jurnal.');
        }

        $journal = JournalEntry::findOrFail($id);
        $this->authorizePropertyAccess($user, $journal->property_id);

        $validated = $request->validate([
            'reversal_reason' => 'required|string|min:5|max:255',
        ]);

        try {
            $reversal = $this->journalService->reverseJournal($journal, $user, $validated['reversal_reason']);
            return back()->with('success', "Jurnal #{$journal->journal_number} berhasil dibalik dengan Jurnal Pembalik #{$reversal->journal_number}.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * 3. Neraca Saldo (Trial Balance)
     */
    public function trialBalance(Request $request): View|StreamedResponse
    {
        $user = Auth::user();
        $properties = $this->getAuthorizedProperties($user);
        $selectedPropertyId = (int) ($request->query('property_id') ?: $properties[0]->id);
        $this->authorizePropertyAccess($user, $selectedPropertyId);

        $asOfDate = $request->query('as_of_date', now()->toDateString());
        $startDate = $request->query('start_date');

        $data = $this->reportService->getTrialBalance($selectedPropertyId, $asOfDate, $startDate);

        if ($request->query('export') === 'csv') {
            return $this->exportTrialBalanceCsv($data);
        }

        return view('dashboard.accounting.trial_balance', [
            'properties' => $properties,
            'selectedPropertyId' => $selectedPropertyId,
            'data' => $data,
            'asOfDate' => $asOfDate,
            'startDate' => $startDate,
        ]);
    }

    /**
     * 4. Laporan Laba Rugi (Income Statement)
     */
    public function incomeStatement(Request $request): View|StreamedResponse
    {
        $user = Auth::user();
        $properties = $this->getAuthorizedProperties($user);
        $selectedPropertyId = (int) ($request->query('property_id') ?: $properties[0]->id);
        $this->authorizePropertyAccess($user, $selectedPropertyId);

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $data = $this->reportService->getIncomeStatement($selectedPropertyId, $startDate, $endDate);

        if ($request->query('export') === 'csv') {
            return $this->exportIncomeStatementCsv($data);
        }

        return view('dashboard.accounting.income_statement', [
            'properties' => $properties,
            'selectedPropertyId' => $selectedPropertyId,
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * 5. Neraca Keuangan (Balance Sheet)
     */
    public function balanceSheet(Request $request): View|StreamedResponse
    {
        $user = Auth::user();
        $properties = $this->getAuthorizedProperties($user);
        $selectedPropertyId = (int) ($request->query('property_id') ?: $properties[0]->id);
        $this->authorizePropertyAccess($user, $selectedPropertyId);

        $asOfDate = $request->query('as_of_date', now()->toDateString());

        $data = $this->reportService->getBalanceSheet($selectedPropertyId, $asOfDate);

        if ($request->query('export') === 'csv') {
            return $this->exportBalanceSheetCsv($data);
        }

        return view('dashboard.accounting.balance_sheet', [
            'properties' => $properties,
            'selectedPropertyId' => $selectedPropertyId,
            'data' => $data,
            'asOfDate' => $asOfDate,
        ]);
    }

    /**
     * 6. Laporan Rekonsiliasi Subledger
     */
    public function reconciliation(Request $request): View
    {
        $user = Auth::user();
        $properties = $this->getAuthorizedProperties($user);
        $selectedPropertyId = (int) ($request->query('property_id') ?: $properties[0]->id);
        $this->authorizePropertyAccess($user, $selectedPropertyId);

        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());

        $data = $this->reportService->getReconciliationReport($selectedPropertyId, $startDate, $endDate);

        return view('dashboard.accounting.reconciliation', [
            'properties' => $properties,
            'selectedPropertyId' => $selectedPropertyId,
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * CSV Exporter aman untuk Trial Balance
     */
    protected function exportTrialBalanceCsv(array $data): StreamedResponse
    {
        $filename = "neraca_saldo_" . str_replace('-', '', $data['period']['as_of_date']) . ".csv";

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['NERACA SALDO (TRIAL BALANCE) - SIM KOS & GUESTHOUSE']);
            fputcsv($handle, ['Properti', $data['property']['name']]);
            fputcsv($handle, ['Per Tanggal', $data['period']['as_of_date']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Klasifikasi', 'Sifat Normal', 'Debit Periode', 'Kredit Periode', 'Saldo Akhir Debit', 'Saldo Akhir Kredit']);

            foreach ($data['rows'] as $r) {
                fputcsv($handle, [
                    $r['code'],
                    $r['name'],
                    $r['type_label'],
                    strtoupper($r['normal_balance']),
                    $r['total_debit'],
                    $r['total_credit'],
                    $r['ending_debit'],
                    $r['ending_credit'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['TOTAL', '', '', '', '', '', $data['summary']['total_debit'], $data['summary']['total_credit']]);
            fputcsv($handle, ['STATUS KESEIMBANGAN', $data['summary']['is_balanced'] ? 'SEIMBANG (BALANCED)' : 'SELISIH (TIDAK SEIMBANG)', '', '', '', '', '', $data['summary']['difference']]);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * CSV Exporter aman untuk Income Statement
     */
    protected function exportIncomeStatementCsv(array $data): StreamedResponse
    {
        $filename = "laba_rugi_" . str_replace('-', '', $data['period']['start_date']) . "_" . str_replace('-', '', $data['period']['end_date']) . ".csv";

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['LAPORAN LABA RUGI (INCOME STATEMENT) - SIM KOS & GUESTHOUSE']);
            fputcsv($handle, ['Properti', $data['property']['name']]);
            fputcsv($handle, ['Periode', "{$data['period']['start_date']} s/d {$data['period']['end_date']}"]);
            fputcsv($handle, []);
            fputcsv($handle, ['PENDAPATAN USAHA (REVENUE)']);
            fputcsv($handle, ['Kode', 'Keterangan', 'Nominal (Rp)']);
            foreach ($data['revenues'] as $rev) {
                fputcsv($handle, [$rev['code'], $rev['name'], $rev['amount']]);
            }
            fputcsv($handle, ['TOTAL PENDAPATAN', '', $data['total_revenue']]);
            fputcsv($handle, []);
            fputcsv($handle, ['BEBAN OPERASIONAL (EXPENSES)']);
            fputcsv($handle, ['Kode', 'Keterangan', 'Nominal (Rp)']);
            foreach ($data['expenses'] as $exp) {
                fputcsv($handle, [$exp['code'], $exp['name'], $exp['amount']]);
            }
            fputcsv($handle, ['TOTAL BEBAN OPERASIONAL', '', $data['total_expense']]);
            fputcsv($handle, []);
            fputcsv($handle, ['LABA / (RUGI) BERSIH OPERASIONAL', '', $data['net_income']]);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * CSV Exporter aman untuk Balance Sheet
     */
    protected function exportBalanceSheetCsv(array $data): StreamedResponse
    {
        $filename = "neraca_keuangan_" . str_replace('-', '', $data['as_of_date']) . ".csv";

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['NERACA KEUANGAN (BALANCE SHEET) - SIM KOS & GUESTHOUSE']);
            fputcsv($handle, ['Properti', $data['property']['name']]);
            fputcsv($handle, ['Per Tanggal', $data['as_of_date']]);
            fputcsv($handle, []);
            fputcsv($handle, ['ASET (ASSETS)']);
            fputcsv($handle, ['Kode', 'Nama Akun', 'Saldo (Rp)']);
            foreach ($data['assets']['rows'] as $r) {
                fputcsv($handle, [$r['code'], $r['name'], $r['amount']]);
            }
            fputcsv($handle, ['TOTAL ASET', '', $data['assets']['total']]);
            fputcsv($handle, []);
            fputcsv($handle, ['KEWAJIBAN (LIABILITIES)']);
            fputcsv($handle, ['Kode', 'Nama Akun', 'Saldo (Rp)']);
            foreach ($data['liabilities']['rows'] as $r) {
                fputcsv($handle, [$r['code'], $r['name'], $r['amount']]);
            }
            fputcsv($handle, ['TOTAL KEWAJIBAN', '', $data['liabilities']['total']]);
            fputcsv($handle, []);
            fputcsv($handle, ['EKUITAS (EQUITY)']);
            fputcsv($handle, ['Kode', 'Nama Akun', 'Saldo (Rp)']);
            foreach ($data['equity']['rows'] as $r) {
                fputcsv($handle, [$r['code'], $r['name'], $r['amount']]);
            }
            fputcsv($handle, ['TOTAL EKUITAS', '', $data['equity']['total']]);
            fputcsv($handle, []);
            fputcsv($handle, ['TOTAL KEWAJIBAN & EKUITAS', '', $data['total_liabilities_and_equity']]);
            fputcsv($handle, ['STATUS KESEIMBANGAN', $data['is_balanced'] ? 'SEIMBANG (BALANCED)' : 'SELISIH', $data['difference']]);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
