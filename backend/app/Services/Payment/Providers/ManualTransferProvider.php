<?php

namespace App\Services\Payment\Providers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentProviderInterface;
use Illuminate\Support\Str;

class ManualTransferProvider implements PaymentProviderInterface
{
    protected string $providerName;

    public function __construct(string $providerName = 'manual_transfer')
    {
        $this->providerName = $providerName;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function supportsManualProof(): bool
    {
        return true;
    }

    public function processPayment(Invoice $invoice, User $user, float $amount, array $payload): Payment
    {
        return Payment::create([
            'payment_code' => 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'payment_method' => $payload['payment_method'] ?? 'manual_transfer',
            'payment_provider' => $this->providerName,
            'amount' => $amount,
            'sender_name' => $payload['sender_name'] ?? null,
            'sender_bank' => $payload['sender_bank'] ?? null,
            'proof_photo' => $payload['proof_photo'] ?? null,
            'status' => 'pending',
        ]);
    }
}
