<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Finance\FinancialReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportApiController extends ApiController
{
    public function __construct(
        protected FinancialReportService $reportService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Penyewa tidak memiliki izin mengakses data analitik keuangan.', 403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date']);
        $analytics = $this->reportService->getOwnerDashboardAnalytics($user, $filters);

        return $this->successResponse($analytics, 'Data ringkasan dan analitik keuangan berhasil diambil.');
    }

    public function income(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date', 'invoice_type']);
        $data = $this->reportService->getIncomeReport($user, $filters);

        return $this->successResponse($data, 'Laporan pendapatan berhasil diambil.');
    }

    public function expenses(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date', 'category', 'approval_status', 'payment_status']);
        $data = $this->reportService->getExpenseReport($user, $filters);

        return $this->successResponse($data, 'Laporan pengeluaran operasional berhasil diambil.');
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date']);
        $data = $this->reportService->getCashFlowReport($user, $filters);

        return $this->successResponse($data, 'Laporan arus kas berhasil diambil.');
    }

    public function receivables(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $filters = $request->only(['property_id', 'invoice_type']);
        $data = $this->reportService->getReceivablesReport($user, $filters);

        return $this->successResponse($data, 'Laporan piutang berhasil diambil.');
    }

    public function operatingSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $filters = $request->only(['property_id', 'start_date', 'end_date']);
        $data = $this->reportService->getOperatingSummaryReport($user, $filters);

        return $this->successResponse($data, 'Laporan ringkasan hasil usaha operasional berhasil diambil.');
    }
}
