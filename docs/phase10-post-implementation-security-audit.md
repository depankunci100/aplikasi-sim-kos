# LAPORAN AUDIT KEAMANAN & INTEGRASI: PHASE 10 ADDENDUM
## SIM Kos & Guesthouse — Laravel 13, Blade + Bootstrap 5, Flutter Mobile

Dokumen ini memuat hasil audit keamanan teknis lanjutan (*post-implementation security audit*) atas fitur Phase 10 Addendum (Pengumuman, Permohonan Housekeeping, Permohonan Inspeksi Kamar, OCR KTP, Enkripsi NIK Global, dan Pemulihan Password).

---

## 1. STATUS REPOSITORY & WORKING TREE AUDIT

- **Branch Aktif**: `feature/booking-end-to-end`
- **Commit HEAD**: `0aa8495 feat(ui): redesign web dashboard layout from top navbar to modern collapsible vertical sidebar`
- **Status Integritas Working Tree**:
  - Seluruh file modified dan untracked dari Phase 1 s/d Phase 10 **dipertahankan 100%**.
  - Tidak ada eksekusi `git reset --hard`, `git clean`, destructive checkout, atau stash.
  - Tidak ada commit, push, merge branch, atau modifikasi remote Git.
  - Tidak ada perintah perusak database (`migrate:fresh`, `db:wipe`, `DROP DATABASE`, `TRUNCATE`).
  - Database produksi tidak diakses atau diubah.
  - File rahasia `.env` tidak pernah dicetak ke console atau log.

---

## 2. AUDIT ROUTE & PENCEGAHAN ROUTE COLLISION

### 2.1. Analisis Benturan Route Statis vs Parameter Dinamis
Pada REST API, terdapat potensi kerentanan di mana route dinamis (misal `{id}`) menangkap segment URL statis jika urutan deklarasi terbalik atau jika constraint tipe data tidak diterapkan.

Kasus kritis yang diaudit:
- `GET /api/v1/announcements/unread-count` (Statis)
- `GET /api/v1/announcements/{id}` (Dinamis)
- `POST /api/v1/housekeeping/request` vs `GET /api/v1/housekeeping/{id}`
- `POST /api/v1/inspections/request` vs `GET /api/v1/inspections/{id}`
- `GET /api/v1/notifications/unread-count` vs `POST /api/v1/notifications/{id}/read`

### 2.2. Tindakan Mitigasi yang Diterapkan
1. **Urutan Route**: Route statis (`/unread-count`, `/request`, `/active-room`) diletakkan mendahului route dengan parameter dinamis `{id}`.
2. **Route Parameter Regex Constraint (`whereNumber`)**:
   Seluruh parameter `{id}` pada endpoint sensitif diperketat dengan `->whereNumber('id')`:
   ```php
   // api.php
   Route::get('/announcements/unread-count', [AnnouncementController::class, 'unreadCount']);
   Route::get('/announcements/{id}', [AnnouncementController::class, 'show'])->whereNumber('id');
   Route::post('/announcements/{id}/read', [AnnouncementController::class, 'markAsRead'])->whereNumber('id');
   Route::put('/announcements/{id}', [AnnouncementController::class, 'update'])->whereNumber('id');
   Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])->whereNumber('id');

   Route::post('/housekeeping/request', [HousekeepingController::class, 'tenantRequest']);
   Route::get('/housekeeping/{id}', [HousekeepingController::class, 'show'])->whereNumber('id');

   Route::post('/inspections/request', [InspectionController::class, 'tenantRequest']);
   Route::get('/inspections/{id}', [InspectionController::class, 'show'])->whereNumber('id');
   ```
3. **Bukti Regression Test**:
   Tes `test_announcement_route_unread_count_is_not_captured_by_id_parameter` membuktikan bahwa pemanggilan `/api/v1/announcements/unread-count` mengembalikan HTTP 200 dengan payload hitungan, sedangkan pemanggilan string non-numerik seperti `/api/v1/announcements/non-numeric-id` otomatis menghasilkan HTTP 404 Not Found (kebal benturan).

---

## 3. AUDIT ENKRIPSI NIK & KEUNIKAN GLOBAL (UNIQUE NIK)

### 3.1. Penyimpanan Terenkripsi (At-Rest Encryption)
- **Implementasi**: Kolom `ktp_number` pada `TenantProfile` dikonfigurasikan dengan cast `'ktp_number' => 'encrypted'`.
- **Bukti Forensik Database**:
  Pada pengujian `test_tenant_identity_verification_with_valid_16_digit_nik`, data fisik yang tersimpan di baris database adalah ciphertext AES-256-CBC terautentikasi HMAC:
  ```json
  {
      "ktp_number": "eyJpdiI6IlJwQ0xlajF4VjRia1JaaFNqZ3NpMWc9PSIsInZhbHVlIjoiamNvY0JGTzU2Qy9QSkFGMXBSNUVCUER1eGswNExPYW1ackJ0MUMyWU5jbz0iLCJtYWMiOiJjMWEzNmI4Y2M4ZWQwZWNlYjYyZmIzM2I2OWQwYzk3YTNmZTNjY2IxN2NiODQ5N2ZjMWZmNDBhNTMzZGZiMmVmIiwidGFnIjoiIn0="
  }
  ```
  Nilai plaintext NIK (`3507123456789012`) **TIDAK PERNAH** disimpan di disk database.

