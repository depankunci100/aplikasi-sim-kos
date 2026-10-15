<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Finance\FinancialReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceWebController extends Controller
{
    public function __construct(
        protected FinancialReportService $reportService
    ) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki izin untuk melihat dashboard keuangan.');
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date']);
        if (empty($filters['start_date'])) {
            $filters['start_date'] = now()->startOfMonth()->toDateString();
        }
        if (empty($filters['end_date'])) {
            $filters['end_date'] = now()->endOfMonth()->toDateString();
        }

        $analytics = $this->reportService->getOwnerDashboardAnalytics($user, $filters);
        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();

        return view('dashboard.finance.index', compact('analytics', 'properties', 'filters'));
    }

    public function incomeReport(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date', 'invoice_type']);
        if (empty($filters['start_date'])) {
            $filters['start_date'] = now()->startOfMonth()->toDateString();
        }
        if (empty($filters['end_date'])) {
            $filters['end_date'] = now()->endOfMonth()->toDateString();
        }

        $report = $this->reportService->getIncomeReport($user, $filters);
        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();

        return view('dashboard.reports.income', compact('report', 'properties', 'filters'));
    }

    public function expenseReport(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date', 'category', 'approval_status', 'payment_status']);
        if (empty($filters['start_date'])) {
            $filters['start_date'] = now()->startOfMonth()->toDateString();
        }
        if (empty($filters['end_date'])) {
            $filters['end_date'] = now()->endOfMonth()->toDateString();
        }

        $report = $this->reportService->getExpenseReport($user, $filters);
        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();

        return view('dashboard.reports.expenses', compact('report', 'properties', 'filters'));
    }

    public function cashFlowReport(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date']);
        if (empty($filters['start_date'])) {
            $filters['start_date'] = now()->startOfMonth()->toDateString();
        }
        if (empty($filters['end_date'])) {
            $filters['end_date'] = now()->endOfMonth()->toDateString();
        }

        $report = $this->reportService->getCashFlowReport($user, $filters);
        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();

        return view('dashboard.reports.cash_flow', compact('report', 'properties', 'filters'));
    }

    public function receivablesReport(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403);
        }

        $filters = $request->only(['property_id', 'invoice_type']);
        $report = $this->reportService->getReceivablesReport($user, $filters);
        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();

        return view('dashboard.reports.receivables', compact('report', 'properties', 'filters'));
    }

    public function operatingSummaryReport(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date']);
        if (empty($filters['start_date'])) {
            $filters['start_date'] = now()->startOfMonth()->toDateString();
        }
        if (empty($filters['end_date'])) {
            $filters['end_date'] = now()->endOfMonth()->toDateString();
        }

        $report = $this->reportService->getOperatingSummaryReport($user, $filters);
        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();

        return view('dashboard.reports.operating_summary', compact('report', 'properties', 'filters'));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date', 'category', 'invoice_type']);

        $filename = "laporan_{$type}_" . date('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($type, $user, $filters) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            if ($type === 'income') {
                $data = $this->reportService->getIncomeReport($user, $filters);
                fputcsv($handle, ['No. Pembayaran', 'No. Invoice', 'Properti', 'Penyewa', 'Metode', 'Tanggal Verifikasi', 'Nominal (Rp)']);
                foreach ($data['payments'] as $p) {
                    fputcsv($handle, [
                        $p->payment_code,
                        $p->invoice?->invoice_number ?? '-',
                        $p->invoice?->property?->name ?? '-',
                        $p->invoice?->user?->name ?? '-',
                        $p->payment_method,
                        $p->verified_at ? $p->verified_at->format('Y-m-d H:i') : '-',
                        number_format($p->amount, 2, '.', ''),
                    ]);
                }
            } elseif ($type === 'expenses') {
                $data = $this->reportService->getExpenseReport($user, $filters);
                fputcsv($handle, ['No. Pengeluaran', 'Properti', 'Kategori', 'Judul', 'Vendor', 'Tanggal', 'Nominal (Rp)', 'Dibayar (Rp)', 'Status Persetujuan', 'Status Pembayaran']);
                foreach ($data['expenses'] as $exp) {
                    fputcsv($handle, [
                        $exp->expense_number,
                        $exp->property?->name ?? '-',
                        $exp->category,
                        $exp->title,
                        $exp->vendor_name ?? '-',
                        $exp->expense_date?->toDateString(),
                        number_format($exp->amount, 2, '.', ''),
                        number_format($exp->paid_amount, 2, '.', ''),
                        $exp->approval_status->label(),
                        $exp->payment_status->label(),
                    ]);
                }
            } elseif ($type === 'cash_flow') {
                $data = $this->reportService->getCashFlowReport($user, $filters);
                fputcsv($handle, ['Arah Kas', 'Tanggal', 'Referensi', 'Keterangan', 'Properti', 'Metode', 'Nominal (Rp)']);
                foreach ($data['transactions'] as $t) {
                    fputcsv($handle, [
                        $t['type'] === 'in' ? 'KAS MASUK' : 'KAS KELUAR',
                        $t['date'],
                        $t['reference'],
                        $t['description'],
                        $t['property_name'],
                        $t['method'],
                        number_format($t['amount'], 2, '.', ''),
                    ]);
                }
            } elseif ($type === 'receivables') {
                $data = $this->reportService->getReceivablesReport($user, $filters);
                fputcsv($handle, ['No. Invoice', 'Properti', 'Penyewa', 'Kamar', 'Jatuh Tempo', 'Hari Terlambat', 'Kelompok Umur', 'Total Tagihan (Rp)', 'Sudah Dibayar (Rp)', 'Sisa Piutang (Rp)']);
                foreach ($data['invoices'] as $inv) {
                    fputcsv($handle, [
                        $inv['invoice_number'],
                        $inv['property_name'],
                        $inv['tenant_name'],
                        $inv['room_number'],
                        $inv['due_date'],
                        $inv['days_overdue'],
                        $inv['aging_bucket'],
                        number_format($inv['total_amount'], 2, '.', ''),
                        number_format($inv['paid_amount'], 2, '.', ''),
                        number_format($inv['remaining_balance'], 2, '.', ''),
                    ]);
                }
            } elseif ($type === 'operating_summary') {
                $data = $this->reportService->getOperatingSummaryReport($user, $filters);
                fputcsv($handle, ['Bulan', 'Penerimaan Kas (Rp)', 'Pengeluaran Kas (Rp)', 'Hasil Operasional (Rp)']);
                foreach ($data['monthly_trend'] as $m) {
                    fputcsv($handle, [
                        $m['month'],
                        number_format($m['cash_in'], 2, '.', ''),
                        number_format($m['cash_out'], 2, '.', ''),
                        number_format($m['operating_result'], 2, '.', ''),
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
