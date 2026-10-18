# BASELINE AUDIT MODUL & WORKING TREE — PHASE 10 ADDENDUM
## SIM Kos & Guesthouse (Laravel 13 REST API + Blade Web + Flutter Mobile)
**Tanggal Audit**: 18 Oktober 2026  
**Pemeriksa**: Senior Software & Security Engineer  
**Branch Aktif**: `feature/booking-end-to-end`  
**Target Deployment**: Shared Hosting (Hostinger Web Hosting)

---

## 1. STATUS WORKING TREE & INTEGRITAS REPOSITORI

### 1.1 Status Git & Working Tree
- **Branch**: `feature/booking-end-to-end`
- **Aturan Keselamatan Ditaati**:
  - TIDAK menjalankan `git reset --hard`, `git clean`, `migrate:fresh`, `db:wipe`, `DROP DATABASE`, atau `TRUNCATE`.
  - TIDAK melakukan `git commit`, `push`, `merge`, rebase, atau mengubah remote URL.
  - TIDAK membaca atau mengekspos kredensial, token, atau secret `.env`.
  - Seluruh 35 file modified dan seluruh untracked files dari Phase 1 s/d Phase 10 dipertahankan 100% utuh tanpa modifikasi destruktif.

### 1.2 Status Baseline Uji Otomatis
- **Backend Test Suite (PHPUnit)**: 178 tests, 994 assertions — **100% PASS** (durasi ~3.9 detik).
- **Mobile Test Suite (Flutter)**: 53 tests — **100% PASS**; `flutter analyze` bersih (0 issues).
- **Staging Database**: Lingkungan lokal menggunakan SQLite (`database/database.sqlite`). Belum ada database staging MySQL 8 aktual (sesuai batasan Hostinger), sehingga semua pengujian dicatat sebagai pengujian database lokal/testing dan tidak mengklaim verifikasi MySQL 8.

---

## 2. HASIL AUDIT MODUL EKSISTING

| Modul | Komponen Eksisting | Status Saat Ini | Kebutuhan Penyesuaian (Addendum) |
| :--- | :--- | :--- | :--- |
| **Identitas & NIK Tenant** | `tenant_profiles` table (`2026_10_13_150002_create_tenant_profiles_table.php`), model `TenantProfile.php`. Field `ktp_number` nullable & unique. | Tersedia di skema DB, namun belum ada relasi di `User.php`, belum ada OCR Flutter, dan belum ada service validasi 16-digit NIK yang ketat. | Buat additive migration untuk `nik_hash` / blind-index, tambahkan service verifikasi NIK dengan race condition lock, implementasikan on-device parser OCR di Flutter dengan konfirmasi manual. |
| **Pengumuman (Announcements)** | Tabel `announcements` pada migration `2026_10_13_150012_create_announcements_table.php`. | Skema tabel DB ada, namun model Eloquent `Announcement.php`, controller API, controller Web Blade, dan pelacakan status baca (`announcement_reads`) **BELUM ADA**. | Buat model `Announcement`, additive migration `announcement_reads` (tracking per-user idempotence), API endpoints, Blade web dashboard (drawer/modal), dan menu Pengumuman di Flutter. |
| **Pembersihan (Housekeeping)** | `housekeeping_tasks`, `housekeeping_logs`, `HousekeepingService`, `HousekeepingController`, Blade dashboard, Flutter screens (Phase 5). | Modul sangat lengkap untuk staf operasional, namun `HousekeepingService::createTask` saat ini memblokir penyewa (`isTenant`) secara eksplisit. | Tambahkan metode `requestRoomCleaning` dan `cancelCleaningRequest` yang memvalidasi booking aktif penyewa, endpoint API pengajuan, form pengajuan di Flutter, dan update status real-time. |
| **Inspeksi Kamar (Room Inspection)** | `inspections`, `inspection_items`, `inspection_logs`, `inspection_media`, `InspectionService`, `InspectionController`, Blade dashboard, Flutter screens (Phase 3). | Modul lengkap untuk staf & manajer, namun `InspectionController::store` memblokir penyewa dari penjadwalan. | Tambahkan metode `requestInspection` khusus penyewa (validasi kamar aktif, alasan, deskripsi, upload foto privat), endpoint API `tenant-request`, form pengajuan di Flutter, dan izin tenant approval. |
| **Notifikasi In-App** | `internal_notifications`, `NotificationService`, `NotificationController` API. | Sudah ada dan berfungsi penuh di backend (`index`, `unreadCount`, `markAsRead`, `markAllAsRead`). | Integrasikan trigger otomatis saat pengumuman dipublikasikan, status housekeeping berubah, dan hasil inspeksi siap di-review penyewa. Tambahkan notifikasi UI di Flutter. |
| **Pemulihan Akun (Password Reset)** | Tabel `password_reset_tokens` standar Laravel (`email`, `token`, `created_at`). | Mekanisme bawaan framework tersedia, namun aksi dashboard bagi staf/manajer untuk inisiasi pemulihan akun penyewa **BELUM ADA**. | Buat `PasswordRecoveryService` & `PasswordRecoveryController` dengan token cryptographically secure (hashed), expired 60 menit, rate-limiting, pencabutan sesi lama, dan audit log tanpa membocorkan token. |

---

## 3. ARSITEKTUR & PRINSIP KEAMANAN

1. **Prinsip Tanpa Duplikasi (DRY)**:
   - Tidak membuat tabel booking, inspeksi, housekeeping, atau notifikasi baru. Seluruh alur diintegrasikan langsung ke tabel `housekeeping_tasks`, `inspections`, `internal_notifications`, dan `announcements`.
2. **Isolasi Properti & Penyewa**:
   - Penyewa hanya dapat mengajukan layanan untuk kamar yang memiliki booking aktif miliknya (`confirmed`, `active`, atau `checked_in`).
   - Mencegah IDOR (*Insecure Direct Object References*) pada semua endpoint pengajuan dan pembatalan.
3. **Additive Migration Only**:
   - Seluruh perubahan skema dibuat dalam file migration baru bertanggal hari ini (`2026_10_18_300000_create_phase10_tenant_experience_tables.php`) tanpa memodifikasi migration lama.
4. **Privasi Dokumen & Kredensial**:
   - Foto KTP dan foto inspeksi disimpan dalam private storage disk (`storage/app/private`).
   - NIK 16 digit divalidasi ketat dan tidak pernah dicetak dalam log, error message, atau respons publik.
   - Password reset menggunakan token bertanda tangan waktu (hashed), single-use, dan tidak pernah mengirim password plaintext.
