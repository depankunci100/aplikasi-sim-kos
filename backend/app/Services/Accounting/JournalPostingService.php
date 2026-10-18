<?php

namespace App\Services\Accounting;

use App\Enums\DepositMutationType;
use App\Enums\JournalEntryStatus;
use App\Models\CheckOutSettlement;
use App\Models\DepositMutation;
use App\Models\ExpensePayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalPostingService
{
    public function __construct(
        protected ChartOfAccountService $coaService
    ) {}

    /**
     * Memposting jurnal pembukuan berpasangan secara atomik, seimbang, dan idempoten.
     *
     * @param array $lines Format: [['account_id' => int, 'debit' => float, 'credit' => float, 'memo' => ?string], ...]
     */
    public function postJournal(
        int $propertyId,
        string $sourceType,
        ?int $sourceId,
        string $idempotencyKey,
        string $description,
        array $lines,
        Carbon|string $entryDate,
        ?User $actor = null,
        ?string $notes = null
    ): JournalEntry {
        if (count($lines) < 2) {
            throw new DomainException('Jurnal berpasangan minimal harus memiliki 2 baris (Debit dan Kredit).');
        }

        return DB::transaction(function () use (
            $propertyId,
            $sourceType,
            $sourceId,
            $idempotencyKey,
            $description,
            $lines,
            $entryDate,
            $actor,
            $notes
        ) {
            // 1. Cek Idempotensi: Jika jurnal dengan kunci ini sudah ada, kembalikan tanpa duplikasi
            $existing = JournalEntry::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load('lines.account');
            }

            // 2. Validasi Keseimbangan Debit = Kredit & Aturan Baris
            $totalDebit = 0.00;
            $totalCredit = 0.00;

            foreach ($lines as $line) {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit < 0 || $credit < 0) {
                    throw new DomainException('Nilai debit dan kredit baris jurnal tidak boleh negatif.');
                }

                if ($debit > 0 && $credit > 0) {
                    throw new DomainException('Satu baris jurnal dilarang memuat nilai debit dan kredit positif secara bersamaan.');
                }

                if ($debit === 0.0 && $credit === 0.0) {
                    continue; // Abaikan baris bernilai 0
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);

            if ($totalDebit <= 0.0) {
                throw new DomainException('Total nilai transaksi jurnal harus lebih dari 0.');
            }

            if (abs($totalDebit - $totalCredit) > 0.001) {
                throw new DomainException(
                    "Jurnal tidak seimbang! Total Debit (Rp " . number_format($totalDebit, 2, ',', '.') .
                    ") tidak sama dengan Total Kredit (Rp " . number_format($totalCredit, 2, ',', '.') . ")."
                );
            }

            // 3. Generate Kode Nomor Jurnal Unik
            $dateObj = $entryDate instanceof Carbon ? $entryDate : Carbon::parse($entryDate);
            $journalNumber = 'JRN-' . $dateObj->format('Ymd') . '-' . strtoupper(Str::random(6));

            // 4. Simpan Header Jurnal
            $journal = JournalEntry::create([
                'journal_number' => $journalNumber,
                'property_id' => $propertyId,
                'entry_date' => $dateObj->toDateString(),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'idempotency_key' => $idempotencyKey,
                'status' => JournalEntryStatus::POSTED,
                'description' => $description,
                'notes' => $notes,
                'total_amount' => $totalDebit,
                'created_by' => $actor?->id,
                'posted_at' => now(),
            ]);

            // 5. Simpan Baris-baris Jurnal
            foreach ($lines as $line) {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit === 0.0 && $credit === 0.0) {
                    continue;
                }

                JournalLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $journal->load('lines.account');
        });
    }

    /**
     * Memposting jurnal otomatis dari pembayaran yang terverifikasi (Payment sah).
     */
    public function postPaymentJournal(Payment $payment, User $actor): array
    {
        $invoice = $payment->invoice;
        if (!$invoice) {
            return [];
        }

        $propertyId = $payment->property_id ?? $invoice->property_id;
        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return [];
        }

        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $cashOrBankAcc = $this->coaService->getCashOrBankAccount($propertyId, $payment->payment_method ?? 'bank_transfer');
        $paymentDate = $payment->verified_at ? Carbon::parse($payment->verified_at) : now();
        $results = [];

        // 1. Cek apakah pembayaran mencakup penerimaan uang jaminan (Security Deposit)
        $booking = $invoice->booking;
        $depositPortion = 0.00;

        if ($booking && (float) $booking->deposit_amount > 0) {
            // Dapatkan mutasi deposit yang terkait payment ini jika ada
            $mutation = DepositMutation::where('payment_id', $payment->id)->first();
            if ($mutation) {
                $depositPortion = (float) $mutation->amount;
            } elseif ($invoice->invoice_type === 'deposit') {
                $depositPortion = $amount;
            }
        }

        // Posting Jurnal Deposit jika ada
        if ($depositPortion > 0) {
            $depositAcc = $this->coaService->getAccountByCode($propertyId, '2101'); // Titipan Uang Jaminan
            $idempotencyKey = "payment:{$payment->id}:deposit";

            $desc = "Penerimaan uang jaminan/deposit kamar " . ($booking?->room?->room_number ?? '') .
                " via {$payment->payment_code}";

            $lines = [
                [
                    'account_id' => $cashOrBankAcc->id,
                    'debit' => $depositPortion,
                    'credit' => 0.00,
                    'memo' => "Penerimaan deposit via {$payment->payment_method}",
                ],
                [
                    'account_id' => $depositAcc->id,
                    'debit' => 0.00,
                    'credit' => $depositPortion,
                    'memo' => "Kewajiban titipan deposit pemesanan " . ($booking?->booking_code ?? ''),
                ],
            ];

            $results[] = $this->postJournal(
                $propertyId,
                'payment',
                $payment->id,
                $idempotencyKey,
                $desc,
                $lines,
                $paymentDate,
                $actor,
                "Referensi Pembayaran: {$payment->payment_code}"
            );
        }

        // 2. Sisa pembayaran adalah untuk Sewa Kamar, Guesthouse, atau Utilitas
        $rentOrUtilityAmount = round($amount - $depositPortion, 2);

        if ($rentOrUtilityAmount > 0) {
            $invType = $invoice->invoice_type ?? 'rent';

            // Cek apakah invoice ini murni tagihan utilitas
            $isPureUtility = in_array($invType, ['electricity', 'water', 'utility']);

            // Cek apakah invoice memiliki item utilitas terpisah (misal digabung dalam tagihan sewa)
            $utilityItemsSum = 0.00;
            if (!$isPureUtility && ($invoice->relationLoaded('items') || $invoice->items()->exists())) {
                $utilityItemsSum = (float) $invoice->items()
                    ->whereIn('item_type', ['electricity', 'water', 'utility'])
                    ->sum('amount');
            }

            $utilityPortion = $isPureUtility ? $rentOrUtilityAmount : min($rentOrUtilityAmount, $utilityItemsSum);
            $rentPortion = round($rentOrUtilityAmount - $utilityPortion, 2);

            // 1. Posting Jurnal Utilitas (jika ada porsi utilitas)
            if ($utilityPortion > 0) {
                $utilityAcc = $this->coaService->getAccountByCode($propertyId, '4103'); // Pendapatan Utilitas
                $idempotencyKey = "payment:{$payment->id}:utility";
                $desc = "Penerimaan pembayaran tagihan utilitas ({$invoice->invoice_number})";

                $lines = [
                    [
                        'account_id' => $cashOrBankAcc->id,
                        'debit' => $utilityPortion,
                        'credit' => 0.00,
                        'memo' => "Pembayaran utilitas via {$payment->payment_method}",
                    ],
                    [
                        'account_id' => $utilityAcc->id,
                        'debit' => 0.00,
                        'credit' => $utilityPortion,
                        'memo' => "Pendapatan utilitas invoice {$invoice->invoice_number}",
                    ],
                ];

                $results[] = $this->postJournal(
                    $propertyId,
                    'payment',
                    $payment->id,
                    $idempotencyKey,
                    $desc,
                    $lines,
                    $paymentDate,
                    $actor
                );
            }

            // 2. Posting Jurnal Sewa (jika ada porsi sewa)
            if ($rentPortion > 0) {
                $isGuesthouse = ($booking && (
                    ($booking->rental_type instanceof \App\Enums\RentalType && $booking->rental_type === \App\Enums\RentalType::DAILY) ||
                    $booking->rental_type === 'daily' ||
                    $booking->rental_type?->value === 'daily'
                ));

                // Evaluasi Periode Pengakuan:
                // Jika tanggal mulai sewa > tanggal bayar, catat sebagai Pendapatan Diterima di Muka (2102)
                $isAdvancePayment = false;
                if ($invoice->period_start && $invoice->period_start->isAfter($paymentDate->toDateString())) {
                    $isAdvancePayment = true;
                }

                if ($isAdvancePayment) {
                    $creditAcc = $this->coaService->getAccountByCode($propertyId, '2102'); // Pendapatan Diterima di Muka
                    $memoCredit = "Pendapatan sewa diterima di muka periode " . ($invoice->period_start?->format('d/m/Y') ?? '-');
                    $idempotencyKey = "payment:{$payment->id}:advance_rent";
                    $desc = "Penerimaan sewa di muka / DP ({$invoice->invoice_number})";
                } else {
                    $accCode = $isGuesthouse ? '4102' : '4101'; // 4102 Guesthouse, 4101 Kos
                    $creditAcc = $this->coaService->getAccountByCode($propertyId, $accCode);
                    $memoCredit = "Pendapatan sewa kamar periode berjalan ({$invoice->invoice_number})";
                    $idempotencyKey = "payment:{$payment->id}:rent";
                    $desc = "Penerimaan sewa kamar " . ($isGuesthouse ? 'Guesthouse' : 'Kos') . " ({$invoice->invoice_number})";
                }

                $lines = [
                    [
                        'account_id' => $cashOrBankAcc->id,
                        'debit' => $rentPortion,
                        'credit' => 0.00,
                        'memo' => "Penerimaan sewa via {$payment->payment_method}",
                    ],
                    [
                        'account_id' => $creditAcc->id,
                        'debit' => 0.00,
                        'credit' => $rentPortion,
                        'memo' => $memoCredit,
                    ],
                ];

                $results[] = $this->postJournal(
                    $propertyId,
                    'payment',
                    $payment->id,
                    $idempotencyKey,
                    $desc,
                    $lines,
                    $paymentDate,
                    $actor
                );
            }
        }

        return $results;
    }

    /**
     * Memposting jurnal otomatis dari pencairan biaya operasional (Expense Payment).
     */
    public function postExpensePaymentJournal(ExpensePayment $expensePayment, User $actor): JournalEntry
    {
        $expense = $expensePayment->expense;
        $propertyId = $expense->property_id;
        $amount = (float) $expensePayment->amount;

        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        // Akun Beban Debit
        $expenseCategoryCode = $expense->expenseCategory?->code ?? $expense->category;
        $expenseAcc = $this->coaService->getExpenseAccountByCategory($propertyId, $expenseCategoryCode);

        // Akun Kas / Bank Kredit
        $cashOrBankAcc = $this->coaService->getCashOrBankAccount($propertyId, $expensePayment->payment_method ?? 'cash');

        $idempotencyKey = "expense_payment:{$expensePayment->id}";
        $desc = "Pencairan beban operasional: {$expense->title} ({$expensePayment->payment_number})";

        $lines = [
            [
                'account_id' => $expenseAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'memo' => "Beban: {$expense->title}" . ($expense->vendor_name ? " (Vendor: {$expense->vendor_name})" : ''),
            ],
            [
                'account_id' => $cashOrBankAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'memo' => "Pencairan kas/bank pengeluaran {$expensePayment->payment_number}",
            ],
        ];

        return $this->postJournal(
            $propertyId,
            'expense_payment',
            $expensePayment->id,
            $idempotencyKey,
            $desc,
            $lines,
            $expensePayment->payment_date ?? now(),
            $actor,
            "No. Beban: {$expense->expense_number}"
        );
    }

    /**
     * Memposting jurnal otomatis dari finalisasi check-out settlement (potongan deposit & refund).
     */
    public function postCheckOutSettlementJournals(CheckOutSettlement $settlement, User $actor): array
    {
        $propertyId = $settlement->property_id;
        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $depositAcc = $this->coaService->getAccountByCode($propertyId, '2101'); // Titipan Uang Jaminan
        $damageRevenueAcc = $this->coaService->getAccountByCode($propertyId, '4104'); // Pendapatan Denda & Ganti Rugi Kerusakan
        $cashOrBankAcc = $this->coaService->getCashOrBankAccount($propertyId, $settlement->refund_method ?? 'bank_transfer');

        $results = [];
        $settlementDate = $settlement->refunded_at ? Carbon::parse($settlement->refunded_at) : now();

        // 1. Jurnal Potongan Kerusakan / Denda (mengurangi deposit, mengakui pendapatan denda sah)
        $totalApprovedDeductions = 0.00;
        foreach ($settlement->deductions as $deduction) {
            if ($deduction->is_approved && (float) $deduction->approved_amount > 0) {
                $totalApprovedDeductions += (float) $deduction->approved_amount;
            }
        }

        // Potongan yang dapat ditutup dari deposit dibatasi oleh saldo deposit sebelum settlement
        $depositBalanceBefore = (float) $settlement->deposit_balance_before;
        $deductedFromDeposit = min($totalApprovedDeductions, $depositBalanceBefore);
        $excessDamage = round(max(0, $totalApprovedDeductions - $depositBalanceBefore), 2);

        if ($deductedFromDeposit > 0) {
            $idempotencyKey = "settlement:{$settlement->id}:deduction";
            $desc = "Pemotongan uang jaminan check-out untuk denda/kerusakan ({$settlement->settlement_code})";

            $lines = [
                [
                    'account_id' => $depositAcc->id,
                    'debit' => $deductedFromDeposit,
                    'credit' => 0.00,
                    'memo' => "Pelepasan kewajiban deposit atas denda check-out",
                ],
                [
                    'account_id' => $damageRevenueAcc->id,
                    'debit' => 0.00,
                    'credit' => $deductedFromDeposit,
                    'memo' => "Pendapatan sah denda kerusakan kamar {$settlement->settlement_code}",
                ],
            ];

            $results[] = $this->postJournal(
                $propertyId,
                'check_out_settlement',
                $settlement->id,
                $idempotencyKey,
                $desc,
                $lines,
                $settlementDate,
                $actor
            );
        }

        // 1b. Jurnal Denda Kerusakan Melebihi Saldo Deposit (Piutang Denda 1203 vs Pendapatan Denda 4104)
        if ($excessDamage > 0) {
            $receivableDendaAcc = $this->coaService->getAccountByCode($propertyId, '1203'); // Piutang Denda & Ganti Rugi
            $idempotencyKey = "settlement:{$settlement->id}:excess_damage";
            $desc = "Tagihan piutang denda kerusakan check-out melebihi saldo deposit ({$settlement->settlement_code})";

            $lines = [
                [
                    'account_id' => $receivableDendaAcc->id,
                    'debit' => $excessDamage,
                    'credit' => 0.00,
                    'memo' => "Piutang denda kerusakan kamar melebihi deposit",
                ],
                [
                    'account_id' => $damageRevenueAcc->id,
                    'debit' => 0.00,
                    'credit' => $excessDamage,
                    'memo' => "Pendapatan denda kerusakan kamar {$settlement->settlement_code}",
                ],
            ];

            $results[] = $this->postJournal(
                $propertyId,
                'check_out_settlement',
                $settlement->id,
                $idempotencyKey,
                $desc,
                $lines,
                $settlementDate,
                $actor
            );
        }

        // 2. Jurnal Pengembalian Sisa Deposit ke Penyewa (Net Refund Payout)
        $netRefund = (float) $settlement->net_refund_amount;
        if ($netRefund > 0) {
            $idempotencyKey = "settlement:{$settlement->id}:refund";
            $desc = "Pengembalian sisa uang jaminan check-out ke penyewa ({$settlement->settlement_code})";

            $lines = [
                [
                    'account_id' => $depositAcc->id,
                    'debit' => $netRefund,
                    'credit' => 0.00,
                    'memo' => "Pelepasan kewajiban sisa deposit yang dikembalikan",
                ],
                [
                    'account_id' => $cashOrBankAcc->id,
                    'debit' => 0.00,
                    'credit' => $netRefund,
                    'memo' => "Pencairan refund deposit ke " . ($settlement->refund_destination ?? 'rekening penyewa'),
                ],
            ];

            $results[] = $this->postJournal(
                $propertyId,
                'check_out_settlement',
                $settlement->id,
                $idempotencyKey,
                $desc,
                $lines,
                $settlementDate,
                $actor
            );
        }

        return $results;
    }

    /**
     * Memposting jurnal otomatis dari mutasi refund deposit tunggal (misal pembatalan booking).
     */
    public function postDepositRefundJournal(DepositMutation $mutation, User $actor): ?JournalEntry
    {
        if ($mutation->transaction_type !== DepositMutationType::REFUND_PAYOUT && $mutation->direction !== 'out') {
            return null;
        }

        $ledger = $mutation->depositLedger ?: \App\Models\DepositLedger::find($mutation->deposit_ledger_id);
        $propertyId = $ledger?->property_id;
        if (!$propertyId) {
            return null;
        }

        $this->coaService->ensureDefaultAccountsForProperty($propertyId);

        $depositAcc = $this->coaService->getAccountByCode($propertyId, '2101');
        $cashOrBankAcc = $this->coaService->getCashOrBankAccount($propertyId, $mutation->payment_method ?? 'bank_transfer');

        $amount = (float) $mutation->amount;
        $idempotencyKey = "deposit_mutation:{$mutation->id}:refund";
        $desc = "Pengembalian deposit penyewa: {$mutation->mutation_code}";

        $lines = [
            [
                'account_id' => $depositAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'memo' => "Pengurangan kewajiban deposit ({$mutation->mutation_code})",
            ],
            [
                'account_id' => $cashOrBankAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'memo' => "Pencairan uang refund deposit via {$mutation->payment_method}",
            ],
        ];

        return $this->postJournal(
            $propertyId,
            'deposit_mutation',
            $mutation->id,
            $idempotencyKey,
            $desc,
            $lines,
            $mutation->created_at ?? now(),
            $actor
        );
    }

    /**
     * Reversal Jurnal: Membatalkan jurnal terposting dengan membuat jurnal pembalik yang seimbang.
     */
    public function reverseJournal(JournalEntry $journal, User $actor, string $reason): JournalEntry
    {
        if (empty(trim($reason))) {
            throw new DomainException('Alasan pembalikan jurnal wajib diisi.');
        }

        return DB::transaction(function () use ($journal, $actor, $reason) {
            $lockedJournal = JournalEntry::where('id', $journal->id)->lockForUpdate()->firstOrFail();

            if ($lockedJournal->status === JournalEntryStatus::REVERSED) {
                throw new DomainException("Jurnal #{$lockedJournal->journal_number} sudah pernah dibalik sebelumnya.");
            }

            if ($lockedJournal->status !== JournalEntryStatus::POSTED) {
                throw new DomainException("Hanya jurnal berstatus Diposting (Posted) yang dapat dibalik.");
            }

            // Tandai jurnal lama sebagai REVERSED
            $lockedJournal->status = JournalEntryStatus::REVERSED;
            $lockedJournal->notes = ($lockedJournal->notes ? $lockedJournal->notes . " | " : "") . "Dibalik oleh {$actor->name}: {$reason}";
            $lockedJournal->save();

            // Susun baris pembalik (tukar posisi debit dan kredit)
            $reversedLines = [];
            foreach ($lockedJournal->lines as $line) {
                $reversedLines[] = [
                    'account_id' => $line->account_id,
                    'debit' => (float) $line->credit, // Tukar!
                    'credit' => (float) $line->debit, // Tukar!
                    'memo' => "Pembalik: " . ($line->memo ?: "Baris Jurnal #{$lockedJournal->journal_number}"),
                ];
            }

            $idempotencyKey = "reversal:{$lockedJournal->id}";
            $reversalDesc = "Pembalik atas Jurnal #{$lockedJournal->journal_number}: {$reason}";

            $dateObj = now();
            $reversalJournalNumber = 'REV-' . $dateObj->format('Ymd') . '-' . strtoupper(Str::random(6));

            $reversalEntry = JournalEntry::create([
                'journal_number' => $reversalJournalNumber,
                'property_id' => $lockedJournal->property_id,
                'entry_date' => $dateObj->toDateString(),
                'source_type' => 'reversal',
                'source_id' => $lockedJournal->id,
                'idempotency_key' => $idempotencyKey,
                'status' => JournalEntryStatus::POSTED,
                'description' => $reversalDesc,
                'notes' => "Alasan Pembalikan: {$reason}",
                'total_amount' => $lockedJournal->total_amount,
                'created_by' => $actor->id,
                'posted_at' => now(),
                'reversed_journal_id' => $lockedJournal->id,
            ]);

            foreach ($reversedLines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'memo' => $line['memo'],
                ]);
            }

            return $reversalEntry->load('lines.account');
        });
    }
}
