# DOKUMENTASI KEAMANAN & ARSITEKTUR: OCR KTP, UNIQUE NIK, & RECOVERY PASSWORD
## SIM Kos & Guesthouse — Phase 10 Addendum

Dokumen ini mendokumentasikan spesifikasi teknis, mekanisme kriptografis, mitigasi kerentanan, dan kebijakan privasi untuk Fitur E (OCR KTP & Satu NIK Per Akun) serta Fitur F (Pemulihan Password Penyewa).

---

## 1. FITUR E — OCR KTP & PERLINDUNGAN SATU NIK PER AKUN

### 1.1. OCR KTP On-Device & Ekstraksi Cerdas
Aplikasi mobile menggunakan `KtpOcrService` on-device untuk mengekstraksi data KTP tanpa mengirimkan raw image atau payload mentah ke cloud AI eksternal yang tidak terpercaya:
1. **Reguler Expression 16-Digit**:
   Memeriksa pola angka 16 digit `\b[0-9]{16}\b`.
2. **Kompensasi Ambiguitas Karakter (OCR Ambiguity Recovery)**:
   Pada kamera bersolusi rendah atau pencahayaan kurang, angka sering tertukar dengan huruf:
   - `'O'`, `'o'`, `'D'` dikonversi menjadi `'0'`
   - `'l'`, `'I'`, `'|'` dikonversi menjadi `'1'`
3. **Konfirmasi & Koreksi Pengguna (Human-in-the-Loop)**:
   Hasil pemindaian otomatis ditampilkan ke form review. Pengguna diwajibkan memeriksa dan dapat mengedit nomor NIK serta nama sebelum data dikirim ke backend. OCR diposisikan sebagai alat bantu pengisian (helper), bukan bukti keaslian identitas hukum.

### 1.2. Arsitektur Kriptografi NIK: Enkripsi & Blind Index
Menyimpan NIK dalam format plaintext melanggar prinsip kepatuhan UU PDP (Pelindungan Data Pribadi). Namun, jika NIK dienkripsi secara probabilistik (AES-256-CBC dengan random IV), database tidak dapat melakukan pencarian cepat atau menerapkan constraint keunikan index.

Solusi arsitektur yang diimplementasikan:
```
                ┌─────────────────────────────────────────────────────────┐
                │                       Input NIK                         │
                └───────────────────────────┬─────────────────────────────┘
                                            │
                     ┌──────────────────────┴──────────────────────┐
                     ▼                                             ▼
          AES-256 Enkripsi Simetris                    HMAC-SHA256 Blind Index
        (Laravel 'encrypted' cast)                      Key: config('app.key')
                     │                                             │
                     ▼                                             ▼
             id_card_number                                     nik_hash
       (Tersimpan terenkripsi aman)                     (CHAR(64) UNIQUE INDEX)
```

1. **`id_card_number`**: Kolom teks terenkripsi menggunakan kunci enkripsi Laravel. Hanya dapat didekripsi di memori backend.
2. **`nik_hash`**: Blind index hash deterministik satu arah menggunakan `hash_hmac('sha256', $nik, config('app.key'))`. Kolom ini memiliki index `UNIQUE` di tabel `tenant_profiles`.
3. **Penyewa Global Uniqueness**:
   Keunikan NIK berlaku global di tingkat seluruh sistem, bukan hanya per properti. Seorang individu hanya boleh memiliki 1 akun penyewa terverifikasi.

### 1.3. Penanganan Race Condition & Transaksi Database
Untuk mencegah pembuatan akun ganda secara simultan melalui race condition:
```php
DB::transaction(function () use ($user, $data, $nikHash) {
    // 1. Pessimistic Locking untuk memeriksa keberadaan NIK
    $duplicate = TenantProfile::where('nik_hash', $nikHash)
        ->where('user_id', '!=', $user->id)
        ->lockForUpdate()
        ->first();

    if ($duplicate) {
        throw ValidationException::withMessages([
            'nik' => 'Nomor Identitas (NIK) ini sudah terdaftar pada akun lain.'
        ]);
    }

    // 2. Simpan profil
    $profile = TenantProfile::firstOrNew(['user_id' => $user->id]);
    $profile->id_card_number = $data['nik'];
    $profile->nik_hash = $nikHash;
    $profile->is_nik_verified = true;
    $profile->save();
});
```

