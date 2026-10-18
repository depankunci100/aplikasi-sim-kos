# Baseline Audit: Tenant Identity UX, Room Inspection & Housekeeping Workflow Upgrade
**Dokumentasi:** `docs/phase10-ux-workflow-upgrade-baseline.md`  
**Tanggal Audit:** 18 Oktober 2026  
**Status Baseline:** Verified & Complete  
**Branch:** `feature/booking-end-to-end`  
**HEAD Commit:** `0aa84952cd0e4f26be1d7ca407ee9ab19eb1905e`  

---

## 1. Ringkasan Eksekutif & Status Git

Audit komprehensif dilakukan terhadap aplikasi SIM Kos & Guesthouse untuk memetakan arsitektur modul Identitas Penyewa (Tenant Identity), Room Inspection (Fase 3), Housekeeping (Fase 5), dan Tenant Experience (Fase 10).

### Status Repository
- **Branch Aktif:** `feature/booking-end-to-end`
- **Working Tree:** Bersih dari konflik, seluruh berkas lokal modifikasi dan untracked files dari fase-fase sebelumnya dipertahankan utuh.
- **Batasan Keselamatan:**
  - Tidak ada eksekusi perintah destruktif (`migrate:fresh`, `db:wipe`, `truncate`, `drop table`).
  - Tidak ada commit, push, merge, force-push, atau manipulasi Git remote.
  - SQLite database staging lokal aktif dan terjaga integritasnya.

---

## 2. Pemetaan Modul & Komponen Reusable

### A. Modul Identitas & Profil Penyewa (Phase 10)
1. **Model:** `TenantProfile`
   - Kolom: `user_id`, `ktp_number` (encrypted via Laravel Casts), `nik_hash` (blind-index HMAC-SHA256 untuk pencegahan duplikasi global tanpa dekripsi), `is_nik_verified`, `nik_verified_at`, `ktp_photo` (path penyimpanan disk lokal privat), `emergency_name`, `emergency_phone`, `occupation`, `institution_name`, `vehicle_type`, `vehicle_plate`.
   - Accessor: `masked_nik` menghasilkan NIK yang disamarkan (contoh: `3507********0004`).
2. **Service:** `IdentityVerificationService`
   - `validateNikFormat`: Regex tepat 16 digit angka Indonesia (`^[0-9]{16}$`).
   - `computeNikHash`: HMAC sha256 ber-key blind index.
   - `maskNik`: Masking format `3507********1234`.
   - `verifyAndSaveTenantIdentity`: Row locking `lockForUpdate` mencegah race condition duplikasi NIK lintas akun.
3. **Controller & Endpoint:**
   - `GET /api/v1/tenant/identity`: Mengembalikan status verifikasi, `masked_nik`, emergency info, pekerjaan, kendaraan (NIK utuh TIDAK PERNAH dikembalikan).
   - `POST /api/v1/tenant/identity/verify`: Menerima 16 digit NIK, foto KTP, dan atribut pelengkap.
4. **Mobile (Flutter):**
   - `ProfileScreen` (`mobile/lib/features/profile/screens/profile_screen.dart`): Menampilkan info dasar (nama, role, email, phone).
   - `IdentityVerificationScreen` (`mobile/lib/features/profile/screens/identity_verification_screen.dart`): Mengelola formulir verifikasi dan integrasi on-device OCR.
   - `KtpOcrService` (`mobile/lib/features/profile/services/ktp_ocr_service.dart`): Parser Regex on-device untuk NIK 16 digit, nama, dan alamat.

### B. Modul Room Inspection (Phase 3)
1. **Model & Relasi:**
   - `Inspection`: `property_id`, `room_id`, `booking_id`, `inspector_id`, `tenant_id`, `created_by`, `baseline_inspection_id`, `type`, `status`, `scheduled_date`, `overall_condition`, `inspector_notes`, `tenant_feedback`, `tenant_decision_at`, `tenant_due_at`.
   - `InspectionItem`: Item checklist kondisi (`good`, `fair`, `poor`, `damaged`, `missing`), `notes`, `repair_required`, `estimated_cost`.
   - `InspectionMedia`: Foto bukti privat tertaut ke item atau inspeksi keseluruhan (`file_path` disk lokal privat).
   - `InspectionLog`: Audit trail rekaman aksi status dan aktor.
2. **Enum Status Aktual (`InspectionStatus`):**
   - `SCHEDULED ('scheduled')`: Terjadwal / diajukan.
   - `IN_PROGRESS ('in_progress')`: Sedang berlangsung dikerjakan inspektor.
   - `COMPLETED ('completed')`: Selesai dikerjakan inspektor (Menunggu Respon Penyewa).
   - `APPROVED_BY_TENANT ('approved_by_tenant')`: Disetujui penyewa.
   - `OBJECTED_BY_TENANT ('objected_by_tenant')`: Keberatan diajukan penyewa.
   - `CANCELLED ('cancelled')`: Dibatalkan.

### C. Modul Housekeeping (Phase 5)
1. **Model & Relasi:**
   - `HousekeepingTask`: `task_number`, `property_id`, `room_id`, `area_name`, `type`, `priority`, `scheduled_date`, `scheduled_slot`, `assigned_to`, `assigned_by`, `status`, `checklist` (JSON), `photos_before` (JSON), `photos_after` (JSON), `damaged_or_missing_items`, `notes`, `started_at`, `completed_at`, `verified_at`, `verified_by`, `verification_notes`, `cancellation_reason`.
   - `HousekeepingLog`: Audit trail status housekeeping.
