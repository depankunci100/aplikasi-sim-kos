<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Identity\IdentityVerificationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentityVerificationController extends ApiController
{
    public function __construct(
        protected IdentityVerificationService $identityService
    ) {}

    /**
     * GET /api/v1/tenant/identity
     * Cek status identitas dan verifikasi NIK penyewa
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $identity = $this->identityService->getTenantIdentity($user);

        return $this->successResponse($identity, 'Data identitas penyewa berhasil diambil.');
    }

    /**
     * POST /api/v1/tenant/identity/verify
     * Verifikasi dan simpan NIK KTP penyewa (dengan validasi 16 digit dan pencegahan duplikasi global)
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'nik' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'ktp_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'emergency_name' => ['nullable', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'institution_name' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['nullable', 'in:motor,mobil,none'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
        ], [
            'nik.regex' => 'Format NIK harus tepat 16 digit angka sesuai KTP resmi.',
        ]);

        try {
            $profile = $this->identityService->verifyAndSaveTenantIdentity(
                $user,
                $request->nik,
                $request->file('ktp_photo'),
                $request->only([
                    'emergency_name',
                    'emergency_phone',
                    'occupation',
                    'institution_name',
                    'vehicle_type',
                    'vehicle_plate',
                ])
            );

            return $this->successResponse([
                'user_id' => $profile->user_id,
                'masked_nik' => $profile->masked_nik,
                'is_nik_verified' => $profile->is_nik_verified,
                'nik_verified_at' => $profile->nik_verified_at?->toIso8601String(),
            ], 'Identitas NIK KTP penyewa berhasil diverifikasi dan disimpan.', 200);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * PUT /api/v1/tenant/identity
     * Edit identitas dan profil penyewa (nama, NIK bila diubah, data pelengkap)
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'regex:/^[0-9]{16}$/'],
            'ktp_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'emergency_name' => ['nullable', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'institution_name' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['nullable', 'in:motor,mobil,none'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
        ], [
            'nik.regex' => 'Format NIK harus tepat 16 digit angka sesuai KTP resmi.',
        ]);

        try {
            $profile = $this->identityService->updateTenantProfile(
                $user,
                $request->only([
                    'name',
                    'nik',
                    'emergency_name',
                    'emergency_phone',
                    'occupation',
                    'institution_name',
                    'vehicle_type',
                    'vehicle_plate',
                ]),
                $request->file('ktp_photo')
            );

            $identity = $this->identityService->getTenantIdentity($user);

            return $this->successResponse($identity, 'Profil identitas penyewa berhasil diperbarui.');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