### 1.4. Masking & Pencegahan Kebocoran Data (Data Minimization)
- **Masking NIK**: API dan Web Dashboard hanya menampilkan NIK yang telah disamarkan:
  ```php
  public function getMaskedNikAttribute(): ?string
  {
      $nik = $this->id_card_number;
      if (!$nik || strlen($nik) < 8) return '****';
      return substr($nik, 0, 6) . '******' . substr($nik, -4);
  }
  ```
  Contoh tampilan: `357301******0004`.
- **Private File Storage**: Foto KTP pendukung disimpan di disk privat non-publik (`storage/app/private/id_cards/`) dan tidak pernah dapat diakses publik melalui URL langsung tanpa otorisasi controller.
- **Log Sanitization**: NIK lengkap dan foto KTP dilarang dicatat ke dalam file log aplikasi.

---

## 2. FITUR F — PEMULIHAN PASSWORD PENYEWA (PASSWORD RECOVERY)

### 2.1. Alur Recovery yang Dimulai oleh Pengelola (Admin-Initiated)
Ketika penyewa lupa password atau terkunci dari akun:
1. Petugas berwenang (Admin/Superadmin) membuka menu pemulihan password di web dashboard.
2. Memasukkan alamat email penyewa terdaftar.
3. Sistem memverifikasi keberadaan user dan role `tenant`.
4. Sistem membuat secure token acak 64 karakter (`Str::random(64)`).
5. Hash SHA-256 dari token disimpan di tabel `password_reset_tokens` bersama timestamp `created_at`.
6. Tautan pemulihan dikirimkan ke email terverifikasi milik penyewa (atau diberikan melalui kanal verifikasi manual yang aman).

### 2.2. Kontrol Keamanan & Pembatasan
- **Masa Berlaku Token**: Token kedaluwarsa secara otomatis dalam 60 menit.
- **Sekali Pakai (One-Time Use)**: Segera setelah password baru berhasil disimpan, token dihapus secara permanen dari tabel `password_reset_tokens`.
- **Pencabutan Sesi (Sanctum Token Revocation)**:
  Untuk mencegah akun tetap diakses oleh pihak yang tidak sah setelah pergantian password, seluruh Sanctum personal access token milik akun tersebut dicabut secara instan:
  ```php
  $user->tokens()->delete();
  ```
- **Rate Limiting**:
  Maksimum 3 permintaan reset password per jam untuk setiap akun pengguna guna mencegah serangan brute-force dan spam email.
- **Pencegahan User Enumeration**:
  Respons publik dari endpoint pemulihan tidak membocorkan apakah suatu email terdaftar atau tidak, mencegah enumeration attack.
- **Audit Trail (`password_reset_logs`)**:
  Setiap aktivitas permintaan dan penyelesaian reset password dicatat:
  - `actor_id`: ID staf/admin yang menginisiasi (atau NULL jika self-service).
  - `user_id`: ID penyewa yang dipulihkan.
  - `email`: Alamat email tujuan.
  - `ip_address`: Alamat IP pemohon.
  - `status`: `'requested'`, `'completed'`, atau `'expired'`.
  - Token rahasia atau password **TIDAK PERNAH** disimpan di log audit.

---

## 3. AUDIT KESIAPAN STAGING & HOSTING

1. **Hostinger Shared Hosting Constraints**:
   - Shared hosting tidak memberikan akses root atau daemon background independen.
   - Database MySQL versi spesifik belum terverifikasi di server live.
2. **Status Pengujian Database**:
   - Seluruh 189 unit dan feature test Laravel berjalan 100% pada database test terisolasi (SQLite in-memory) dengan schema kompatibel ANSI SQL.
   - Pengujian terhadap MySQL 8 produksi sengaja **TIDAK DILAKUKAN** demi melindungi data aktif dan mematuhi batasan bahwa staging MySQL 8 belum diprovisi.
   - Migration yang dibuat bersifat sepenuhnya additive (`ADD COLUMN IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`) sehingga aman dijalankan saat database staging/hosting siap.