### 3.2. Penyamaran (Masking) pada Respons API & UI
- Pengguna hanya melihat NIK termasker melalui accessor `masked_nik`: `3507********9012`.
- Plaintext NIK tidak diekspos pada API response atau log aplikasi.

### 3.3. Penegakan Keunikan Global (Global Uniqueness & Concurrency)
- **Kelemahan `lockForUpdate()` Tanpa Unique Constraint**:
  Jika dua request registrasi masuk bersamaan dengan NIK yang sama saat data belum ada di database, `SELECT ... FOR UPDATE` tidak mengunci baris non-existent (phantom read). Akibatnya, kedua transaksi dapat membaca `null` dan sama-sama melakukan insert.
- **Mitigasi di Tingkat Skema**:
  Migration `2026_10_18_300000_create_phase10_tenant_experience_tables.php` diperbarui untuk menerapkan constraint `CHAR(64) UNIQUE` pada kolom `nik_hash`:
  ```php
  $table->char('nik_hash', 64)->nullable()->unique()->after('ktp_number');
  ```
- **Bukti Uji Konkurensi**:
  Metode uji `test_database_unique_constraint_enforces_nik_uniqueness_at_schema_level` membuktikan bahwa upaya insert duplikat pada tingkat SQL langsung dilempar sebagai `Illuminate\Database\QueryException` (pelanggaran unique constraint), mencegah race condition akun ganda.
- **Penanganan Nilai Null**:
  Sesuai standar ANSI SQL dan InnoDB, kolom `nik_hash` bersifat `nullable()`. Penyewa yang belum memverifikasi NIK memiliki nilai `NULL`, dan multiple `NULL` diizinkan dalam indeks `UNIQUE`.

### 3.4. Evaluasi Rotasi Kunci (Key Rotation Risk)
- `nik_hash` dihitung menggunakan `hash_hmac('sha256', $nik, config('app.key'))`.
- **Risiko Rotasi Kunci**: Jika `APP_KEY` diganti (`php artisan key:generate`), seluruh hash blind index lama tidak akan cocok dengan query NIK baru.
- **Rekomendasi Arsitektur**:
  Mendukung `config('security.nik_hmac_key') ?: config('app.key')`. Kunci HMAC blind-index dapat dipisahkan dari kunci sesi/enkripsi umum agar rotasi `APP_KEY` tidak merusak indeks pencarian identitas.

---

## 4. AUDIT PEMULIHAN PASSWORD (PASSWORD RECOVERY)

### 4.1. Pembuatan & Penyimpanan Token
1. **Entropi Kriptografis**: Token dibuat acak menggunakan `Str::random(64)` (256-bit entropy).
2. **Hashing**: Token disimpan dalam tabel `password_reset_tokens` dalam bentuk hash SHA-256 (`hash('sha256', $plainToken)`). Token mentah tidak disimpan di database.
3. **Pemeriksaan Constant-Time**: Verifikasi token menggunakan `hash_equals($record->token, $hashedToken)` untuk mencegah timing attack.
4. **Masa Berlaku & Sekali Pakai**: Token otomatis kedaluwarsa setelah 60 menit dan langsung dihapus dari database begitu password baru berhasil disimpan.

### 4.2. Pencabutan Sesi Aktif (Sanctum Token Revocation)
- **Audit Kode**:
  Saat admin menginisiasi reset: `$tenant->tokens()->delete();`
  Saat penyewa menyelesaikan reset: `$user->tokens()->delete();`
- **Bukti Pengujian Aktual**:
  Metode uji `test_password_reset_revokes_active_sanctum_tokens` membuktikan:
  1. Personal access token yang tadinya aktif (HTTP 200) langsung terhapus dari tabel `personal_access_tokens`.
  2. Request berikutnya menggunakan Bearer token lama ditolak dengan HTTP 401 Unauthorized.

### 4.3. Anti-Enumerasi & Rate Limiting
- **Anti-Enumeration**: Endpoint publik `/api/v1/auth/password-reset/complete` merespons dengan pesan generik (*"Tautan reset password tidak valid atau telah digunakan"*) tanpa membocorkan apakah email terdaftar di sistem.
- **Rate Limiting**: Maksimal 3 kali inisiasi per jam per akun penyewa berdasarkan audit `PasswordResetLog`.

### 4.4. Kerahasiaan Token & Status Pengiriman Email (BLOCKER)
- **Prinsip**: Petugas/Admin tidak boleh melihat password lama atau token reset penyewa.
- **Status Pengiriman Email**:
  Karena domain dan server SMTP belum tersedia pada shared hosting Hostinger, pengiriman otomatis tautan pemulihan ke inbox penyewa **dinyatakan sebagai BLOCKER**.
