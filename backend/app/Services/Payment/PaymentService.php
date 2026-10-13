<?php

namespace App\Services\Payment;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\Providers\ManualTransferProvider;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Mengajukan pembayaran transfer manual dengan upload bukti transfer
     */
    public function submitManualPayment(
        Invoice $invoice,
        User $user,
        float $amount,
        array $payload,
        ?UploadedFile $proofFile = null
    ): Payment {
        // 1. Otorisasi kepemilikan invoice
        if (!$user->isSuperAdmin() && $invoice->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'invoice' => ['Anda tidak memiliki akses ke tagihan ini.'],
            ]);
        }

        // 2. Validasi status invoice
        if ($invoice->isPaid()) {
            throw new DomainException('Tagihan ini sudah lunas.');
        }

        if ($invoice->status === InvoiceStatus::CANCELLED) {
            throw new DomainException('Tagihan ini telah dibatalkan.');
        }

        // 3. Kebijakan Anti-Overpayment (Mencegah kelebihan bayar)
        $remaining = $invoice->remaining_balance;
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Nominal pembayaran harus lebih besar dari 0.'],
            ]);
        }

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => [
                    sprintf('Nominal pembayaran (Rp %s) melebihi sisa tagihan (Rp %s).',
                        number_format($amount, 0, ',', '.'),
                        number_format($remaining, 0, ',', '.')
                    ),
                ],
            ]);
        }

        // 4. Simpan bukti transfer pada storage privat (aman dari akses publik tak terotorisasi)
        $proofPath = null;
        if ($proofFile) {
            // Disimpan di direktori storage privat 'payment_proofs'
            $proofPath = $proofFile->store('payment_proofs', 'local');
        }

        $providerName = $payload['payment_provider'] ?? 'manual_transfer';
        $provider = new Providers\ManualTransferProvider($providerName);

        $paymentPayload = array_merge($payload, [
            'proof_photo' => $proofPath,
        ]);

        return $provider->processPayment($invoice, $user, $amount, $paymentPayload);
    }

    /**
     * Memverifikasi (menyetujui) pembayaran dengan kunci pesimistik dan transaksi atomik
     */
    public function verifyPayment(Payment $payment, User $verifier, ?string $notes = null): Payment
    {
        // 1. Penyewa dilarang memverifikasi pembayarannya sendiri
        if ($payment->user_id === $verifier->id) {
            throw new DomainException('Penyewa tidak diizinkan memverifikasi pembayarannya sendiri.');
        }

        return DB::transaction(function () use ($payment, $verifier, $notes) {
            // Kunci baris payment secara pesimistik agar request ganda/klik bersamaan tidak mengeksekusi dua kali
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::where('id', $lockedPayment->invoice_id)->lockForUpdate()->firstOrFail();

            if (!$lockedPayment->isPending()) {
                throw new DomainException(
                    "Pembayaran {$lockedPayment->payment_code} sudah berstatus '{$lockedPayment->status}' dan tidak dapat diproses lagi."
                );
            }

            // Otorisasi hak akses properti petugas verifikator
            $this->validateVerifierAuthorization($invoice, $verifier);

            // Update status pembayaran
            $lockedPayment->status = 'verified';
            $lockedPayment->verified_by = $verifier->id;
            $lockedPayment->verified_at = now();
            $lockedPayment->verification_notes = $notes;
            $lockedPayment->rejection_reason = null;
            $lockedPayment->save();

            // Rekalkulasi total bayar terverifikasi & status invoice
            $invoice->syncPaidAmountAndStatus();

            // Rental Lifecycle: Jika invoice lunas ATAU pembayaran terverifikasi mencukupi DP (Deposit),
            // perbarui status booking dari PENDING ke CONFIRMED
            if ($invoice->booking_id) {
                $booking = Booking::where('id', $invoice->booking_id)->lockForUpdate()->first();
                if ($booking && $booking->status === BookingStatus::PENDING) {
                    $minPayment = $booking->deposit_amount > 0 ? $booking->deposit_amount : ($booking->total_amount * 0.3);
                    if ($invoice->isPaid() || (float)$invoice->paid_amount >= (float)$minPayment) {
                        $booking->status = BookingStatus::CONFIRMED;
                        $booking->save();
                    }
                }
            }

            return $lockedPayment->load(['invoice', 'user', 'verifier']);
        });
    }

    /**
     * Menolak pembayaran dengan alasan wajib
     */
    public function rejectPayment(Payment $payment, User $verifier, string $rejectionReason, ?string $notes = null): Payment
    {
        if (empty(trim($rejectionReason))) {
            throw ValidationException::withMessages([
                'rejection_reason' => ['Alasan penolakan pembayaran wajib diisi.'],
            ]);
        }

        return DB::transaction(function () use ($payment, $verifier, $rejectionReason, $notes) {
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::where('id', $lockedPayment->invoice_id)->lockForUpdate()->firstOrFail();

            if (!$lockedPayment->isPending()) {
                throw new DomainException(
                    "Pembayaran {$lockedPayment->payment_code} sudah berstatus '{$lockedPayment->status}' dan tidak dapat diproses lagi."
                );
            }

            $this->validateVerifierAuthorization($invoice, $verifier);

            $lockedPayment->status = 'rejected';
            $lockedPayment->verified_by = $verifier->id;
            $lockedPayment->verified_at = now();
            $lockedPayment->rejection_reason = trim($rejectionReason);
            $lockedPayment->verification_notes = $notes;
            $lockedPayment->save();

            // Saldo invoice TIDAK berubah karena pembayaran ditolak
            return $lockedPayment->load(['invoice', 'user', 'verifier']);
        });
    }

    /**
     * Validasi apakah petugas memiliki izin pada properti terkait invoice
     */
    protected function validateVerifierAuthorization(Invoice $invoice, User $verifier): void
    {
        if ($verifier->isSuperAdmin()) {
            return;
        }

        $propertyId = $invoice->property_id ?? $invoice->booking?->property_id;
        if (!$propertyId) {
            return;
        }

        $isAssigned = DB::table('property_users')
            ->where('property_id', $propertyId)
            ->where('user_id', $verifier->id)
            ->exists();

        if (!$isAssigned) {
            throw new DomainException('Anda tidak memiliki kewenangan verifikasi pada properti cabang ini.');
        }
    }

    /**
     * Memeriksa otorisasi akses bukti transfer privat
     */
    public function canAccessPaymentProof(Payment $payment, User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($payment->user_id === $user->id) {
            return true;
        }

        $propertyId = $payment->invoice?->property_id ?? $payment->invoice?->booking?->property_id;
        if ($propertyId) {
            return DB::table('property_users')
                ->where('property_id', $propertyId)
                ->where('user_id', $user->id)
                ->exists();
        }

        return false;
    }
}