2. **Enum Status Aktual (`HousekeepingStatus`):**
   - `SCHEDULED ('scheduled')`: Terjadwal / pengajuan masuk.
   - `ASSIGNED ('assigned')`: Ditugaskan ke staf kebersihan.
   - `IN_PROGRESS ('in_progress')`: Sedang dikerjakan (foto before diunggah).
   - `PENDING_VERIFICATION ('pending_verification')`: Menunggu verifikasi (checklist selesai, foto after diunggah).
   - `COMPLETED ('completed')`: Selesai & Bersih (diverifikasi).
   - `CANCELLED ('cancelled')`: Dibatalkan.
3. **Enum Tipe Housekeeping (`HousekeepingType`):**
   - `ROOM_CLEANING ('room_cleaning')`: Pembersihan rutin.
   - `TURNOVER_CLEANING ('turnover_cleaning')`: Turnover check-in/out.
   - `DEEP_CLEANING ('deep_cleaning')`: Deep cleaning.
   - `COMMON_AREA_CLEANING ('common_area_cleaning')`: Area bersama.
   - `ON_DEMAND ('on_demand')`: Permintaan khusus penghuni (Room Service Cleaning).

---

## 3. Analisis Gap & Rencana Upgrade End-to-End

| Modul | Status Saat Ini | Kebutuhan Sesuai Spesifikasi Prompt | Rencana Implementasi |
| :--- | :--- | :--- | :--- |
| **Menu OCR Mobile** | Muncul pada quick shortcut / entry point verifikasi | OCR tidak boleh muncul di menu utama atau dashboard penyewa sebagai fitur mandiri | Hapus dari home screen & shortcut; jadikan tombol bantuan opsional hanya di dalam Edit Profil & Identitas |
| **Profil & Identitas** | ProfileScreen hanya menampilkan email & phone dasar | Menampilkan Nama, Status Verifikasi, NIK Tersamar, Tanggal Diperbarui, Data Kontak Darurat, Tombol Edit Identitas | Rombak UI ProfileScreen & sub-screen Edit Identitas dengan validasi aman & feedback real-time |
| **Entry Point Layanan Kamar** | Halaman request terpisah (`/housekeeping/request` & `/inspections/request`) | Satu entry point terpadu "Layanan Kamar" di Flutter dengan opsi: (1) Inspeksi Kamar, (2) Pembersihan Kamar | Buat screen `RoomServiceHubScreen` yang menyatukan pembuatan pengajuan, riwayat, dan pelacakan status |
| **Web Antrean Layanan** | Halaman Housekeeping & Inspeksi terpisah | Dashboard terpadu queue pengajuan dengan filter: Properti, Status, Tipe Layanan, Rentang Tanggal, Staf, Prioritas | Tingkatkan Blade view antrean dengan Bootstrap 5 Drawer/Modal untuk approval, reschedule, reject, & assignment |
| **Review & Konfirmasi Tenant** | Inspeksi sudah memiliki `approve` & `object`, Housekeeping hanya supervisor | Penyewa harus dapat mengonfirmasi hasil pekerjaan ("Sesuai" atau "Minta Perbaikan") pada kedua layanan | Tambahkan endpoint `tenantApprove` dan `tenantRequestRevision` pada Housekeeping dengan audit trail lengkap |
| **Foto Before & After** | Housekeeping menyimpan JSON array path, Inspeksi menggunakan `InspectionMedia` | Validasi tipe file, MIME type, private storage streaming endpoint aman | Standarisasi akses foto privat tanpa URL publik permanen, dengan otorisasi ketat per aktor |

---

## 4. State Machine Terpadu

```
[Tahap 1: Pengajuan Penyewa]
   Penyewa Memilih Kamar Aktif & Waktu Layanan
               │
               ▼
   Status: SCHEDULED / PENDING
               │
[Tahap 2: Review Manajer]
   ├── Disetujui Tanggal & Jam ──► SCHEDULED (Approved)
   ├── Penjadwalan Ulang ────────► SCHEDULED (Rescheduled Slot)
   ├── Ditolak (Alasan Wajib) ───► CANCELLED / REJECTED (With Reason)
   │
   ▼
[Tahap 3: Disposisi Petugas]
   Manajer Menugaskan Staf ──────► ASSIGNED
               │
               ▼
[Tahap 4 & 5: Pelaksanaan]
   Staf Memulai (Foto Before) ───► IN_PROGRESS
   Staf Mengerjakan Checklist
   Staf Mengunggah Foto After
               │
               ▼
[Tahap 6: Penyerahan Hasil]
   Staf Klik "Ajukan Konfirmasi" ─► PENDING_VERIFICATION (Housekeeping)
                                  COMPLETED (Inspection, Waiting Review)
               │
[Tahap 7: Verifikasi Penyewa]
   ├── "Sesuai — Konfirmasi Selesai" ──► COMPLETED / APPROVED_BY_TENANT
   └── "Belum Sesuai — Minta Perbaikan" ─► REVISION_REQUESTED / OBJECTED_BY_TENANT
               │
               └─► Masuk Antrean Manajer untuk Penugasan Ulang (Staf Perbaiki & Submit Ulang)
```

---

## 5. Ringkasan Kesiapan Eksekusi
- Backend Laravel 13: 200 Feature Tests Passing (1,090 assertions).
- Flutter Mobile: 61 Widget/Unit Tests Passing.
- Keamanan: Data NIK terlindungi enkripsi AES-256 + blind index HMAC-SHA256, penyimpanan foto privat terisolasi per properti dan penyewa.
