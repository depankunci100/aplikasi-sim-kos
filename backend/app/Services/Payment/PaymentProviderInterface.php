<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

interface PaymentProviderInterface
{
    /**
     * Nama unik identifier provider (e.g. manual_transfer, manual_bca, manual_qris, midtrans, xendit)
     */
    public function getProviderName(): string;

    /**
     * Apakah provider membutuhkan upload bukti transfer manual oleh penyewa
     */
    public function supportsManualProof(): bool;

    /**
     * Memproses inisiasi pembayaran untuk suatu invoice
     */
    public function processPayment(Invoice $invoice, User $user, float $amount, array $payload): Payment;
}
