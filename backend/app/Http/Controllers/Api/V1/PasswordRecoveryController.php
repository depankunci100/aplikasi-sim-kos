<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Auth\PasswordRecoveryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordRecoveryController extends ApiController
{
    public function __construct(
        protected PasswordRecoveryService $recoveryService
    ) {}

    /**
     * POST /api/v1/auth/tenant/password-reset/initiate
     * Diinisiasi oleh petugas/manajer/owner untuk akun penyewa
     */
    public function initiate(Request $request): JsonResponse
    {
        $actor = $request->user();

        $request->validate([
            'tenant_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $result = $this->recoveryService->initiateTenantPasswordReset(
                (int) $request->tenant_id,
                $actor,
                $request
            );

            return $this->successResponse($result, 'Inisiasi reset password penyewa berhasil diproses.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/auth/password-reset/complete
     * Selesaikan penggantian password baru dengan token sekali pakai
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.min' => 'Kata sandi baru minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak sesuai.',
        ]);

        try {
            $this->recoveryService->completePasswordReset(
                $request->email,
                $request->token,
                $request->password
            );

            return $this->successResponse(null, 'Kata sandi berhasil diperbarui. Silakan login kembali dengan password baru Anda.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
