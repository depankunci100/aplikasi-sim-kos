<?php

namespace App\Console\Commands;

use App\Enums\DepositMutationType;
use App\Enums\ExpenseApprovalStatus;
use App\Enums\SettlementStatus;
use App\Models\CheckOutSettlement;
use App\Models\DepositMutation;
use App\Models\ExpensePayment;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Console\Command;
use Throwable;

class BackfillJournalsCommand extends Command
{
    protected $signature = 'finance:backfill-journals
                            {--property= : Filter properti ID tertentu}
                            {--dry-run : Jalankan simulasi tanpa menulis data jurnal ke basis data}';

    protected $description = 'Backfill jurnal pembukuan berpasangan historis dari transaksi operasional yang sah secara aman dan idempoten';

    public function handle(JournalPostingService $journalService): int
    {
        $propertyId = $this->option('property') ? (int) $this->option('property') : null;
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("=========================================================");
        $this->info(" SIM KOS & GUESTHOUSE — BACKFILL JURNAL HISTORIS (PHASE 8)");
        $this->info("=========================================================");

        if ($isDryRun) {
            $this->warn(" [MODE SIMULASI / DRY-RUN AKTIF]");
            $this->warn(" Tidak ada data jurnal yang akan disimpan ke basis data.");
        }

        if ($propertyId) {
            $property = Property::find($propertyId);
            if (!$property) {
                $this->error("Properti dengan ID {$propertyId} tidak ditemukan.");
                return 1;
            }
            $this->line(" Properti Target: #{$property->id} - {$property->name}");
        } else {
            $this->line(" Properti Target: Semua Properti Terdaftar");
        }

        $systemUser = User::whereIn('role', ['super_admin', 'owner', 'manager'])->first() ?? User::first();

        $stats = [
            'payments' => ['candidates' => 0, 'posted' => 0, 'skipped' => 0, 'failed' => 0],
            'expenses' => ['candidates' => 0, 'posted' => 0, 'skipped' => 0, 'failed' => 0],
            'settlements' => ['candidates' => 0, 'posted' => 0, 'skipped' => 0, 'failed' => 0],
            'refunds' => ['candidates' => 0, 'posted' => 0, 'skipped' => 0, 'failed' => 0],
        ];

        $exceptions = [];

        // 1. Backfill Verified Payments (Penerimaan Sewa / Utilitas / Deposit)
        $this->newLine();
        $this->comment("1. Memproses Pembayaran Terverifikasi (Verified Payments)...");

        $paymentsQuery = Payment::with(['invoice.booking', 'invoice.property'])
            ->where('status', 'verified');

        if ($propertyId) {
            $paymentsQuery->whereHas('invoice', fn($q) => $q->where('property_id', $propertyId));
        }

        $payments = $paymentsQuery->get();
        $stats['payments']['candidates'] = $payments->count();

        foreach ($payments as $payment) {
            try {
                $targetPropId = $payment->property_id ?? $payment->invoice?->property_id;
                if (!$targetPropId) {
                    $stats['payments']['failed']++;
                    $exceptions[] = "Payment #{$payment->payment_code}: Tidak memiliki asosiasi properti valid.";
                    continue;
                }

                $keys = [
                    "payment:{$payment->id}:rent",
                    "payment:{$payment->id}:advance_rent",
                    "payment:{$payment->id}:deposit",
                    "payment:{$payment->id}:utility",
                ];

                $alreadyPosted = JournalEntry::whereIn('idempotency_key', $keys)->exists();

                if ($alreadyPosted) {
                    $stats['payments']['skipped']++;
                } else {
                    if (!$isDryRun && $systemUser) {
                        $journalService->postPaymentJournal($payment, $systemUser);
                    }
                    $stats['payments']['posted']++;
                }
            } catch (Throwable $e) {
                $stats['payments']['failed']++;
                $exceptions[] = "Payment #{$payment->payment_code}: " . $e->getMessage();
            }
        }

        // 2. Backfill Approved Expense Payments (Pencairan Pengeluaran Operasional)
        $this->comment("2. Memproses Pencairan Beban Operasional (Expense Payments)...");

        $expensePaymentsQuery = ExpensePayment::with(['expense'])
            ->whereHas('expense', fn($q) => $q->where('approval_status', ExpenseApprovalStatus::APPROVED->value));

        if ($propertyId) {
            $expensePaymentsQuery->whereHas('expense', fn($q) => $q->where('property_id', $propertyId));
        }

        $expensePayments = $expensePaymentsQuery->get();
        $stats['expenses']['candidates'] = $expensePayments->count();

        foreach ($expensePayments as $ep) {
            try {
                $targetPropId = $ep->expense?->property_id;
                if (!$targetPropId) {
                    $stats['expenses']['failed']++;
                    $exceptions[] = "ExpensePayment #{$ep->payment_number}: Expense tidak memiliki property_id.";
                    continue;
                }

                $key = "expense_payment:{$ep->id}";
                $alreadyPosted = JournalEntry::where('idempotency_key', $key)->exists();

                if ($alreadyPosted) {
                    $stats['expenses']['skipped']++;
                } else {
                    if (!$isDryRun && $systemUser) {
                        $journalService->postExpensePaymentJournal($ep, $systemUser);
                    }
                    $stats['expenses']['posted']++;
                }
            } catch (Throwable $e) {
                $stats['expenses']['failed']++;
                $exceptions[] = "ExpensePayment #{$ep->payment_number}: " . $e->getMessage();
            }
        }

        // 3. Backfill Check-Out Settlements (Potongan Kerusakan & Net Refund)
        $this->comment("3. Memproses Penyelesaian Check-Out (Settlements)...");

        $settlementsQuery = CheckOutSettlement::with(['deductions', 'depositLedger'])
            ->where('status', SettlementStatus::SETTLED->value);

        if ($propertyId) {
            $settlementsQuery->where('property_id', $propertyId);
        }

        $settlements = $settlementsQuery->get();
        $stats['settlements']['candidates'] = $settlements->count();

        foreach ($settlements as $settlement) {
            try {
                $keys = [
                    "settlement:{$settlement->id}:deduction",
                    "settlement:{$settlement->id}:excess_damage",
                    "settlement:{$settlement->id}:refund",
                ];

                $alreadyPosted = JournalEntry::whereIn('idempotency_key', $keys)->exists();

                if ($alreadyPosted) {
                    $stats['settlements']['skipped']++;
                } else {
                    if (!$isDryRun && $systemUser) {
                        $journalService->postCheckOutSettlementJournals($settlement, $systemUser);
                    }
                    $stats['settlements']['posted']++;
                }
            } catch (Throwable $e) {
                $stats['settlements']['failed']++;
                $exceptions[] = "Settlement #{$settlement->settlement_code}: " . $e->getMessage();
            }
        }

        // 4. Backfill Standalone Deposit Refund Mutations
        $this->comment("4. Memproses Mutasi Pengembalian Deposit Tunggal (Deposit Refunds)...");

        $refundQuery = DepositMutation::with('depositLedger')
            ->where('transaction_type', DepositMutationType::REFUND_PAYOUT->value)
            ->where('direction', 'out');

        if ($propertyId) {
            $refundQuery->whereHas('depositLedger', fn($q) => $q->where('property_id', $propertyId));
        }

        $refunds = $refundQuery->get();
        $stats['refunds']['candidates'] = $refunds->count();

        foreach ($refunds as $ref) {
            try {
                // Jangan duplikasi jika mutasi refund ini berasal dari settlement yang sudah diproses
                $key = "deposit_mutation:{$ref->id}:refund";
                $alreadyPosted = JournalEntry::where('idempotency_key', $key)->exists();

                if ($alreadyPosted) {
                    $stats['refunds']['skipped']++;
                } else {
                    if (!$isDryRun && $systemUser) {
                        $journalService->postDepositRefundJournal($ref, $systemUser);
                    }
                    $stats['refunds']['posted']++;
                }
            } catch (Throwable $e) {
                $stats['refunds']['failed']++;
                $exceptions[] = "DepositMutation #{$ref->mutation_code}: " . $e->getMessage();
            }
        }

        // Tampilkan Ringkasan Eksekusi
        $this->newLine();
        $this->info("--- RINGKASAN EKSEKUSI BACKFILL JURNAL ---");

        $headers = ['Modul Transaksi', 'Kandidat', $isDryRun ? 'Siap Diposting' : 'Berhasil Diposting', 'Sudah Ada (Skipped)', 'Gagal / Peringatan'];
        $rows = [
            ['Penerimaan Pembayaran (Payments)', $stats['payments']['candidates'], $stats['payments']['posted'], $stats['payments']['skipped'], $stats['payments']['failed']],
            ['Pencairan Beban (Expenses)', $stats['expenses']['candidates'], $stats['expenses']['posted'], $stats['expenses']['skipped'], $stats['expenses']['failed']],
            ['Penyelesaian Check-Out (Settlements)', $stats['settlements']['candidates'], $stats['settlements']['posted'], $stats['settlements']['skipped'], $stats['settlements']['failed']],
            ['Pengembalian Deposit (Refunds)', $stats['refunds']['candidates'], $stats['refunds']['posted'], $stats['refunds']['skipped'], $stats['refunds']['failed']],
        ];

        $this->table($headers, $rows);

        if (!empty($exceptions)) {
            $this->newLine();
            $this->warn("Daftar Peringatan / Exception (" . count($exceptions) . "):");
            foreach (array_slice($exceptions, 0, 10) as $ex) {
                $this->line(" - " . $ex);
            }
            if (count($exceptions) > 10) {
                $this->line(" ... dan " . (count($exceptions) - 10) . " exception lainnya.");
            }
        }

        $totalPosted = array_sum(array_column($stats, 'posted'));
        $this->newLine();
        if ($isDryRun) {
            $this->info("Simulasi selesai: {$totalPosted} transaksi siap diposting ke jurnal.");
        } else {
            $this->info("Backfill selesai: {$totalPosted} jurnal transaksi historis berhasil diposting.");
        }

        return 0;
    }
}
