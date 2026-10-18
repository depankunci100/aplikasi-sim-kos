<?php

namespace App\Services\Auth;

use App\Models\PasswordResetLog;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordRecoveryService
{
    /**
     * Waktu kedaluwarsa token reset password (dalam menit)
     */
    public const TOKEN_EXPIRY_MINUTES = 60;

    /**
     * Batas maksimal inisiasi per jam untuk satu akun
     */
    public const MAX_ATTEMPTS_PER_HOUR = 3;

    /**
     * Petugas (Super Admin, Owner, Manager) menginisiasi pemulihan akun penyewa
     */
    public function initiateTenantPasswordReset(int $tenantId, User $actor, ?Request $request = null): array
    {
        if (!$actor->hasRole('super_admin', 'owner', 'manager')) {
            throw new DomainException('Hanya pengelola atau pemilik yang berwenang menginisiasi reset password penyewa.');
        }

        $tenant = User::where('role', 'tenant')->findOrFail($tenantId);

        // Batasi frekuensi inisiasi reset (rate limiting)
        $recentAttempts = PasswordResetLog::where('user_id', $tenant->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentAttempts >= self::MAX_ATTEMPTS_PER_HOUR) {
            throw new DomainException('Batas frekuensi inisiasi reset tercapai (maksimal 3 kali per jam). Harap tunggu beberapa saat.');
        }

        // Buat token acak berkekuatan kriptografis
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        $ipAddress = $request ? $request->ip() : null;

        DB::transaction(function () use ($tenant, $hashedToken, $actor, $ipAddress) {
            // Hapus token lama untuk email ini
            DB::table('password_reset_tokens')->where('email', $tenant->email)->delete();

            // Simpan token yang di-hash (tidak menyimpan token mentah di database)
            DB::table('password_reset_tokens')->insert([
                'email' => $tenant->email,
                'token' => $hashedToken,
                'created_at' => now(),
            ]);

            // Cabut seluruh token Sanctum aktif milik tenant demi keamanan
            $tenant->tokens()->delete();

            // Catat log audit
            PasswordResetLog::create([
                'user_id' => $tenant->id,
                'initiated_by' => $actor->id,
                'ip_address' => $ipAddress,
                'status' => 'initiated',
            ]);
        });

        Log::info('Inisiasi reset password penyewa berhasil dicatat.', [
            'tenant_id' => $tenant->id,
            'initiated_by' => $actor->id,
        ]);

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_email' => $tenant->email,
            'reset_token' => $plainToken, // Diberikan sekali saja ke pemanggil untuk tautan pemulihan
            'expires_at' => now()->addMinutes(self::TOKEN_EXPIRY_MINUTES)->toIso8601String(),
        ];
    }

    /**
     * Selesaikan proses pemulihan kata sandi dengan token yang valid
     */
    public function completePasswordReset(string $email, string $plainToken, string $newPassword): bool
    {
        $cleanEmail = trim($email);
        $hashedToken = hash('sha256', trim($plainToken));

        $record = DB::table('password_reset_tokens')->where('email', $cleanEmail)->first();

        if (!$record) {
            throw new DomainException('Tautan reset password tidak valid atau telah digunakan.');
        }

        // Cek kecocokan token
        if (!hash_equals($record->token, $hashedToken)) {
            throw new DomainException('Token verifikasi tidak sesuai.');
        }

        // Cek kedaluwarsa token
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(self::TOKEN_EXPIRY_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $cleanEmail)->delete();
            throw new DomainException('Token reset password telah kedaluwarsa. Silakan ajukan inisiasi reset ulang.');
        }

        $user = User::where('email', $cleanEmail)->firstOrFail();

        DB::transaction(function () use ($user, $newPassword, $cleanEmail) {
            $user->password = Hash::make($newPassword);
            $user->save();

            // Hapus token reset setelah digunakan sekali
            DB::table('password_reset_tokens')->where('email', $cleanEmail)->delete();

            // Cabut semua sesi Sanctum lama
            $user->tokens()->delete();

            // Perbarui log audit
            PasswordResetLog::where('user_id', $user->id)
                ->where('status', 'initiated')
                ->latest('id')
                ->first()
                ?->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
        });

        Log::info('Password akun pengguna berhasil diperbarui melalui mekanisme reset token.', [
            'user_id' => $user->id,
        ]);

        return true;
    }
}