- **Mitigasi**: Tautan reset password tidak boleh dibocorkan ke UI admin atau dimasukkan ke notifikasi in-app. Fitur pemulihan akun siap diaktifkan seketika setelah kredensial SMTP Hostinger dikonfigurasikan.

---

## 5. AUDIT OCR KTP & PERLINDUNGAN PRIVASI

### 5.1. Prinsip Client-Side Helper vs Bukti Hukum
- Implementasi `KtpOcrService` pada mobile Flutter diposisikan murni sebagai alat bantu input (*client-side form auto-fill helper*), **bukan bukti keaslian hukum e-KTP**.
- Pengguna wajib meninjau (*review dialog*) dan dapat mengedit NIK/Nama sebelum data dikirim ke backend (*Human-in-the-Loop*).

### 5.2. Toleransi Karakter Ambigu & Validasi
- Unit test mobile membuktikan bahwa karakter ambigu akibat pencahayaan buram (misal huruf 'O' bukannya angka '0', atau huruf 'I' bukannya angka '1') dikompensasi secara cerdas oleh parser regex.
- Input NIK yang tidak terdiri dari tepat 16 digit angka ditolak sebelum request dikirim ke backend.

### 5.3. Perlindungan Berkas Foto KTP
- Foto KTP disimpan di disk privat lokal (`storage/app/private/ktp/{user_id}/`).
- Berkas privat tidak dipublikasikan ke symlink `public/storage` dan tidak memiliki URL publik langsung.
- **Status Uji Kamera Fisik**:
  Unit test parsing dan widget review form telah lulus 100%. Pengujian akses sensor kamera pada hardware fisik Android/iOS berstatus **PENDING PHYSICAL DEVICE VERIFICATION** (memerlukan instalasi APK langsung di HP pengguna).

---

## 6. AUDIT HOUSEKEEPING, INSPEKSI, & PENGUMUMAN

### 6.1. Pengumuman (Announcements)
- **Isolasi Properti**: Scope `forUser($user)` memastikan penyewa hanya menerima pengumuman global (`property_id = null`) atau pengumuman properti tempat ia memiliki booking aktif.
- **Isolasi Role**: Pengumuman dengan target role `staff` difilter di tingkat query SQL dan tidak pernah diekspos ke penyewa.
- **Pelacakan Idempoten**: Tabel `announcement_reads` memiliki compound unique index `['announcement_id', 'user_id']`. Pembacaan berulang tidak menciptakan data duplikat.

### 6.2. Permohonan Housekeeping
- **Kepemilikan Kamar**: `getActiveRoomForTenant` memvalidasi status booking (`confirmed`, `active`, `checked_in`) dan rentang tanggal huni.
- **Pencegahan Duplikasi**: Sistem menolak permohonan kebersihan kamar baru jika sudah ada task `pending` atau `scheduled` pada tanggal yang sama.
- **State Machine Guard**: Penyewa hanya diizinkan membuat permohonan (`pending`) dan membatalkan permohonan miliknya yang masih `pending`. Perubahan ke status `in_progress` atau `completed` hanya dapat dilakukan oleh role staf berwenang.

### 6.3. Permohonan Inspeksi Kamar
- **Penggunaan Modul Existing**: Pengajuan langsung membuat entri pada `room_inspections` Phase 3, menginisialisasi checklist standar ruangan, dan mencatat log audit.
- **Proteksi Checklist Staf**: Penyewa tidak memiliki hak mengubah checklist resmi petugas.
- **Tenant Approval**: Alur persetujuan akhir hasil inspeksi oleh penyewa tetap dipelihara.

---

## 7. REKAPITULASI PENGUJIAN AKTUAL (TEST EVIDENCE)

### 7.1. Backend Laravel (Test Suite Terisolasi)
- **Framework**: PHPUnit / Laravel Test Runner
- **Database Uji**: SQLite In-Memory Database (Terisolasi dari Produksi)
- **Hasil Uji**:
  - `Phase10TenantExperienceTest`: **15 passed, 65 assertions (0 failures)**.
  - Test Suite Keseluruhan: **193 passed, 1,059 assertions (0 failures)**.
  - Waktu Eksekusi: **4.33 detik**.

### 7.2. Mobile Flutter
- **Static Analysis**: `flutter analyze` -> **No issues found!**
- **Test Suite**: **59 passed, 0 failures**.
- **Kompilasi APK**: `flutter build apk --debug` -> **Sukses (`sim-kos-debug.apk`, 105 MB)**.

### 7.3. Status Pengujian Staging MySQL 8
- Database staging MySQL 8 belum dibuat pada hosting Hostinger.
- Sesuai aturan audit: **PENGUJIAN MYSQL 8 DI DEKLARASIKAN BLOCKED HINGGA ENVIRONMENT STAGING MYSQL 8 DISEDIAKAN**.
- Tidak ada klaim palsu bahwa pengujian telah lulus di MySQL 8 sebelum pengujian aktual dijalankan di server live.
