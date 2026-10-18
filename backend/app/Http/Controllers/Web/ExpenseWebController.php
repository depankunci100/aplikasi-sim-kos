<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\Property;
use App\Models\User;
use App\Services\Finance\ExpenseService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseWebController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki akses ke manajemen pengeluaran.');
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

        $expenses = $this->expenseService->getExpensesQuery($user, $filters)->paginate(15)->withQueryString();

        // Summary Counts
        $counts = [
            'total' => (clone $this->expenseService->getExpensesQuery($user, []))->count(),
            'draft' => (clone $this->expenseService->getExpensesQuery($user, ['approval_status' => 'draft']))->count(),
            'submitted' => (clone $this->expenseService->getExpensesQuery($user, ['approval_status' => 'submitted']))->count(),
            'approved' => (clone $this->expenseService->getExpensesQuery($user, ['approval_status' => 'approved']))->count(),
            'paid' => (clone $this->expenseService->getExpensesQuery($user, ['payment_status' => 'paid']))->count(),
        ];

        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();
        $categories = $this->expenseService->getCategories();

        return view('dashboard.expenses.index', compact('expenses', 'counts', 'properties', 'categories', 'filters'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki izin untuk membuat pengeluaran.');
        }

        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();
        $categories = $this->expenseService->getCategories();

        return view('dashboard.expenses.create', compact('properties', 'categories'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki izin untuk membuat pengeluaran.');
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
            $receiptFile = $request->file('receipt');

            $expense = $this->expenseService->createExpense($user, $validated, $receiptFile, $submitDirectly);

            $msg = $submitDirectly
                ? "Pengeluaran {$expense->expense_number} berhasil dibuat dan diajukan untuk persetujuan."
                : "Draft pengeluaran {$expense->expense_number} berhasil disimpan.";

            return redirect()->route('dashboard.expenses.show', $expense->id)->with('success', $msg);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $expense = Expense::with(['property', 'expenseCategory', 'user', 'approver', 'payer', 'payments.recorder', 'logs.user'])->findOrFail($id);

        $this->authorizeExpenseAccess($user, $expense);

        return view('dashboard.expenses.show', compact('expense'));
    }

    public function edit(Request $request, int $id)
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

        if (!$expense->isDraft()) {
            return redirect()->route('dashboard.expenses.show', $expense->id)
                ->with('error', 'Hanya pengeluaran berstatus draft yang dapat diedit.');
        }

        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();
        $categories = $this->expenseService->getCategories($expense->property_id);

        return view('dashboard.expenses.edit', compact('expense', 'properties', 'categories'));
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

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
            $receiptFile = $request->file('receipt');
            $this->expenseService->updateDraft($user, $expense, $validated, $receiptFile);

            return redirect()->route('dashboard.expenses.show', $expense->id)
                ->with('success', 'Draft pengeluaran berhasil diperbarui.');
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function submit(Request $request, int $id)
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

        try {
            $this->expenseService->submitForApproval($user, $expense);
            return back()->with('success', "Pengeluaran {$expense->expense_number} berhasil diajukan untuk persetujuan.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, int $id)
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->expenseService->approve($user, $expense, $request->input('notes'));
            return back()->with('success', "Pengeluaran {$expense->expense_number} telah disetujui untuk pembayaran.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->expenseService->reject($user, $expense, $request->input('rejection_reason'));
            return back()->with('success', "Pengeluaran {$expense->expense_number} telah ditolak.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function recordPayment(Request $request, int $id)
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

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

            return back()->with('success', "Pembayaran {$payment->payment_number} sebesar Rp " . number_format($payment->amount, 0, ',', '.') . " berhasil dicatat.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receipt(Request $request, int $id): BinaryFileResponse
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

        if (!$expense->receipt_photo || !Storage::exists($expense->receipt_photo)) {
            abort(404, 'Bukti kuitansi/transaksi tidak ditemukan.');
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

    public function paymentProof(Request $request, int $id, int $paymentId): BinaryFileResponse
    {
        $user = $request->user();
        $expense = Expense::findOrFail($id);
        $this->authorizeExpenseAccess($user, $expense);

        $payment = ExpensePayment::where('expense_id', $expense->id)->findOrFail($paymentId);

        if (!$payment->proof_file || !Storage::exists($payment->proof_file)) {
            abort(404, 'Bukti pembayaran tidak ditemukan.');
        }

        $fullPath = Storage::path($payment->proof_file);
        $mimeType = Storage::mimeType($payment->proof_file) ?? 'image/jpeg';

        $fileResponse = response()->file($fullPath, [
            'Content-Type' => $mimeType,
        ]);
        $fileResponse->setPrivate();
        $fileResponse->headers->addCacheControlDirective('no-cache', true);

        return $fileResponse;
    }

    public function categories(Request $request)
    {
        $user = $request->user();
        if ($user->isTenant()) {
            abort(403);
        }

        $categories = ExpenseCategory::with('property')->orderBy('name')->get();
        $properties = $user->isSuperAdmin() ? Property::active()->get() : $user->properties()->active()->get();

        return view('dashboard.expense_categories.index', compact('categories', 'properties'));
    }

    public function storeCategory(Request $request)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isOwner() && !$user->isManager()) {
            abort(403, 'Hanya manajer atau pemilik yang dapat menambah kategori.');
        }

        $validated = $request->validate([
            'property_id' => 'nullable|exists:properties,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:255',
        ]);

        ExpenseCategory::create([
            'property_id' => $validated['property_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Kategori pengeluaran baru berhasil ditambahkan.');
    }

    public function toggleCategory(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isOwner() && !$user->isManager()) {
            abort(403);
        }

        $category = ExpenseCategory::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);

        $statusText = $category->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Kategori {$category->name} berhasil {$statusText}.");
    }

    protected function authorizeExpenseAccess(User $user, Expense $expense): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isTenant()) {
            abort(403, 'Penyewa tidak memiliki akses ke pengeluaran operasional.');
        }

        $allowed = $user->properties()->where('properties.id', $expense->property_id)->exists();
        if (!$allowed) {
            abort(403, 'Anda tidak memiliki akses ke properti pengeluaran ini.');
        }
    }
}
