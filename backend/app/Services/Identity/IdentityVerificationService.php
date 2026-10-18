<?php

namespace App\Services\Identity;

use App\Models\TenantProfile;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IdentityVerificationService
{
    /**
     * Hitung HMAC ber-key untuk blind-index NIK yang aman
     */
    public function computeNikHash(string $nik): string
    {
        $key = config('app.key') ?: 'simkos-identity-secret-fallback-key';
        return hash_hmac('sha256', trim($nik), $key);
    }

    /**
     * Validasi format NIK Indonesia (16 Digit Angka)
     */
    public function validateNikFormat(string $nik): bool
    {
        return preg_match('/^[0-9]{16}$/', trim($nik)) === 1;
    }

    /**
     * Samarkan NIK untuk logging atau tampilan publik (misal: 3507********1234)
     */
    public function maskNik(string $nik): string
    {
        $clean = trim($nik);
        $len = strlen($clean);
        if ($len < 8) {
            return str_repeat('*', $len);
        }
        return substr($clean, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($clean, -4);
    }

    /**
     * Verifikasi dan Simpan NIK KTP Penyewa dengan Race-Condition Lock
     */
    public function verifyAndSaveTenantIdentity(
        User $user,
        string $nik,
        ?UploadedFile $ktpPhoto = null,
        array $additionalData = []
    ): TenantProfile {
        $cleanNik = trim($nik);

        if (!$this->validateNikFormat($cleanNik)) {
            throw new DomainException('Format NIK tidak valid. NIK KTP harus terdiri dari tepat 16 digit angka.');
        }

        $nikHash = $this->computeNikHash($cleanNik);

        return DB::transaction(function () use ($user, $cleanNik, $nikHash, $ktpPhoto, $additionalData) {
            // Cek apakah NIK sudah digunakan oleh akun lain via blind index (dengan row locking)
            $existing = TenantProfile::where('nik_hash', $nikHash)
                ->orWhere('ktp_number', $cleanNik)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->user_id !== $user->id) {
                // Catat log keamanan tanpa mencatat NIK utuh
                Log::warning('Upaya pendaftaran NIK terduplikasi terdeteksi.', [
                    'attempted_by_user_id' => $user->id,
                    'masked_nik' => $this->maskNik($cleanNik),
                    'existing_user_id' => $existing->user_id,
                ]);

                throw new DomainException('NIK KTP ini sudah terdaftar pada akun penyewa lain di sistem.');
            }

            // Simpan foto KTP di private storage disk (bukan public)
            $photoPath = null;
            if ($ktpPhoto && $ktpPhoto->isValid()) {
                $photoPath = $ktpPhoto->store("ktp/{$user->id}", 'local');
            }

            $profile = TenantProfile::firstOrNew(['user_id' => $user->id]);

            $profile->ktp_number = $cleanNik;
            $profile->nik_hash = $nikHash;
            $profile->is_nik_verified = true;
            $profile->nik_verified_at = now();

            if ($photoPath) {
                // Hapus foto lama jika ada
                if ($profile->ktp_photo && Storage::disk('local')->exists($profile->ktp_photo)) {
                    Storage::disk('local')->delete($profile->ktp_photo);
                }
                $profile->ktp_photo = $photoPath;
            }

            if (!empty($additionalData['emergency_name'])) {
                $profile->emergency_name = trim($additionalData['emergency_name']);
            }
            if (!empty($additionalData['emergency_phone'])) {
                $profile->emergency_phone = trim($additionalData['emergency_phone']);
            }
            if (!empty($additionalData['occupation'])) {
                $profile->occupation = trim($additionalData['occupation']);
            }
            if (!empty($additionalData['institution_name'])) {
                $profile->institution_name = trim($additionalData['institution_name']);
            }
            if (!empty($additionalData['vehicle_type'])) {
                $profile->vehicle_type = trim($additionalData['vehicle_type']);
            }
            if (!empty($additionalData['vehicle_plate'])) {
                $profile->vehicle_plate = trim($additionalData['vehicle_plate']);
            }

            $profile->save();

            Log::info('Identitas NIK KTP penyewa berhasil diverifikasi dan disimpan.', [
                'user_id' => $user->id,
                'masked_nik' => $this->maskNik($cleanNik),
            ]);

            return $profile;
        });
    }

    /**
     * Update data profil identitas penyewa (membedakan perubahan data biasa dan perubahan NIK)
     */
    public function updateTenantProfile(
        User $user,
        array $data,
        ?UploadedFile $ktpPhoto = null
    ): TenantProfile {
        return DB::transaction(function () use ($user, $data, $ktpPhoto) {
            $profile = TenantProfile::firstOrNew(['user_id' => $user->id]);

            // Jika ada perubahan nama pengguna
            if (!empty($data['name']) && trim($data['name']) !== $user->name) {
                $user->name = trim($data['name']);
                $user->save();
            }

            // Jika menyertakan NIK baru untuk diverifikasi atau diubah
            if (!empty($data['nik'])) {
                $cleanNik = trim($data['nik']);
                if (!$this->validateNikFormat($cleanNik)) {
                    throw new DomainException('Format NIK tidak valid. NIK KTP harus terdiri dari tepat 16 digit angka.');
                }

                $nikHash = $this->computeNikHash($cleanNik);

                // Cek duplikasi akun lain via blind index
                $existing = TenantProfile::where('nik_hash', $nikHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->user_id !== $user->id) {
                    Log::warning('Upaya perubahan NIK terduplikasi terdeteksi.', [
                        'actor_id' => $user->id,
                        'masked_nik' => $this->maskNik($cleanNik),
                        'existing_user_id' => $existing->user_id,
                    ]);
                    throw new DomainException('NIK KTP ini sudah terdaftar pada akun penyewa lain di sistem.');
                }

                $profile->ktp_number = $cleanNik;
                $profile->nik_hash = $nikHash;
                $profile->is_nik_verified = true;
                $profile->nik_verified_at = now();
            }

            // Simpan foto KTP di private storage disk jika diunggah
            if ($ktpPhoto && $ktpPhoto->isValid()) {
                if ($profile->ktp_photo && Storage::disk('local')->exists($profile->ktp_photo)) {
                    Storage::disk('local')->delete($profile->ktp_photo);
                }
                $profile->ktp_photo = $ktpPhoto->store("ktp/{$user->id}", 'local');
            }

            // Pembaruan data biasa
            if (array_key_exists('emergency_name', $data)) {
                $profile->emergency_name = trim($data['emergency_name'] ?? '');
            }
            if (array_key_exists('emergency_phone', $data)) {
                $profile->emergency_phone = trim($data['emergency_phone'] ?? '');
            }
            if (array_key_exists('occupation', $data)) {
                $profile->occupation = trim($data['occupation'] ?? '');
            }
            if (array_key_exists('institution_name', $data)) {
                $profile->institution_name = trim($data['institution_name'] ?? '');
            }
            if (array_key_exists('vehicle_type', $data)) {
                $profile->vehicle_type = trim($data['vehicle_type'] ?? 'none');
            }
            if (array_key_exists('vehicle_plate', $data)) {
                $profile->vehicle_plate = trim($data['vehicle_plate'] ?? '');
            }

            $profile->save();

            Log::info('Profil identitas penyewa berhasil diperbarui.', [
                'actor_id' => $user->id,
                'has_nik_change' => !empty($data['nik']),
                'masked_nik' => !empty($data['nik']) ? $this->maskNik($data['nik']) : $profile->masked_nik,
                'updated_at' => now()->toIso8601String(),
            ]);

            return $profile;
        });
    }

    /**
     * Ambil data profil identitas penyewa
     */
    public function getTenantIdentity(User $user): ?array
    {
        $profile = TenantProfile::where('user_id', $user->id)->first();
        if (!$profile) {
            return [
                'id' => null,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'masked_nik' => null,
                'has_ktp_photo' => false,
                'is_nik_verified' => false,
                'verification_status' => 'unverified',
                'nik_verified_at' => null,
                'updated_at' => null,
                'emergency_name' => null,
                'emergency_phone' => null,
                'occupation' => null,
                'institution_name' => null,
                'vehicle_type' => 'none',
                'vehicle_plate' => null,
            ];
        }

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'masked_nik' => $profile->masked_nik,
            'has_ktp_photo' => !empty($profile->ktp_photo),
            'is_nik_verified' => (bool) $profile->is_nik_verified,
            'verification_status' => $profile->is_nik_verified ? 'verified' : 'unverified',
            'nik_verified_at' => $profile->nik_verified_at?->toIso8601String(),
            'updated_at' => $profile->updated_at?->toIso8601String(),
            'emergency_name' => $profile->emergency_name,
            'emergency_phone' => $profile->emergency_phone,
            'occupation' => $profile->occupation,
            'institution_name' => $profile->institution_name,
            'vehicle_type' => $profile->vehicle_type ?? 'none',
            'vehicle_plate' => $profile->vehicle_plate,
        ];
    }
}
