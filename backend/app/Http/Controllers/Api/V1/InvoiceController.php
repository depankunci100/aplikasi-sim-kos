<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Invoice;
use App\Services\Payment\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends ApiController
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Invoice::with(['property', 'booking.room.roomType', 'payments'])
            ->latest();

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            // Owner dan Staf hanya melihat invoice pada properti yang ditugaskan
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->where(function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds)
                  ->orWhereHas('booking', fn ($b) => $b->whereIn('property_id', $propertyIds));
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $invoices = $query->paginate(15);

        // Tambahkan remaining_balance pada setiap record
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->remaining_balance = $invoice->remaining_balance;
            return $invoice;
        });

        return $this->successResponse($invoices, 'Daftar tagihan berhasil diambil.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $query = Invoice::with(['property', 'booking.room.roomType', 'payments.verifier']);

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->where(function ($q) use ($propertyIds) {
                $q->whereIn('property_id', $propertyIds)
                  ->orWhereHas('booking', fn ($b) => $b->whereIn('property_id', $propertyIds));
            });
        }

        $invoice = $query->find($id);

        if (!$invoice) {
            return $this->errorResponse('Tagihan tidak ditemukan atau Anda tidak memiliki akses.', 404);
        }

        $invoice->remaining_balance = $invoice->remaining_balance;

        return $this->successResponse($invoice, 'Detail tagihan berhasil diambil.');
    }

    public function submitPayment(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $invoice = Invoice::find($id);
        if (!$invoice) {
            return $this->errorResponse('Tagihan tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'string', 'in:manual_transfer,qris,cash'],
            'payment_provider' => ['nullable', 'string', 'max:50'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_bank' => ['nullable', 'string', 'max:100'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'], // Max 5MB
        ]);

        try {
            $payment = $this->paymentService->submitManualPayment(
                $invoice,
                $user,
                (float) $validated['amount'],
                $validated,
                $request->file('proof_file')
            );

            return $this->successResponse(
                $payment->load('invoice'),
                'Bukti pembayaran berhasil diajukan dan sedang menunggu verifikasi kasir.',
                201
            );
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi pengajuan pembayaran gagal.', 422, $e->errors());
        }
    }
}
