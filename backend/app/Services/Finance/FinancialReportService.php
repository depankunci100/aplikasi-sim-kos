<?php

namespace App\Services\Finance;

use App\Enums\BookingStatus;
use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Get authorized property IDs for user
     */
    public function getAuthorizedPropertyIds(User $user, ?int $filterPropertyId = null): array
    {
        if ($user->isSuperAdmin()) {
            if ($filterPropertyId) {
                return [$filterPropertyId];
            }
            return Property::pluck('id')->toArray();
        }

        if ($user->isTenant()) {
            return [];
        }

        $allowed = $user->properties()->pluck('properties.id')->toArray();
        if ($filterPropertyId) {
            return in_array($filterPropertyId, $allowed) ? [$filterPropertyId] : [];
        }

        return $allowed;
    }

    /**
     * 1. Income Report (Laporan Pendapatan)
     */
    public function getIncomeReport(User $user, array $filters = []): array
    {
        $propertyIds = $this->getAuthorizedPropertyIds($user, $filters['property_id'] ?? null);

        // Date range
        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        // 1. Invoices Billed in period
        $invoiceQuery = Invoice::whereIn('property_id', $propertyIds)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['invoice_type'])) {
            $invoiceQuery->where('invoice_type', $filters['invoice_type']);
        }

        $totalInvoiced = (float) (clone $invoiceQuery)->sum('total_amount');
        $invoices = (clone $invoiceQuery)->with(['property', 'user', 'booking.room'])->get();

        // 2. Verified cash receipts in period (source of truth for cash-in)
        $paymentQuery = Payment::whereHas('invoice', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->where('status', 'verified')
        ->whereBetween('verified_at', [$startDate, $endDate]);

        $verifiedCashReceived = (float) (clone $paymentQuery)->sum('amount');
        $payments = (clone $paymentQuery)->with(['invoice.property', 'invoice.user'])->get();

        // Breakdown by invoice type for verified cash
        $rentalCash = 0.00;
        $utilityCash = 0.00;
        $otherCash = 0.00;

        foreach ($payments as $p) {
            $invType = $p->invoice?->invoice_type ?? 'room_rental';
            if ($invType === 'utility') {
                $utilityCash += (float) $p->amount;
            } elseif ($invType === 'room_rental') {
                $rentalCash += (float) $p->amount;
            } else {
                $otherCash += (float) $p->amount;
            }
        }

        // Outstanding receivables for invoices billed in or active
        $outstandingReceivables = (float) Invoice::whereIn('property_id', $propertyIds)
            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])
            ->selectRaw('SUM(total_amount - paid_amount) as total_remaining')
            ->value('total_remaining') ?? 0.00;

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_invoiced' => round($totalInvoiced, 2),
                'verified_cash_received' => round($verifiedCashReceived, 2),
                'rental_cash' => round($rentalCash, 2),
                'utility_cash' => round($utilityCash, 2),
                'other_cash' => round($otherCash, 2),
                'outstanding_receivables' => round((float) $outstandingReceivables, 2),
                'invoice_count' => $invoices->count(),
                'verified_payment_count' => $payments->count(),
            ],
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    /**
     * 2. Expense Report (Laporan Pengeluaran Operasional)
     */
    public function getExpenseReport(User $user, array $filters = []): array
    {
        $propertyIds = $this->getAuthorizedPropertyIds($user, $filters['property_id'] ?? null);

        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        $query = Expense::whereIn('property_id', $propertyIds)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['expense_category_id'])) {
            $query->where('expense_category_id', $filters['expense_category_id']);
        }

        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        $allExpenses = (clone $query)->with(['property', 'expenseCategory', 'user', 'payments'])->get();

        $totalSubmitted = (float) (clone $query)->where('approval_status', ExpenseApprovalStatus::SUBMITTED)->sum('amount');
        $totalApproved = (float) (clone $query)->where('approval_status', ExpenseApprovalStatus::APPROVED)->sum('amount');

        // Actual cash out: payments recorded in this period
        $actualCashOut = (float) ExpensePayment::whereHas('expense', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
        ->sum('amount');

        // Breakdown by category
        $byCategory = Expense::whereIn('property_id', $propertyIds)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('approval_status', ExpenseApprovalStatus::APPROVED)
            ->selectRaw('category, SUM(amount) as total_amount, SUM(paid_amount) as total_paid, COUNT(*) as count')
            ->groupBy('category')
            ->get();

        // Breakdown by property
        $byProperty = Expense::whereIn('property_id', $propertyIds)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->join('properties', 'expenses.property_id', '=', 'properties.id')
            ->selectRaw('properties.name as property_name, SUM(expenses.amount) as total_amount, SUM(expenses.paid_amount) as total_paid')
            ->groupBy('properties.id', 'properties.name')
            ->get();

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_submitted' => round($totalSubmitted, 2),
                'total_approved' => round($totalApproved, 2),
                'actual_cash_out' => round($actualCashOut, 2),
                'total_count' => $allExpenses->count(),
            ],
            'by_category' => $byCategory,
            'by_property' => $byProperty,
            'expenses' => $allExpenses,
        ];
    }

    /**
     * 3. Cash Flow Report (Laporan Arus Kas)
     * Strictly verifies real cash transactions: verified tenant payments vs recorded expense disbursements
     */
    public function getCashFlowReport(User $user, array $filters = []): array
    {
        $propertyIds = $this->getAuthorizedPropertyIds($user, $filters['property_id'] ?? null);

        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        // Cash In: Verified Payments
        $cashInItems = Payment::whereHas('invoice', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->where('status', 'verified')
        ->whereBetween('verified_at', [$startDate, $endDate])
        ->with(['invoice.property', 'invoice.user'])
        ->get();

        $totalCashIn = (float) $cashInItems->sum('amount');

        // Cash Out: Recorded Expense Payments
        $cashOutItems = ExpensePayment::whereHas('expense', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
        ->with(['expense.property', 'recorder'])
        ->get();

        $totalCashOut = (float) $cashOutItems->sum('amount');
        $netCashChange = round($totalCashIn - $totalCashOut, 2);

        // Build unified chronological transaction log
        $transactions = [];

        foreach ($cashInItems as $in) {
            $transactions[] = [
                'type' => 'in',
                'date' => $in->verified_at ? $in->verified_at->toDateTimeString() : $in->created_at->toDateTimeString(),
                'reference' => $in->payment_code,
                'description' => 'Penerimaan Invoice ' . ($in->invoice?->invoice_number ?? '-') . ' (' . ($in->invoice?->user?->name ?? 'Penyewa') . ')',
                'property_name' => $in->invoice?->property?->name ?? '-',
                'method' => $in->payment_method,
                'amount' => (float) $in->amount,
            ];
        }

        foreach ($cashOutItems as $out) {
            $transactions[] = [
                'type' => 'out',
                'date' => $out->payment_date->toDateString() . ' 12:00:00',
                'reference' => $out->payment_number,
                'description' => 'Pengeluaran ' . ($out->expense?->expense_number ?? '-') . ': ' . ($out->expense?->title ?? '-'),
                'property_name' => $out->expense?->property?->name ?? '-',
                'method' => $out->payment_method,
                'amount' => (float) $out->amount,
            ];
        }

        usort($transactions, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_cash_in' => round($totalCashIn, 2),
                'total_cash_out' => round($totalCashOut, 2),
                'net_cash_change' => $netCashChange,
                'transaction_count' => count($transactions),
            ],
            'transactions' => $transactions,
        ];
    }

    /**
     * 4. Receivables Report (Laporan Piutang & Aging)
     */
    public function getReceivablesReport(User $user, array $filters = []): array
    {
        $propertyIds = $this->getAuthorizedPropertyIds($user, $filters['property_id'] ?? null);

        $query = Invoice::whereIn('property_id', $propertyIds)
            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])
            ->with(['property', 'user', 'booking.room']);

        if (!empty($filters['invoice_type'])) {
            $query->where('invoice_type', $filters['invoice_type']);
        }

        $invoices = $query->orderBy('due_date', 'asc')->get();

        $totalReceivables = 0.00;
        $bucketCurrent = 0.00;    // 0 - 30 days
        $bucket31to60 = 0.00;     // 31 - 60 days overdue
        $bucket61to90 = 0.00;     // 61 - 90 days overdue
        $bucketOver90 = 0.00;     // > 90 days overdue

        $today = now()->startOfDay();
        $formattedInvoices = [];

        foreach ($invoices as $inv) {
            $remaining = (float) $inv->remaining_balance;
            if ($remaining <= 0) continue;

            $totalReceivables += $remaining;
            $dueDate = $inv->due_date ? Carbon::parse($inv->due_date)->startOfDay() : $today;
            $daysOverdue = $today->gt($dueDate) ? $today->diffInDays($dueDate) : 0;

            if ($daysOverdue <= 30) {
                $bucketCurrent += $remaining;
                $agingBucket = '0 - 30 Hari';
            } elseif ($daysOverdue <= 60) {
                $bucket31to60 += $remaining;
                $agingBucket = '31 - 60 Hari';
            } elseif ($daysOverdue <= 90) {
                $bucket61to90 += $remaining;
                $agingBucket = '61 - 90 Hari';
            } else {
                $bucketOver90 += $remaining;
                $agingBucket = '> 90 Hari';
            }

            $formattedInvoices[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'property_name' => $inv->property?->name ?? '-',
                'tenant_name' => $inv->user?->name ?? '-',
                'room_number' => $inv->booking?->room?->room_number ?? '-',
                'due_date' => $inv->due_date?->toDateString() ?? '-',
                'days_overdue' => $daysOverdue,
                'aging_bucket' => $agingBucket,
                'total_amount' => (float) $inv->total_amount,
                'paid_amount' => (float) $inv->paid_amount,
                'remaining_balance' => $remaining,
                'status' => $inv->status->value,
            ];
        }

        return [
            'summary' => [
                'total_receivables' => round($totalReceivables, 2),
                'bucket_current' => round($bucketCurrent, 2),
                'bucket_31_60' => round($bucket31to60, 2),
                'bucket_61_90' => round($bucket61to90, 2),
                'bucket_over_90' => round($bucketOver90, 2),
                'invoice_count' => count($formattedInvoices),
            ],
            'invoices' => $formattedInvoices,
        ];
    }

    /**
     * 5. Operating Summary Report (Ringkasan Hasil Usaha Operasional)
     * Cash-basis operating surplus/deficit calculation
     */
    public function getOperatingSummaryReport(User $user, array $filters = []): array
    {
        $propertyIds = $this->getAuthorizedPropertyIds($user, $filters['property_id'] ?? null);

        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        // Verified Cash In
        $cashIn = (float) Payment::whereHas('invoice', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->where('status', 'verified')
        ->whereBetween('verified_at', [$startDate, $endDate])
        ->sum('amount');

        // Paid Expenses Cash Out
        $cashOut = (float) ExpensePayment::whereHas('expense', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
        ->sum('amount');

        $operatingMargin = round($cashIn - $cashOut, 2);

        // Monthly trend for past 6 months
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = now()->subMonths($i)->startOfMonth();
            $mEnd = now()->subMonths($i)->endOfMonth();

            $mIn = (float) Payment::whereHas('invoice', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->where('status', 'verified')
            ->whereBetween('verified_at', [$mStart, $mEnd])
            ->sum('amount');

            $mOut = (float) ExpensePayment::whereHas('expense', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->whereBetween('payment_date', [$mStart->toDateString(), $mEnd->toDateString()])
            ->sum('amount');

            $monthlyTrend[] = [
                'month' => $mStart->format('M Y'),
                'cash_in' => round($mIn, 2),
                'cash_out' => round($mOut, 2),
                'operating_result' => round($mIn - $mOut, 2),
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_verified_revenue' => round($cashIn, 2),
                'total_paid_expenses' => round($cashOut, 2),
                'operating_result' => $operatingMargin,
                'margin_percentage' => $cashIn > 0 ? round(($operatingMargin / $cashIn) * 100, 1) : 0.0,
            ],
            'monthly_trend' => $monthlyTrend,
        ];
    }

    /**
     * 6. Owner Dashboard and Business Analytics
     */
    public function getOwnerDashboardAnalytics(User $user, array $filters = []): array
    {
        $propertyIds = $this->getAuthorizedPropertyIds($user, $filters['property_id'] ?? null);

        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        // 1. Verified cash received
        $totalVerifiedReceipts = (float) Payment::whereHas('invoice', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->where('status', 'verified')
        ->whereBetween('verified_at', [$startDate, $endDate])
        ->sum('amount');

        // 2. Paid expenses
        $totalPaidExpenses = (float) ExpensePayment::whereHas('expense', function ($q) use ($propertyIds) {
            $q->whereIn('property_id', $propertyIds);
        })
        ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
        ->sum('amount');

        // 3. Net cash flow
        $netCashFlow = round($totalVerifiedReceipts - $totalPaidExpenses, 2);

        // 4. Total outstanding receivables
        $totalReceivables = (float) Invoice::whereIn('property_id', $propertyIds)
            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])
            ->selectRaw('SUM(total_amount - paid_amount) as remaining')
            ->value('remaining') ?? 0.00;

        // 5. Expenses pending approval
        $pendingExpensesCount = Expense::whereIn('property_id', $propertyIds)
            ->where('approval_status', ExpenseApprovalStatus::SUBMITTED)
            ->count();

        $pendingExpensesAmount = (float) Expense::whereIn('property_id', $propertyIds)
            ->where('approval_status', ExpenseApprovalStatus::SUBMITTED)
            ->sum('amount');

        // 6. Unpaid invoices count
        $unpaidInvoicesCount = Invoice::whereIn('property_id', $propertyIds)
            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])
            ->count();

        // 7. Active bookings
        $activeBookingsCount = Booking::whereIn('property_id', $propertyIds)
            ->whereIn('status', ['confirmed', 'active', 'checked_in'])
            ->count();

        // 8. Rooms and Occupancy Rate
        $totalRooms = Room::whereIn('property_id', $propertyIds)->count();
        $occupiedRooms = Room::whereIn('property_id', $propertyIds)
            ->where('status', 'occupied')
            ->count();

        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0.0;

        // 9. Incomplete maintenance jobs
        $incompleteMaintenance = \App\Models\MaintenanceRequest::whereIn('property_id', $propertyIds)
            ->whereIn('status', [
                MaintenanceStatus::REPORTED,
                MaintenanceStatus::TRIAGED,
                MaintenanceStatus::IN_PROGRESS,
                MaintenanceStatus::PENDING_VERIFICATION,
            ])
            ->count();

        // 10. Monthly trend (past 6 months)
        $chartMonths = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = now()->subMonths($i)->startOfMonth();
            $mEnd = now()->subMonths($i)->endOfMonth();

            $mIn = (float) Payment::whereHas('invoice', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->where('status', 'verified')
            ->whereBetween('verified_at', [$mStart, $mEnd])
            ->sum('amount');

            $mOut = (float) ExpensePayment::whereHas('expense', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->whereBetween('payment_date', [$mStart->toDateString(), $mEnd->toDateString()])
            ->sum('amount');

            $chartMonths[] = [
                'label' => $mStart->format('M Y'),
                'income' => round($mIn, 2),
                'expense' => round($mOut, 2),
            ];
        }

        // 11. Expense category breakdown
        $expenseCategories = Expense::whereIn('property_id', $propertyIds)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('approval_status', ExpenseApprovalStatus::APPROVED)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->map(fn($item) => [
                'category' => $item->category,
                'total' => (float) $item->total,
            ]);

        return [
            'kpis' => [
                'total_verified_receipts' => round($totalVerifiedReceipts, 2),
                'total_paid_expenses' => round($totalPaidExpenses, 2),
                'net_cash_flow' => $netCashFlow,
                'total_receivables' => round($totalReceivables, 2),
                'pending_expenses_count' => $pendingExpensesCount,
                'pending_expenses_amount' => round($pendingExpensesAmount, 2),
                'unpaid_invoices_count' => $unpaidInvoicesCount,
                'active_bookings_count' => $activeBookingsCount,
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'occupancy_rate' => $occupancyRate,
                'incomplete_maintenance' => $incompleteMaintenance,
            ],
            'chart_trend' => $chartMonths,
            'expense_categories' => $expenseCategories,
        ];
    }

    /**
     * 7. Security Deposit Subledger Summary (Kewajiban Uang Jaminan)
     */
    public function getDepositSummary(User $user, array $filters = []): array
    {
        $propertyIds = $this->getAuthorizedPropertyIds($user, $filters['property_id'] ?? null);

        $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfMonth();
        $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : now()->endOfMonth();

        // 1. Total kewajiban deposit aktif saat ini (Liability balance held)
        $totalDepositHeld = (float) \App\Models\DepositLedger::whereIn('property_id', $propertyIds)
            ->where('status', \App\Enums\DepositStatus::HELD)
            ->sum('current_balance');

        // 2. Penerimaan deposit dalam periode
        $depositsReceivedPeriod = (float) \App\Models\DepositMutation::whereHas('ledger', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->where('transaction_type', \App\Enums\DepositMutationType::DEPOSIT_RECEIVED)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        // 3. Potongan denda kerusakan dalam periode
        $deductionsPeriod = (float) \App\Models\DepositMutation::whereHas('ledger', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->where('transaction_type', \App\Enums\DepositMutationType::DAMAGE_DEDUCTION)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        // 4. Pengembalian deposit yang dibayarkan dalam periode
        $refundsPaidPeriod = (float) \App\Models\DepositMutation::whereHas('ledger', function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds);
            })
            ->where('transaction_type', \App\Enums\DepositMutationType::REFUND_PAYOUT)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        return [
            'total_deposit_held' => round($totalDepositHeld, 2),
            'deposits_received_period' => round($depositsReceivedPeriod, 2),
            'deductions_period' => round($deductionsPeriod, 2),
            'refunds_paid_period' => round($refundsPaidPeriod, 2),
        ];
    }
}
