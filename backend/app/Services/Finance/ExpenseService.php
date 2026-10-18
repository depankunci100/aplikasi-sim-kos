<?php

namespace App\Services\Finance;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseLog;
use App\Models\ExpensePayment;
use App\Models\Property;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    /**
     * Default expense categories template
     */
    public const DEFAULT_CATEGORIES = [
        ['code' => 'electricity_water', 'name' => 'Listrik & Air', 'description' => 'Biaya utilitas PLN dan PDAM operasional'],
        ['code' => 'internet', 'name' => 'Internet & WiFi', 'description' => 'Langganan internet dan jaringan WiFi kos/guesthouse'],
        ['code' => 'maintenance', 'name' => 'Perbaikan & Maintenance', 'description' => 'Suku cadang, material, dan jasa teknisi perbaikan'],
        ['code' => 'housekeeping', 'name' => 'Kebersihan & Linen', 'description' => 'Sabun, disinfektan, laundry linen, dan perlengkapan kebersihan'],
        ['code' => 'salaries', 'name' => 'Gaji & Tenaga Kerja', 'description' => 'Upah staf kebersihan, resepsionis, teknisi, dan penjaga malam'],
        ['code' => 'supplies', 'name' => 'Perlengkapan & ATK', 'description' => 'Alat tulis kantor, perlengkapan tamu, dan amenities'],
        ['code' => 'taxes_admin', 'name' => 'Pajak & Biaya Administrasi', 'description' => 'PBB, retribusi daerah, izin usaha, dan biaya perbankan'],
        ['code' => 'other', 'name' => 'Pengeluaran Lainnya', 'description' => 'Pengeluaran darurat atau operasional tak terduga'],
    ];

    /**
     * Seed default categories if none exist
     */
    public function seedDefaultCategoriesIfEmpty(?int $propertyId = null): void
    {
        $existing = ExpenseCategory::where(function ($q) use ($propertyId) {
            $q->whereNull('property_id');
            if ($propertyId) {
                $q->orWhere('property_id', $propertyId);
            }
        })->count();

        if ($existing === 0) {
            foreach (self::DEFAULT_CATEGORIES as $cat) {
                ExpenseCategory::create([
                    'property_id' => $propertyId,
                    'name' => $cat['name'],
                    'code' => $cat['code'],
                    'description' => $cat['description'],
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * Get active categories for user and property
     */
    public function getCategories(?int $propertyId = null)
    {
        $this->seedDefaultCategoriesIfEmpty($propertyId);

        return ExpenseCategory::active()
            ->where(function ($q) use ($propertyId) {
                $q->whereNull('property_id');
                if ($propertyId) {
                    $q->orWhere('property_id', $propertyId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Build base query for expenses filtered by user property authorization
     */
    public function getExpensesQuery(User $user, array $filters = []): Builder
    {
        $query = Expense::with(['property', 'expenseCategory', 'user', 'approver', 'payer', 'payments.recorder']);

        // Multi-property authorization guard
        if (!$user->isSuperAdmin()) {
            if ($user->isTenant()) {
                // Tenants are strictly prohibited from viewing operational expenses
                $query->whereRaw('1 = 0');
                return $query;
            }

            $allowedPropertyIds = $user->properties()->pluck('properties.id')->toArray();
            $query->whereIn('property_id', $allowedPropertyIds);
        }

        // Apply filters
        if (!empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (!empty($filters['expense_category_id'])) {
            $query->where('expense_category_id', $filters['expense_category_id']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('expense_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('expense_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query->whereYear('expense_date', $filters['year'])
                ->whereMonth('expense_date', $filters['month']);
        } elseif (!empty($filters['year'])) {
            $query->whereYear('expense_date', $filters['year']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', $search)
                    ->orWhere('title', 'like', $search)
                    ->orWhere('vendor_name', 'like', $search);
            });
        }

        return $query->orderBy('expense_date', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Create an expense draft or directly submit
     */
    public function createExpense(User $user, array $data, ?UploadedFile $receipt = null, bool $submitDirectly = false): Expense
    {
        $this->authorizePropertyAccess($user, (int) $data['property_id']);

        if ($user->isTenant()) {
            throw new DomainException('Penyewa tidak memiliki izin untuk mengajukan pengeluaran operasional.');
        }

        return DB::transaction(function () use ($user, $data, $receipt, $submitDirectly) {
            $property = Property::findOrFail($data['property_id']);
            $category = !empty($data['expense_category_id'])
                ? ExpenseCategory::find($data['expense_category_id'])
                : null;

            $receiptPath = null;
            if ($receipt) {
                $receiptPath = $receipt->store('private/expenses/receipts');
            }

            $expenseNumber = $this->generateExpenseNumber();
            $approvalStatus = $submitDirectly ? ExpenseApprovalStatus::SUBMITTED : ExpenseApprovalStatus::DRAFT;
            $submittedAt = $submitDirectly ? now() : null;

            $expense = Expense::create([
                'expense_number' => $expenseNumber,
                'property_id' => $property->id,
                'expense_category_id' => $category?->id,
                'user_id' => $user->id,
                'title' => $data['title'],
                'category' => $category?->code ?? ($data['category'] ?? 'other'),
                'vendor_name' => $data['vendor_name'] ?? null,
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'] ?? now()->toDateString(),
                'receipt_photo' => $receiptPath,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'approval_status' => $approvalStatus,
                'payment_status' => ExpensePaymentStatus::UNPAID,
                'paid_amount' => 0.00,
                'submitted_at' => $submittedAt,
                'notes' => $data['notes'] ?? null,
            ]);

            ExpenseLog::create([
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'action' => $submitDirectly ? 'submitted' : 'created',
                'from_approval_status' => null,
                'to_approval_status' => $approvalStatus->value,
                'from_payment_status' => null,
                'to_payment_status' => ExpensePaymentStatus::UNPAID->value,
                'notes' => $submitDirectly ? 'Pengeluaran diajukan untuk persetujuan' : 'Draft pengeluaran dibuat',
            ]);

            return $expense;
        });
    }

    /**
     * Update draft expense
     */
    public function updateDraft(User $user, Expense $expense, array $data, ?UploadedFile $receipt = null): Expense
    {
        $this->authorizePropertyAccess($user, $expense->property_id);

        if (!$expense->isDraft()) {
            throw new DomainException('Hanya pengeluaran berstatus draft yang dapat diedit secara langsung.');
        }

        return DB::transaction(function () use ($user, $expense, $data, $receipt) {
            $receiptPath = $expense->receipt_photo;
            if ($receipt) {
                if ($receiptPath) {
                    Storage::delete($receiptPath);
                }
                $receiptPath = $receipt->store('private/expenses/receipts');
            }

            $category = !empty($data['expense_category_id'])
                ? ExpenseCategory::find($data['expense_category_id'])
                : null;

            $expense->update([
                'expense_category_id' => $category?->id ?? $expense->expense_category_id,
                'category' => $category?->code ?? $expense->category,
                'title' => $data['title'] ?? $expense->title,
                'vendor_name' => $data['vendor_name'] ?? $expense->vendor_name,
                'amount' => $data['amount'] ?? $expense->amount,
                'expense_date' => $data['expense_date'] ?? $expense->expense_date,
                'receipt_photo' => $receiptPath,
                'payment_method' => $data['payment_method'] ?? $expense->payment_method,
                'notes' => $data['notes'] ?? $expense->notes,
            ]);

            ExpenseLog::create([
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'action' => 'updated',
                'notes' => 'Draft pengeluaran diperbarui',
            ]);

            return $expense->fresh();
        });
    }

    /**
     * Submit draft for approval
     */
    public function submitForApproval(User $user, Expense $expense): Expense
    {
        $this->authorizePropertyAccess($user, $expense->property_id);

        if (!$expense->isDraft()) {
            throw new DomainException('Hanya pengeluaran berstatus draft yang dapat diajukan untuk persetujuan.');
        }

        return DB::transaction(function () use ($user, $expense) {
            $fromStatus = $expense->approval_status->value;

            $expense->update([
                'approval_status' => ExpenseApprovalStatus::SUBMITTED,
                'submitted_at' => now(),
            ]);

            ExpenseLog::create([
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'action' => 'submitted',
                'from_approval_status' => $fromStatus,
                'to_approval_status' => ExpenseApprovalStatus::SUBMITTED->value,
                'notes' => 'Pengeluaran diajukan untuk persetujuan manajer/owner',
            ]);

            return $expense->fresh();
        });
    }

    /**
     * Approve expense
     * Strict Rule: Submitter cannot approve their own expense (separation of duty).
     * Only Manager, Owner, or Super Admin with property access.
     */
    public function approve(User $user, Expense $expense, ?string $notes = null): Expense
    {
        $this->authorizePropertyAccess($user, $expense->property_id);

        if (!$user->isSuperAdmin() && !$user->isOwner() && !$user->isManager()) {
            throw new DomainException('Hanya Super Admin, Pemilik Properti, atau Pengelola Cabang yang berwenang menyetujui pengeluaran.');
        }

        // Separation of duty guard
        if ($expense->user_id === $user->id && !$user->isSuperAdmin()) {
            throw new DomainException('Pemisahan tugas (Segregation of Duties): Pengaju tidak diperbolehkan menyetujui pengajuannya sendiri.');
        }

        if (!$expense->isSubmitted()) {
            throw new DomainException('Hanya pengeluaran berstatus menunggu persetujuan (submitted) yang dapat disetujui.');
        }

        return DB::transaction(function () use ($user, $expense, $notes) {
            $fromStatus = $expense->approval_status->value;

            $expense->update([
                'approval_status' => ExpenseApprovalStatus::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            ExpenseLog::create([
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'action' => 'approved',
                'from_approval_status' => $fromStatus,
                'to_approval_status' => ExpenseApprovalStatus::APPROVED->value,
                'notes' => $notes ?? 'Pengeluaran disetujui untuk pembayaran',
            ]);

            return $expense->fresh();
        });
    }

    /**
     * Reject expense
     */
    public function reject(User $user, Expense $expense, string $reason): Expense
    {
        $this->authorizePropertyAccess($user, $expense->property_id);

        if (!$user->isSuperAdmin() && !$user->isOwner() && !$user->isManager()) {
            throw new DomainException('Hanya Super Admin, Pemilik Properti, atau Pengelola Cabang yang berwenang menolak pengeluaran.');
        }

        if (empty(trim($reason))) {
            throw new DomainException('Alasan penolakan pengeluaran wajib diisi.');
        }

        if (!$expense->isSubmitted()) {
            throw new DomainException('Hanya pengeluaran yang sedang diajukan yang dapat ditolak.');
        }

        return DB::transaction(function () use ($user, $expense, $reason) {
            $fromStatus = $expense->approval_status->value;

            $expense->update([
                'approval_status' => ExpenseApprovalStatus::REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            ExpenseLog::create([
                'expense_id' => $expense->id,
                'user_id' => $user->id,
                'action' => 'rejected',
                'from_approval_status' => $fromStatus,
                'to_approval_status' => ExpenseApprovalStatus::REJECTED->value,
                'notes' => 'Ditolak dengan alasan: ' . $reason,
            ]);

            return $expense->fresh();
        });
    }

    /**
     * Record payment for approved expense
     * Supports full and partial disbursements, validates non-overpayment, idempotency
     */
    public function recordPayment(User $user, Expense $expense, array $paymentData, ?UploadedFile $proof = null): ExpensePayment
    {
        $this->authorizePropertyAccess($user, $expense->property_id);

        if ($user->isTenant()) {
            throw new DomainException('Penyewa tidak memiliki wewenang mencatat pembayaran pengeluaran.');
        }

        return DB::transaction(function () use ($user, $expense, $paymentData, $proof) {
            // Re-fetch with row lock to prevent race condition double payment
            $lockedExpense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            if (!$lockedExpense->isApproved()) {
                throw new DomainException('Pembayaran hanya dapat dicatat untuk pengeluaran yang telah disetujui (Approved).');
            }

            if ($lockedExpense->isPaid()) {
                throw new DomainException('Pengeluaran ini sudah lunas.');
            }

            $payAmount = round((float) $paymentData['amount'], 2);
            if ($payAmount <= 0) {
                throw new DomainException('Nominal pembayaran harus lebih besar dari 0.');
            }

            $remaining = $lockedExpense->remaining_balance;
            if ($payAmount > $remaining) {
                throw new DomainException("Nominal pembayaran (Rp " . number_format($payAmount, 0, ',', '.') . ") melebihi sisa tagihan pengeluaran (Rp " . number_format($remaining, 0, ',', '.') . ").");
            }

            $proofPath = null;
            if ($proof) {
                $proofPath = $proof->store('private/expenses/payments');
            }

            $paymentNumber = 'EXPPAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $payment = ExpensePayment::create([
                'expense_id' => $lockedExpense->id,
                'payment_number' => $paymentNumber,
                'amount' => $payAmount,
                'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                'payment_method' => $paymentData['payment_method'] ?? 'cash',
                'proof_file' => $proofPath,
                'recorded_by' => $user->id,
                'notes' => $paymentData['notes'] ?? null,
            ]);

            $newPaidSum = round((float) $lockedExpense->paid_amount + $payAmount, 2);
            $newPaymentStatus = ($newPaidSum >= (float) $lockedExpense->amount)
                ? ExpensePaymentStatus::PAID
                : ExpensePaymentStatus::PARTIALLY_PAID;

            $fromPaymentStatus = $lockedExpense->payment_status->value;

            $lockedExpense->update([
                'paid_amount' => $newPaidSum,
                'payment_status' => $newPaymentStatus,
                'paid_by' => $user->id,
                'paid_at' => now(),
            ]);

            ExpenseLog::create([
                'expense_id' => $lockedExpense->id,
                'user_id' => $user->id,
                'action' => 'payment_recorded',
                'from_payment_status' => $fromPaymentStatus,
                'to_payment_status' => $newPaymentStatus->value,
                'notes' => "Pembayaran dicatat: Rp " . number_format($payAmount, 0, ',', '.') . " ({$newPaymentStatus->label()})",
                'metadata' => [
                    'payment_number' => $paymentNumber,
                    'amount' => $payAmount,
                    'method' => $paymentData['payment_method'] ?? 'cash',
                ],
            ]);

            // Phase 8: Otomatis posting jurnal pengeluaran operasional
            $journalService = app(\App\Services\Accounting\JournalPostingService::class);
            $journalService->postExpensePaymentJournal($payment, $user);

            return $payment;
        });
    }

    /**
     * Authorize user access to property
     */
    public function authorizePropertyAccess(User $user, int $propertyId): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        $allowed = $user->properties()->where('properties.id', $propertyId)->exists();
        if (!$allowed) {
            throw new DomainException('Anda tidak memiliki akses ke properti ini.');
        }
    }

    /**
     * Unique expense number generator (EXP-YYYYMMDD-XXXX)
     */
    protected function generateExpenseNumber(): string
    {
        $prefix = 'EXP-' . date('Ymd') . '-';
        $random = strtoupper(bin2hex(random_bytes(2)));

        while (Expense::where('expense_number', $prefix . $random)->exists()) {
            $random = strtoupper(bin2hex(random_bytes(2)));
        }

        return $prefix . $random;
    }
}
