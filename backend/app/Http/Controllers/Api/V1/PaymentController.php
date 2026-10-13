<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentController extends ApiController
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payment::with(['invoice.property', 'user', 'verifier'])->latest();

        if ($user->isTenant()) {
            $query->where('user_id', $user->id);
        } elseif (!$user->isSuperAdmin()) {
            $propertyIds = DB::table('property_users')->where('user_id', $user->id)->pluck('property_id');
            $query->whereHas('invoice', function ($inv) use ($propertyIds) {
                $inv->whereIn('property_id', $propertyIds)
                    ->orWhereHas('booking', fn ($b) => $b->whereIn('property_id', $propertyIds));
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $payments = $query->paginate(15);

        return $this->successResponse($payments, 'Daftar transaksi pembayaran berhasil diambil.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $payment = Payment::with(['invoice.property', 'user', 'verifier'])->find($id);

        if (!$payment) {
            return $this->errorResponse('Data pembayaran tidak ditemukan.', 404);
        }

        if (!$this->paymentService->canAccessPaymentProof($payment, $user)) {
            return $this->errorResponse('Anda tidak memiliki izin mengakses data pembayaran ini.', 403);
        }

        return $this->successResponse($payment, 'Detail pembayaran berhasil diambil.');
    }

    public function getProof(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();

        $payment = Payment::with('invoice')->find($id);

        if (!$payment || !$payment->proof_photo) {
            return $this->errorResponse('Bukti transfer tidak ditemukan.', 404);
        }

        if (!$this->paymentService->canAccessPaymentProof($payment, $user)) {
            return $this->errorResponse('Akses ke bukti pembayaran ini ditolak.', 403);
        }

        if (!Storage::disk('local')->exists($payment->proof_photo)) {
            return $this->errorResponse('Berkas fisik bukti transfer tidak ditemukan di server.', 404);
        }

        $fullPath = Storage::disk('local')->path($payment->proof_photo);
        $mimeType = Storage::disk('local')->mimeType($payment->proof_photo) ?? 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $verifier = $request->user();

        // Hanya staf/manager/owner/super_admin yang berhak memverifikasi
        if ($verifier->isTenant()) {
            return $this->errorResponse('Penyewa tidak diizinkan memverifikasi pembayaran.', 403);
        }

        $payment = Payment::with('invoice')->find($id);
        if (!$payment) {
            return $this->errorResponse('Data pembayaran tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $verifiedPayment = $this->paymentService->verifyPayment($payment, $verifier, $validated['notes'] ?? null);

            return $this->successResponse($verifiedPayment, 'Pembayaran berhasil diverifikasi. Saldo tagihan dan status sewa telah diperbarui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $verifier = $request->user();

        if ($verifier->isTenant()) {
            return $this->errorResponse('Penyewa tidak diizinkan memproses penolakan pembayaran.', 403);
        }

        $payment = Payment::with('invoice')->find($id);
        if (!$payment) {
            return $this->errorResponse('Data pembayaran tidak ditemukan.', 404);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $rejectedPayment = $this->paymentService->rejectPayment(
                $payment,
                $verifier,
                $validated['rejection_reason'],
                $validated['notes'] ?? null
            );

            return $this->successResponse($rejectedPayment, 'Pengajuan pembayaran telah ditolak dengan catatan alasan.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validasi penolakan gagal.', 422, $e->errors());
        }
    }
}
