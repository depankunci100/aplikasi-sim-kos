<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Finance\ExpenseService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseApiController extends ApiController
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isTenant()) {
            return $this->errorResponse('Penyewa tidak memiliki akses ke pengeluaran operasional.', 403);
        }

        $filters = $request->only([
            'property_id',
            'expense_category_id',
            'category',
            'approval_status',
            'payment_status',
            'payment_method',
            'start_date',
            'end_date',
            'search',
        ]);

        $expenses = $this->expenseService->getExpensesQuery($user, $filters)->paginate($request->input('per_page', 15));

        return $this->successResponse($expenses, 'Daftar pengeluaran operasional berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isTenant()) {
            return $this->errorResponse('Penyewa tidak memiliki izin mengajukan pengeluaran.', 403);
        }

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date',
            'vendor_name' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,qris,other',
            'receipt' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'notes' => 'nullable|string|max:1000',
            'action_type' => 'nullable|string|in:draft,submit',
        ]);

        try {
            $submitDirectly = ($request->input('action_type') === 'submit');
            $receipt = $request->file('receipt');
            $expense = $this->expenseService->createExpense($user, $validated, $receipt, $submitDirectly);

            return $this->successResponse($expense->load(['property', 'expenseCategory']), 'Pengeluaran operasional berhasil dibuat.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::with(['property', 'expenseCategory', 'user', 'approver', 'payer', 'payments.recorder', 'logs.user'])->find($id);

        if (!$expense) {
            return $this->errorResponse('Data pengeluaran tidak ditemukan.', 404);
        }

        if (!$this->canAccessExpense($user, $expense)) {
            return $this->errorResponse('Anda tidak memiliki akses ke pengeluaran ini.', 403);
        }

        return $this->successResponse($expense, 'Detail pengeluaran berhasil diambil.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::find($id);

        if (!$expense) {
            return $this->errorResponse('Data pengeluaran tidak ditemukan.', 404);
        }

        if (!$this->canAccessExpense($user, $expense)) {
            return $this->errorResponse('Anda tidak memiliki akses ke pengeluaran ini.', 403);
        }

        $validated = $request->validate([
            'expense_category_id' => 'nullable|exists:expense_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date',
            'vendor_name' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,qris,other',
            'receipt' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $receipt = $request->file('receipt');
            $updated = $this->expenseService->updateDraft($user, $expense, $validated, $receipt);

            return $this->successResponse($updated, 'Draft pengeluaran berhasil diperbarui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::find($id);

        if (!$expense) {
            return $this->errorResponse('Data pengeluaran tidak ditemukan.', 404);
        }

        if (!$this->canAccessExpense($user, $expense)) {
            return $this->errorResponse('Anda tidak memiliki akses ke pengeluaran ini.', 403);
        }

        try {
            $submitted = $this->expenseService->submitForApproval($user, $expense);
            return $this->successResponse($submitted, 'Pengeluaran berhasil diajukan untuk persetujuan.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::find($id);

        if (!$expense) {
            return $this->errorResponse('Data pengeluaran tidak ditemukan.', 404);
        }

        if (!$this->canAccessExpense($user, $expense)) {
            return $this->errorResponse('Anda tidak memiliki akses ke pengeluaran ini.', 403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $approved = $this->expenseService->approve($user, $expense, $request->input('notes'));
            return $this->successResponse($approved, 'Pengeluaran telah disetujui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::find($id);

        if (!$expense) {
            return $this->errorResponse('Data pengeluaran tidak ditemukan.', 404);
        }

        if (!$this->canAccessExpense($user, $expense)) {
            return $this->errorResponse('Anda tidak memiliki akses ke pengeluaran ini.', 403);
        }

        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $rejected = $this->expenseService->reject($user, $expense, $request->input('rejection_reason'));
            return $this->successResponse($rejected, 'Pengeluaran telah ditolak.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $expense = Expense::find($id);

        if (!$expense) {
            return $this->errorResponse('Data pengeluaran tidak ditemukan.', 404);
        }

        if (!$this->canAccessExpense($user, $expense)) {
            return $this->errorResponse('Anda tidak memiliki akses ke pengeluaran ini.', 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|in:cash,bank_transfer,qris,other',
            'proof_file' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $proof = $request->file('proof_file');
            $payment = $this->expenseService->recordPayment($user, $expense, $validated, $proof);

            return $this->successResponse($payment, 'Pembayaran pengeluaran berhasil dicatat.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function categories(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isTenant()) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $propertyId = $request->input('property_id');
        $categories = $this->expenseService->getCategories($propertyId ? (int) $propertyId : null);

        return $this->successResponse($categories, 'Daftar kategori pengeluaran berhasil diambil.');
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isOwner() && !$user->isManager()) {
            return $this->errorResponse('Hanya manajer atau pemilik yang dapat menambah kategori.', 403);
        }

        $validated = $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:255',
        ]);

        $cat = ExpenseCategory::create([
            'property_id' => $validated['property_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return $this->successResponse($cat, 'Kategori pengeluaran baru berhasil dibuat.', 201);
    }

    public function receipt(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $expense = Expense::find($id);

        if (!$expense || !$this->canAccessExpense($user, $expense)) {
            return $this->errorResponse('Akses bukti transaksi tidak diizinkan.', 403);
        }

        if (!$expense->receipt_photo || !Storage::exists($expense->receipt_photo)) {
            return $this->errorResponse('File bukti kuitansi tidak ditemukan.', 404);
        }

        $fullPath = Storage::path($expense->receipt_photo);
        $mimeType = Storage::mimeType($expense->receipt_photo) ?? 'image/jpeg';

        $fileResponse = response()->file($fullPath, [
            'Content-Type' => $mimeType,
        ]);
        $fileResponse->setPrivate();
        $fileResponse->headers->addCacheControlDirective('no-cache', true);

        return $fileResponse;
    }

    protected function canAccessExpense($user, Expense $expense): bool
    {
        if ($user->isSuperAdmin()) return true;
        if ($user->isTenant()) return false;
        return $user->properties()->where('properties.id', $expense->property_id)->exists();
    }
}
