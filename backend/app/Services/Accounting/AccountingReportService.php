<?php

namespace App\Services\Accounting;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\CheckOutSettlement;
use App\Models\DepositLedger;
use App\Models\ExpensePayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    public function __construct(
        protected ChartOfAccountService $coaService
    ) {}

    /**
     * 1. Neraca Saldo (Trial Balance)
     */
    public function getTrialBalance(int $propertyId, ?string $asOfDate = null, ?string $startDate = null): array
    {
        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $property = Property::findOrFail($propertyId);
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : now()->endOfDay();
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;

        $accounts = Account::where('property_id', $propertyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0.00;
        $totalCredit = 0.00;

        foreach ($accounts as $acc) {
            $query = JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $start, $asOf) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '<=', $asOf->toDateString());

                    if ($start) {
                        $q->whereDate('entry_date', '>=', $start->toDateString());
                    }
                });

            $sumDebit = round((float) (clone $query)->sum('debit'), 2);
            $sumCredit = round((float) (clone $query)->sum('credit'), 2);

            // Hitung Saldo Akhir berdasarkan Normal Balance
            if ($acc->normal_balance === AccountNormalBalance::DEBIT) {
                $netBalance = round($sumDebit - $sumCredit, 2);
                $endingDebit = $netBalance >= 0 ? $netBalance : 0.00;
                $endingCredit = $netBalance < 0 ? abs($netBalance) : 0.00;
            } else {
                $netBalance = round($sumCredit - $sumDebit, 2);
                $endingCredit = $netBalance >= 0 ? $netBalance : 0.00;
                $endingDebit = $netBalance < 0 ? abs($netBalance) : 0.00;
            }

            if ($sumDebit > 0 || $sumCredit > 0 || $acc->is_system) {
                $rows[] = [
                    'account_id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'type' => $acc->type->value,
                    'type_label' => $acc->type->label(),
                    'normal_balance' => $acc->normal_balance->value,
                    'total_debit' => $sumDebit,
                    'total_credit' => $sumCredit,
                    'ending_debit' => $endingDebit,
                    'ending_credit' => $endingCredit,
                    'net_balance' => $netBalance,
                ];

                $totalDebit += $endingDebit;
                $totalCredit += $endingCredit;
            }
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $isBalanced = abs($totalDebit - $totalCredit) < 0.001;

        return [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
            ],
            'period' => [
                'start_date' => $start?->toDateString(),
                'as_of_date' => $asOf->toDateString(),
            ],
            'rows' => $rows,
            'summary' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_balanced' => $isBalanced,
                'difference' => round(abs($totalDebit - $totalCredit), 2),
            ],
        ];
    }

    /**
     * 2. Laporan Laba Rugi (Income Statement)
     */
    public function getIncomeStatement(int $propertyId, ?string $startDate = null, ?string $endDate = null): array
    {
        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $property = Property::findOrFail($propertyId);
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfMonth()->endOfDay();

        // 1. Ambil Akun Pendapatan (Revenue - 4xxx)
        $revenueAccounts = Account::where('property_id', $propertyId)
            ->where('type', AccountType::REVENUE->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $revenueRows = [];
        $totalRevenue = 0.00;

        foreach ($revenueAccounts as $acc) {
            $sumCredit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $start, $end) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '>=', $start->toDateString())
                      ->whereDate('entry_date', '<=', $end->toDateString());
                })
                ->sum('credit');

            $sumDebit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $start, $end) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '>=', $start->toDateString())
                      ->whereDate('entry_date', '<=', $end->toDateString());
                })
                ->sum('debit');

            $netAmount = round($sumCredit - $sumDebit, 2);

            $revenueRows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'amount' => $netAmount,
            ];

            $totalRevenue += $netAmount;
        }

        // 2. Ambil Akun Beban Operasional (Expense - 5xxx)
        $expenseAccounts = Account::where('property_id', $propertyId)
            ->where('type', AccountType::EXPENSE->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $expenseRows = [];
        $totalExpense = 0.00;

        foreach ($expenseAccounts as $acc) {
            $sumDebit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $start, $end) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '>=', $start->toDateString())
                      ->whereDate('entry_date', '<=', $end->toDateString());
                })
                ->sum('debit');

            $sumCredit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $start, $end) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '>=', $start->toDateString())
                      ->whereDate('entry_date', '<=', $end->toDateString());
                })
                ->sum('credit');

            $netAmount = round($sumDebit - $sumCredit, 2);

            $expenseRows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'amount' => $netAmount,
            ];

            $totalExpense += $netAmount;
        }

        $totalRevenue = round($totalRevenue, 2);
        $totalExpense = round($totalExpense, 2);
        $netIncome = round($totalRevenue - $totalExpense, 2);

        return [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
            ],
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'revenues' => $revenueRows,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenseRows,
            'total_expense' => $totalExpense,
            'net_income' => $netIncome,
        ];
    }

    /**
     * 3. Neraca Keuangan (Balance Sheet)
     */
    public function getBalanceSheet(int $propertyId, ?string $asOfDate = null): array
    {
        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $property = Property::findOrFail($propertyId);
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : now()->endOfDay();

        // 1. Aset (1xxx)
        $assetAccounts = Account::where('property_id', $propertyId)
            ->where('type', AccountType::ASSET->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $assetRows = [];
        $totalAssets = 0.00;

        foreach ($assetAccounts as $acc) {
            $sumDebit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $asOf) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '<=', $asOf->toDateString());
                })
                ->sum('debit');

            $sumCredit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $asOf) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '<=', $asOf->toDateString());
                })
                ->sum('credit');

            $balance = round($sumDebit - $sumCredit, 2);

            $assetRows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'category' => $acc->category,
                'amount' => $balance,
            ];

            $totalAssets += $balance;
        }

        // 2. Kewajiban (2xxx)
        $liabilityAccounts = Account::where('property_id', $propertyId)
            ->where('type', AccountType::LIABILITY->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $liabilityRows = [];
        $totalLiabilities = 0.00;

        foreach ($liabilityAccounts as $acc) {
            $sumCredit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $asOf) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '<=', $asOf->toDateString());
                })
                ->sum('credit');

            $sumDebit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $asOf) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '<=', $asOf->toDateString());
                })
                ->sum('debit');

            $balance = round($sumCredit - $sumDebit, 2);

            $liabilityRows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'category' => $acc->category,
                'amount' => $balance,
            ];

            $totalLiabilities += $balance;
        }

        // 3. Ekuitas (3xxx) + Laba Bersih Periode Berjalan
        $equityAccounts = Account::where('property_id', $propertyId)
            ->where('type', AccountType::EQUITY->value)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $equityRows = [];
        $totalEquityWithoutCurrentEarnings = 0.00;

        foreach ($equityAccounts as $acc) {
            $sumCredit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $asOf) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '<=', $asOf->toDateString());
                })
                ->sum('credit');

            $sumDebit = (float) JournalLine::where('account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($propertyId, $asOf) {
                    $q->where('property_id', $propertyId)
                      ->where('status', JournalEntryStatus::POSTED->value)
                      ->whereDate('entry_date', '<=', $asOf->toDateString());
                })
                ->sum('debit');

            $balance = round($sumCredit - $sumDebit, 2);

            $equityRows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'amount' => $balance,
            ];

            $totalEquityWithoutCurrentEarnings += $balance;
        }

        // Hitung Laba/Rugi Kumulatif s/d tanggal as_of (dari pendapatan & beban yang belum ditutup ke modal)
        $incStmt = $this->getIncomeStatement($propertyId, '2000-01-01', $asOf->toDateString());
        $currentEarnings = (float) $incStmt['net_income'];

        $equityRows[] = [
            'account_id' => null,
            'code' => '3300',
            'name' => 'Laba Bersih Tahun Berjalan',
            'amount' => $currentEarnings,
        ];

        $totalEquity = round($totalEquityWithoutCurrentEarnings + $currentEarnings, 2);
        $totalAssets = round($totalAssets, 2);
        $totalLiabilities = round($totalLiabilities, 2);
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);
        $isBalanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.001;

        return [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
            ],
            'as_of_date' => $asOf->toDateString(),
            'assets' => [
                'rows' => $assetRows,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'rows' => $liabilityRows,
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'rows' => $equityRows,
                'total' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => $isBalanced,
            'difference' => round(abs($totalAssets - $totalLiabilitiesAndEquity), 2),
        ];
    }

    /**
     * 4. Laporan Rekonsiliasi Subledger terhadap Jurnal & Kas/Bank
     */
    public function getReconciliationReport(int $propertyId, ?string $startDate = null, ?string $endDate = null): array
    {
        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $property = Property::findOrFail($propertyId);
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfMonth()->endOfDay();

        // 1. Rekonsiliasi Penerimaan Kas/Bank (Verified Payments Subledger vs Journal Debits)
        $verifiedPaymentsSum = (float) Payment::whereHas('invoice', function ($q) use ($propertyId) {
            $q->where('property_id', $propertyId);
        })
        ->where('status', 'verified')
        ->whereDate('verified_at', '>=', $start->toDateString())
        ->whereDate('verified_at', '<=', $end->toDateString())
        ->sum('amount');

        // Jurnal Kas/Bank Debit dari sumber payment
        $journalPaymentDebits = (float) JournalLine::whereHas('account', function ($q) {
            $q->where('category', 'cash_bank');
        })
        ->whereHas('journalEntry', function ($q) use ($propertyId, $start, $end) {
            $q->where('property_id', $propertyId)
              ->where('source_type', 'payment')
              ->where('status', JournalEntryStatus::POSTED->value)
              ->whereDate('entry_date', '>=', $start->toDateString())
              ->whereDate('entry_date', '<=', $end->toDateString());
        })
        ->sum('debit');

        $paymentVariance = round($verifiedPaymentsSum - $journalPaymentDebits, 2);

        // 2. Rekonsiliasi Pengeluaran Kas/Bank (Expense Payments Subledger vs Journal Credits)
        $expensePaymentsSum = (float) ExpensePayment::whereHas('expense', function ($q) use ($propertyId) {
            $q->where('property_id', $propertyId);
        })
        ->whereDate('payment_date', '>=', $start->toDateString())
        ->whereDate('payment_date', '<=', $end->toDateString())
        ->sum('amount');

        $journalExpenseCredits = (float) JournalLine::whereHas('account', function ($q) {
            $q->where('category', 'cash_bank');
        })
        ->whereHas('journalEntry', function ($q) use ($propertyId, $start, $end) {
            $q->where('property_id', $propertyId)
              ->where('source_type', 'expense_payment')
              ->where('status', JournalEntryStatus::POSTED->value)
              ->whereDate('entry_date', '>=', $start->toDateString())
              ->whereDate('entry_date', '<=', $end->toDateString());
        })
        ->sum('credit');

        $expenseVariance = round($expensePaymentsSum - $journalExpenseCredits, 2);

        // 3. Rekonsiliasi Subledger Uang Jaminan (DepositLedger vs Akun 2101)
        $depositLedgers = DepositLedger::where('property_id', $propertyId)->get();
        $subledgerDepositReceived = (float) $depositLedgers->sum('total_received');
        $subledgerDepositDeductions = (float) $depositLedgers->sum('total_deductions');
        $subledgerDepositRefunded = (float) $depositLedgers->sum('total_refunded');
        $subledgerDepositBalance = (float) $depositLedgers->sum('current_balance');

        $depositAcc = $this->coaService->getAccountByCode($propertyId, '2101');
        $journalDepositCredits = (float) JournalLine::where('account_id', $depositAcc->id)
            ->whereHas('journalEntry', function ($q) use ($propertyId, $end) {
                $q->where('property_id', $propertyId)
                  ->where('status', JournalEntryStatus::POSTED->value)
                  ->whereDate('entry_date', '<=', $end->toDateString());
            })
            ->sum('credit');

        $journalDepositDebits = (float) JournalLine::where('account_id', $depositAcc->id)
            ->whereHas('journalEntry', function ($q) use ($propertyId, $end) {
                $q->where('property_id', $propertyId)
                  ->where('status', JournalEntryStatus::POSTED->value)
                  ->whereDate('entry_date', '<=', $end->toDateString());
            })
            ->sum('debit');

        $journalDepositBalance = round($journalDepositCredits - $journalDepositDebits, 2);
        $depositVariance = round($subledgerDepositBalance - $journalDepositBalance, 2);

        // 4. Deteksi Transaksi Tanpa Jurnal (Unmapped / Unposted Exceptions)
        $unpostedPaymentsCount = Payment::whereHas('invoice', function ($q) use ($propertyId) {
            $q->where('property_id', $propertyId);
        })
        ->where('status', 'verified')
        ->whereDoesntHave('invoice.property.journalEntries', function ($q) {
            $q->where('source_type', 'payment')
              ->whereRaw("idempotency_key LIKE 'payment:%'");
        })
        ->count();

        return [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
            ],
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'cash_inflow_reconciliation' => [
                'subledger_verified_payments' => round($verifiedPaymentsSum, 2),
                'journal_cash_debits' => round($journalPaymentDebits, 2),
                'variance' => $paymentVariance,
                'is_reconciled' => abs($paymentVariance) < 0.001,
            ],
            'cash_outflow_reconciliation' => [
                'subledger_expense_disbursements' => round($expensePaymentsSum, 2),
                'journal_cash_credits' => round($journalExpenseCredits, 2),
                'variance' => $expenseVariance,
                'is_reconciled' => abs($expenseVariance) < 0.001,
            ],
            'security_deposit_reconciliation' => [
                'subledger_current_balance' => round($subledgerDepositBalance, 2),
                'journal_liability_balance' => $journalDepositBalance,
                'variance' => $depositVariance,
                'is_reconciled' => abs($depositVariance) < 0.001,
                'details' => [
                    'subledger_received' => round($subledgerDepositReceived, 2),
                    'journal_deposit_credits' => round($journalDepositCredits, 2),
                    'subledger_deductions' => round($subledgerDepositDeductions, 2),
                    'subledger_refunded' => round($subledgerDepositRefunded, 2),
                    'journal_deposit_debits' => round($journalDepositDebits, 2),
                ],
            ],
            'unposted_exceptions_count' => $unpostedPaymentsCount,
        ];
    }
}
