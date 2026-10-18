<?php

namespace App\Services\Deposit;

use App\Enums\BookingStatus;
use App\Enums\DepositMutationType;
use App\Enums\DepositStatus;
use App\Enums\RoomStatus;
use App\Enums\SettlementStatus;
use App\Models\Booking;
use App\Models\CheckOutSettlement;
use App\Models\DepositLedger;
use App\Models\DepositMutation;
use App\Models\Inspection;
use App\Models\Room;
use App\Models\SettlementDeduction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DepositService
{
    /**
     * Dapatkan atau buat DepositLedger untuk booking tertentu.
     * Saldo awal adalah 0 sampai bukti bayar diverifikasi sah.
     */
    public function getOrCreateLedgerForBooking(Booking $booking): DepositLedger
    {
        return DB::transaction(function () use ($booking) {
            $ledger = DepositLedger::where('booking_id', $booking->id)->lockForUpdate()->first();

            if (!$ledger) {
                $code = 'DEP-' . date('Ymd') . '-' . strtoupper(Str::random(5));
                $ledger = DepositLedger::create([
                    'deposit_code' => $code,
                    'booking_id' => $booking->id,
                    'property_id' => $booking->property_id,
                    'tenant_id' => $booking->user_id,
                    'initial_deposit_amount' => $booking->deposit_amount ?: 0,
                    'total_received' => 0,
                    'total_deductions' => 0,
                    'total_refunded' => 0,
                    'current_balance' => 0,
                    'status' => ($booking->deposit_amount > 0) ? DepositStatus::PENDING : DepositStatus::SETTLED,
                    'notes' => 'Ledger uang jaminan dibuat otomatis untuk pemesanan ' . $booking->booking_code,
                ]);
            }

            return $ledger;
        });
    }

    /**
     * Catat penerimaan uang deposit (verified cash-in).
     * Menerapkan pessimistic lock untuk mencegah race condition.
     */
    public function recordDepositReceived(
        DepositLedger $ledger,
        float $amount,
        User $actor,
        array $options = []
    ): DepositMutation {
        if ($amount <= 0) {
            throw new DomainException('Nominal penerimaan deposit harus lebih dari 0.');
        }

        return DB::transaction(function () use ($ledger, $amount, $actor, $options) {
            $locked = DepositLedger::where('id', $ledger->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) $locked->current_balance;
            $balanceAfter = $balanceBefore + $amount;

            $locked->total_received = (float) $locked->total_received + $amount;
            $locked->current_balance = $balanceAfter;

            if ($balanceAfter > 0) {
                $locked->status = DepositStatus::HELD;
            }
            $locked->save();

            $mutationCode = 'MUT-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            return DepositMutation::create([
                'deposit_ledger_id' => $locked->id,
                'mutation_code' => $mutationCode,
                'transaction_type' => DepositMutationType::DEPOSIT_RECEIVED,
                'direction' => 'in',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_method' => $options['payment_method'] ?? 'bank_transfer',
                'payment_reference' => $options['payment_reference'] ?? null,
                'invoice_id' => $options['invoice_id'] ?? null,
                'payment_id' => $options['payment_id'] ?? null,
                'performed_by' => $actor->id,
                'notes' => $options['notes'] ?? 'Penerimaan uang jaminan/deposit terverifikasi.',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Catat pengembalian deposit (refund payout) secara aman.
     * Mencegah over-refund dan race condition.
     */
    public function recordRefundPayout(
        DepositLedger $ledger,
        float $amount,
        User $actor,
        array $refundDetails
    ): DepositMutation {
        if ($amount <= 0) {
            throw new DomainException('Nominal pengembalian deposit harus lebih dari 0.');
        }

        return DB::transaction(function () use ($ledger, $amount, $actor, $refundDetails) {
            $locked = DepositLedger::where('id', $ledger->id)->lockForUpdate()->firstOrFail();

            $currentBal = (float) $locked->current_balance;

            // Aturan Kritis: Mencegah over-refund
            if ($amount > $currentBal) {
                throw new DomainException(
                    "Nominal pengembalian (Rp " . number_format($amount, 0, ',', '.') . ") melebihi saldo deposit yang tersedia (Rp " . number_format($currentBal, 0, ',', '.') . ")."
                );
            }

            $balanceBefore = $currentBal;
            $balanceAfter = $balanceBefore - $amount;

            $locked->total_refunded = (float) $locked->total_refunded + $amount;
            $locked->current_balance = $balanceAfter;

            if ($balanceAfter <= 0) {
                $locked->status = DepositStatus::REFUNDED;
            } else {
                $locked->status = DepositStatus::PARTIALLY_REFUNDED;
            }
            $locked->save();

            $mutationCode = 'MUT-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $mutation = DepositMutation::create([
                'deposit_ledger_id' => $locked->id,
                'mutation_code' => $mutationCode,
                'transaction_type' => DepositMutationType::REFUND_PAYOUT,
                'direction' => 'out',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_method' => $refundDetails['payment_method'] ?? 'bank_transfer',
                'payment_reference' => $refundDetails['payment_reference'] ?? null,
                'performed_by' => $actor->id,
                'notes' => $refundDetails['notes'] ?? 'Pengembalian deposit ke rekening penyewa: ' . ($refundDetails['destination'] ?? '-'),
                'created_at' => now(),
            ]);

            // Phase 8: Otomatis posting jurnal pengembalian deposit tunggal
            $journalService = app(\App\Services\Accounting\JournalPostingService::class);
            $journalService->postDepositRefundJournal($mutation, $actor);

            return $mutation;
        });
    }

    /**
     * Catat penyesuaian/koreksi (adjustment) dengan audit trail lengkap.
     */
    public function recordAdjustment(
        DepositLedger $ledger,
        float $amount,
        string $type,
        User $actor,
        string $reason
    ): DepositMutation {
        if ($amount <= 0) {
            throw new DomainException('Nominal koreksi harus lebih dari 0.');
        }

        if (empty(trim($reason))) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penyesuaian koreksi wajib diisi untuk keperluan audit trail.',
            ]);
        }

        return DB::transaction(function () use ($ledger, $amount, $type, $actor, $reason) {
            $locked = DepositLedger::where('id', $ledger->id)->lockForUpdate()->firstOrFail();

            $mutationType = DepositMutationType::tryFrom($type);
            if (!in_array($mutationType, [DepositMutationType::ADJUSTMENT_CREDIT, DepositMutationType::ADJUSTMENT_DEBIT])) {
                throw new DomainException('Tipe koreksi tidak valid.');
            }

            $balanceBefore = (float) $locked->current_balance;

            if ($mutationType === DepositMutationType::ADJUSTMENT_DEBIT) {
                if ($amount > $balanceBefore) {
                    throw new DomainException('Koreksi debit tidak boleh melebihi saldo deposit yang ada.');
                }
                $balanceAfter = $balanceBefore - $amount;
            } else {
                $balanceAfter = $balanceBefore + $amount;
            }

            $locked->current_balance = $balanceAfter;
            $locked->save();

            $mutationCode = 'MUT-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            return DepositMutation::create([
                'deposit_ledger_id' => $locked->id,
                'mutation_code' => $mutationCode,
                'transaction_type' => $mutationType,
                'direction' => $mutationType->direction(),
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'performed_by' => $actor->id,
                'notes' => '[KOREKSI AUDIT] ' . $reason,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Inisiasi Check-Out Settlement baru dengan integrasi temuan inspeksi kamar (Phase 3).
     */
    public function initiateCheckOutSettlement(
        Booking $booking,
        User $actor,
        array $payload
    ): CheckOutSettlement {
        return DB::transaction(function () use ($booking, $actor, $payload) {
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedBooking->status, [BookingStatus::ACTIVE, BookingStatus::CONFIRMED, BookingStatus::OVERSTAY])) {
                throw new DomainException("Check-out hanya dapat diproses untuk pemesanan yang sedang Aktif atau Overstay. Status saat ini: {$lockedBooking->status->label()}.");
            }

            // Cegah duplikasi inisiasi settlement check-out untuk booking yang sama
            $existing = CheckOutSettlement::where('booking_id', $lockedBooking->id)
                ->whereNotIn('status', [SettlementStatus::CANCELLED])
                ->first();

            if ($existing) {
                throw new DomainException("Sudah ada proses settlement check-out aktif ({$existing->settlement_code}) dengan status '{$existing->status->label()}'.");
            }

            $ledger = $this->getOrCreateLedgerForBooking($lockedBooking);
            $currentBalance = (float) $ledger->current_balance;

            $checkOutDate = $payload['check_out_date'] ?? now()->toDateString();
            $inspectionId = $payload['inspection_id'] ?? null;

            // Generate Settlement Code
            $code = 'SET-' . date('Ymd') . '-' . strtoupper(Str::random(5));

            $settlement = CheckOutSettlement::create([
                'settlement_code' => $code,
                'booking_id' => $lockedBooking->id,
                'deposit_ledger_id' => $ledger->id,
                'inspection_id' => $inspectionId,
                'property_id' => $lockedBooking->property_id,
                'tenant_id' => $lockedBooking->user_id,
                'check_out_date' => $checkOutDate,
                'status' => SettlementStatus::DRAFT,
                'deposit_balance_before' => $currentBalance,
                'total_deductions' => 0,
                'net_refund_amount' => $currentBalance,
                'refund_destination' => $payload['refund_destination'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Jika inspeksi dihubungkan, impor temuan kerusakan (kerusakan/barang hilang)
            $totalDeductions = 0.00;
            if ($inspectionId) {
                $inspection = Inspection::with('items')->find($inspectionId);
                if ($inspection) {
                    foreach ($inspection->items as $item) {
                        if ($item->repair_required || (float) $item->estimated_cost > 0) {
                            $cost = (float) $item->estimated_cost;
                            SettlementDeduction::create([
                                'settlement_id' => $settlement->id,
                                'inspection_item_id' => $item->id,
                                'title' => 'Kerusakan/Kehilangan: ' . $item->item_name,
                                'description' => $item->notes ?: 'Hasil inspeksi kamar keluar',
                                'proposed_amount' => $cost,
                                'approved_amount' => $cost,
                                'is_approved' => true,
                                'is_disputed' => false,
                            ]);
                            $totalDeductions += $cost;
                        }
                    }
                }
            }

            // Tambahkan potongan manual jika ada
            if (!empty($payload['custom_deductions']) && is_array($payload['custom_deductions'])) {
                foreach ($payload['custom_deductions'] as $custom) {
                    $cAmount = (float) ($custom['amount'] ?? 0);
                    if ($cAmount > 0) {
                        SettlementDeduction::create([
                            'settlement_id' => $settlement->id,
                            'title' => $custom['title'] ?? 'Potongan Tambahan',
                            'description' => $custom['description'] ?? null,
                            'proposed_amount' => $cAmount,
                            'approved_amount' => $cAmount,
                            'is_approved' => true,
                            'is_disputed' => false,
                        ]);
                        $totalDeductions += $cAmount;
                    }
                }
            }

            // Hitung net refund awal
            $netRefund = max(0, $currentBalance - $totalDeductions);
            $settlement->total_deductions = $totalDeductions;
            $settlement->net_refund_amount = $netRefund;

            // Jika ada potongan, mintakan persetujuan penyewa; jika 0, langsung disetujui
            if ($totalDeductions > 0 && $lockedBooking->user_id) {
                $settlement->status = SettlementStatus::PENDING_TENANT_APPROVAL;
            } else {
                $settlement->status = SettlementStatus::APPROVED;
                $settlement->tenant_approved_at = now();
            }

            $settlement->save();

            return $settlement->load(['deductions', 'depositLedger', 'booking', 'inspection']);
        });
    }

    /**
     * Keputusan penyewa: Menyetujui atau Mengajukan Keberatan (Dispute).
     */
    public function submitTenantDecision(
        CheckOutSettlement $settlement,
        User $tenant,
        bool $isApproved,
        ?string $objectionNotes = null,
        array $disputedDeductionIds = []
    ): CheckOutSettlement {
        return DB::transaction(function () use ($settlement, $tenant, $isApproved, $objectionNotes, $disputedDeductionIds) {
            $locked = CheckOutSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SettlementStatus::PENDING_TENANT_APPROVAL && $locked->status !== SettlementStatus::DISPUTED) {
                throw new DomainException("Settlement saat ini tidak sedang menunggu persetujuan penyewa.");
            }

            if ($isApproved) {
                $locked->status = SettlementStatus::APPROVED;
                $locked->tenant_approved_at = now();
                $locked->tenant_objection_notes = null;

                // Setujui semua potongan
                $locked->deductions()->update([
                    'is_approved' => true,
                    'is_disputed' => false,
                ]);
            } else {
                // Penyewa mengajukan sengketa (dispute)
                if (empty(trim((string)$objectionNotes))) {
                    throw ValidationException::withMessages([
                        'objection_notes' => 'Catatan keberatan wajib diisi saat menolak usulan denda/potongan.',
                    ]);
                }

                $locked->status = SettlementStatus::DISPUTED;
                $locked->tenant_objection_notes = trim($objectionNotes);

                // Tandai item potongan yang disengketakan
                if (!empty($disputedDeductionIds)) {
                    $locked->deductions()->whereIn('id', $disputedDeductionIds)->update([
                        'is_disputed' => true,
                        'is_approved' => false,
                        'dispute_reason' => $objectionNotes,
                    ]);
                } else {
                    $locked->deductions()->update([
                        'is_disputed' => true,
                        'is_approved' => false,
                        'dispute_reason' => $objectionNotes,
                    ]);
                }
            }

            $locked->save();

            return $locked->fresh(['deductions', 'depositLedger']);
        });
    }

    /**
     * Penyelesaian sengketa (resolve dispute) oleh manajer atau pemilik kos berwenang.
     */
    public function resolveSettlementDispute(
        CheckOutSettlement $settlement,
        User $resolver,
        array $approvedAmounts,
        string $resolutionNotes
    ): CheckOutSettlement {
        if (empty(trim($resolutionNotes))) {
            throw ValidationException::withMessages([
                'resolution_notes' => 'Catatan keputusan penyelesaian sengketa wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($settlement, $resolver, $approvedAmounts, $resolutionNotes) {
            $locked = CheckOutSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SettlementStatus::DISPUTED) {
                throw new DomainException("Hanya settlement berstatus sengketa (Disputed) yang dapat diselesaikan.");
            }

            $totalApprovedDeductions = 0.00;

            foreach ($locked->deductions as $deduction) {
                if (isset($approvedAmounts[$deduction->id])) {
                    $newAmount = max(0, (float) $approvedAmounts[$deduction->id]);
                    $deduction->approved_amount = $newAmount;
                    $deduction->is_approved = ($newAmount > 0);
                    $deduction->is_disputed = false;
                    $deduction->save();
                    $totalApprovedDeductions += $newAmount;
                } else {
                    $totalApprovedDeductions += (float) $deduction->approved_amount;
                }
            }

            $depositBal = (float) $locked->deposit_balance_before;
            $netRefund = max(0, $depositBal - $totalApprovedDeductions);

            $locked->total_deductions = $totalApprovedDeductions;
            $locked->net_refund_amount = $netRefund;
            $locked->status = SettlementStatus::APPROVED;
            $locked->resolved_by = $resolver->id;
            $locked->resolved_at = now();
            $locked->resolution_notes = $resolutionNotes;
            $locked->save();

            return $locked->fresh(['deductions', 'depositLedger', 'resolver']);
        });
    }

    /**
     * Finalisasi Check-Out & Pembayaran Pengembalian Deposit.
     * Menerapkan mutasi ke subledger deposit, update status booking COMPLETED, dan status kamar DIRTY.
     */
    public function finalizeCheckOutAndRefund(
        CheckOutSettlement $settlement,
        User $actor,
        array $refundData
    ): CheckOutSettlement {
        return DB::transaction(function () use ($settlement, $actor, $refundData) {
            $lockedSettlement = CheckOutSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();

            if ($lockedSettlement->status !== SettlementStatus::APPROVED) {
                throw new DomainException("Settlement harus berstatus Disetujui (Approved) sebelum finalisasi pengembalian dana. Status saat ini: {$lockedSettlement->status->label()}.");
            }

            $booking = Booking::where('id', $lockedSettlement->booking_id)->lockForUpdate()->firstOrFail();
            $room = Room::where('id', $booking->room_id)->lockForUpdate()->firstOrFail();
            $ledger = DepositLedger::where('id', $lockedSettlement->deposit_ledger_id)->lockForUpdate()->firstOrFail();

            // 1. Catat potongan denda yang telah disahkan ke subledger deposit
            foreach ($lockedSettlement->deductions as $deduction) {
                if ($deduction->is_approved && (float)$deduction->approved_amount > 0) {
                    $dAmount = (float) $deduction->approved_amount;
                    $balBefore = (float) $ledger->current_balance;

                    // Jumlah yang dipotong dari deposit dibatasi oleh saldo deposit yang masih tersedia
                    $deductedFromDeposit = min($dAmount, $balBefore);
                    $balAfter = $balBefore - $deductedFromDeposit;

                    if ($deductedFromDeposit > 0) {
                        $ledger->total_deductions = (float) $ledger->total_deductions + $deductedFromDeposit;
                        $ledger->current_balance = $balAfter;
                        $ledger->save();

                        DepositMutation::create([
                            'deposit_ledger_id' => $ledger->id,
                            'mutation_code' => 'MUT-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                            'transaction_type' => DepositMutationType::DAMAGE_DEDUCTION,
                            'direction' => 'out',
                            'amount' => $deductedFromDeposit,
                            'balance_before' => $balBefore,
                            'balance_after' => $balAfter,
                            'inspection_id' => $lockedSettlement->inspection_id,
                            'performed_by' => $actor->id,
                            'notes' => 'Potongan denda check-out: ' . $deduction->title . ($dAmount > $deductedFromDeposit ? " (sebagian: Rp " . number_format($deductedFromDeposit, 0, ',', '.') . " dari Rp " . number_format($dAmount, 0, ',', '.') . ")" : ''),
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            // 2. Eksekusi pengembalian sisa uang deposit (refund payout) jika net refund > 0
            $refundAmount = (float) $lockedSettlement->net_refund_amount;
            if ($refundAmount > 0) {
                $balBefore = (float) $ledger->current_balance;

                if ($refundAmount > $balBefore) {
                    throw new DomainException("Nominal refund (Rp {$refundAmount}) melebihi saldo deposit yang tersisa (Rp {$balBefore}).");
                }

                $balAfter = $balBefore - $refundAmount;
                $ledger->total_refunded = (float) $ledger->total_refunded + $refundAmount;
                $ledger->current_balance = $balAfter;
                $ledger->status = ($balAfter <= 0) ? DepositStatus::REFUNDED : DepositStatus::PARTIALLY_REFUNDED;
                $ledger->save();

                DepositMutation::create([
                    'deposit_ledger_id' => $ledger->id,
                    'mutation_code' => 'MUT-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                    'transaction_type' => DepositMutationType::REFUND_PAYOUT,
                    'direction' => 'out',
                    'amount' => $refundAmount,
                    'balance_before' => $balBefore,
                    'balance_after' => $balAfter,
                    'payment_method' => $refundData['refund_method'] ?? 'bank_transfer',
                    'payment_reference' => $refundData['refund_payment_reference'] ?? null,
                    'performed_by' => $actor->id,
                    'notes' => 'Pengembalian deposit check-out ke: ' . ($refundData['refund_destination'] ?? $lockedSettlement->refund_destination ?? '-'),
                    'created_at' => now(),
                ]);
            } else {
                // Jika deposit habis terpotong denda
                $ledger->status = DepositStatus::SETTLED;
                $ledger->save();
            }

            // 3. Update status Settlement menjadi SETTLED
            $lockedSettlement->status = SettlementStatus::SETTLED;
            $lockedSettlement->refund_method = $refundData['refund_method'] ?? 'bank_transfer';
            $lockedSettlement->refund_destination = $refundData['refund_destination'] ?? $lockedSettlement->refund_destination;
            $lockedSettlement->refund_payment_reference = $refundData['refund_payment_reference'] ?? null;
            $lockedSettlement->refund_receipt_proof = $refundData['refund_receipt_proof'] ?? null;
            $lockedSettlement->refunded_by = $actor->id;
            $lockedSettlement->refunded_at = now();
            $lockedSettlement->save();

            // 4. Update status Booking menjadi COMPLETED & catat actual_check_out
            $booking->status = BookingStatus::COMPLETED;
            $booking->actual_check_out = now();
            $booking->save();

            // 5. ATURAN OPERASIONAL KRITIS: Ubah status kamar menjadi DIRTY (Kotor/Perlu Housekeeping)
            // Jangan langsung ubah menjadi AVAILABLE agar kamar dibersihkan dan dicek terlebih dahulu!
            $room->status = RoomStatus::DIRTY;
            $room->save();

            // Phase 8: Otomatis posting jurnal pemotongan deposit & pengembalian dana check-out
            $journalService = app(\App\Services\Accounting\JournalPostingService::class);
            $journalService->postCheckOutSettlementJournals($lockedSettlement, $actor);

            return $lockedSettlement->fresh(['deductions', 'depositLedger.mutations', 'booking', 'refunder']);
        });
    }

    /**
     * Sinkronisasi Uang Jaminan dari pembayaran terverifikasi secara idempoten.
     */
    public function handleVerifiedDepositPayment(\App\Models\Payment $payment, User $actor): ?DepositMutation
    {
        $invoice = $payment->invoice;
        if (!$invoice || !$invoice->booking_id) {
            return null;
        }

        $booking = $invoice->booking;
        if (!$booking || (float)$booking->deposit_amount <= 0) {
            return null;
        }

        $ledger = $this->getOrCreateLedgerForBooking($booking);

        $alreadyCredited = DepositMutation::where('deposit_ledger_id', $ledger->id)
            ->where('payment_id', $payment->id)
            ->exists();

        if ($alreadyCredited) {
            return null;
        }

        $needed = (float)$booking->deposit_amount - (float)$ledger->total_received;
        $depositCredit = min((float)$payment->amount, max(0, $needed));

        if ($depositCredit <= 0) {
            return null;
        }

        return $this->recordDepositReceived($ledger, $depositCredit, $actor, [
            'payment_method' => $payment->payment_method ?? 'bank_transfer',
            'payment_reference' => $payment->payment_code,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'notes' => 'Penerimaan deposit dari verifikasi pembayaran ' . $payment->payment_code,
        ]);
    }
}
