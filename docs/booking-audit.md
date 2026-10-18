# Audit Menyeluruh Sistem Booking (Pemesanan Kamar)
## SIM Kos & Guesthouse (Laravel 13 + Blade + Bootstrap 5 + Flutter)

Dokumen ini mencatat baseline awal, temuan audit arsitektur, celah keamanan, dan rencana implementasi additive sebelum modifikasi kode dilakukan.

---

## 1. Status Git & Lingkungan

- **Branch Aktif**: `feature/booking-end-to-end`
- **Base Commit**: `0aa8495` (`feature/phase6-financial-analytics`)
- **Working Tree State**: Bersih dari perubahan destruktif; perubahan lokal dari Phase 6 sidebar drawer dan right-side drawer test dipertahankan secara utuh tanpa git reset.
- **Konstrain Keamanan Terpenuhi**:
  - Tidak ada `git push`, merge to `main`, atau `force-push`.
  - Tidak ada operasi database destruktif (`migrate:fresh`, `db:wipe`).
  - Database pengujian terisolasi menggunakan SQLite in-memory (`:memory:`) via `phpunit.xml`.
  - Data historis booking, invoice, dan pembayaran aman tanpa penghapusan.

---

## 2. Audit Database & Model Domain

### A. Tabel `bookings` (`2026_10_13_150006_create_bookings_table.php`)
- **Struktur Existing**:
  - Kolom: `id`, `booking_code`, `property_id`, `room_id`, `user_id`, `rental_type`, `start_date`, `end_date`, `actual_check_in`, `actual_check_out`, `rate_amount`, `deposit_amount`, `total_amount`, `status`, `notes`, `timestamps`.
  - Status Enum Existing: `pending`, `confirmed`, `active`, `completed`, `cancelled`, `overstay`.
- **Temuan & Celah**:
  1. `user_id` bersifat `NOT NULL` (`constrained('users')`). Hal ini menghambat booking publik oleh guest/calon penyewa yang belum memiliki akun.
  2. Belum ada kolom untuk menyimpan identitas guest (`guest_name`, `guest_email`, `guest_phone`, `guest_id_card`).
  3. Belum ada kolom `access_token` (token acak 64 karakter) untuk pelacakan status publik yang aman dan anti-IDOR/enumerasi.
  4. Belum ada kolom `down_payment_amount` (uang muka opsional per properti).
  5. Belum ada status `rejected` dan `expired` di enum `BookingStatus`.
  6. Belum ada kolom pencatatan audit penolakan/pembatalan (`rejection_reason`, `cancellation_reason`, `confirmed_by`, `cancelled_by`, `checked_in_by`, `expires_at`).
  7. Perhitungan durasi sewa di `BookingController::store` lama mengabaikan pengali durasi hari/bulan (hanya mengambil flat `base_price`).

### B. Tabel `properties` (`2026_10_13_150000_create_properties_table.php`)
- **Struktur Existing**: `code`, `name`, `type`, `address`, `city`, `province`, `postal_code`, `latitude`, `longitude`, `phone`, `rules`, `facilities`, `is_active`.
- **Temuan & Celah**:
  1. Belum ada konfigurasi kebijakan uang muka (DP): `dp_required` (boolean), `dp_type` (`fixed`/`percentage`), `dp_amount`, `dp_percentage`, dan `payment_deadline_hours`.
  2. Belum ada konfigurasi uang jaminan (deposit): `deposit_amount`.

### C. Tabel `invoices` (`2026_10_13_150007_create_invoices_table.php`)
- **Struktur Existing**: `invoice_number`, `property_id`, `booking_id`, `user_id`, `invoice_type`, `subtotal`, `discount`, `penalty`, `total_amount`, `paid_amount`, `status`, `due_date`, `paid_at`.
- **Temuan & Celah**:
  1. `user_id` bersifat `NOT NULL`. Pada alur guest booking publik, invoice awal untuk down payment perlu dibuat sebelum akun penyewa resmi terhubung. Kolom `user_id` perlu dibuat nullable secara aditif.

---

## 3. Audit Backend & REST API

### A. Route API (`backend/routes/api.php`)
- **Existing**:
  - `GET /api/v1/bookings` (Auth only)
  - `POST /api/v1/bookings` (Auth only)
  - `GET /api/v1/bookings/{id}` (Auth only)
- **Kekurangan Endpoint**:
  1. Tidak ada endpoint publik untuk pencarian ketersediaan kamar (`GET /api/v1/public/rooms/availability`).
  2. Tidak ada endpoint publik untuk pembuatan booking guest (`POST /api/v1/public/bookings`) dengan rate limiting ketat (brute force & anti-spam).
  3. Tidak ada endpoint publik untuk pelacakan status booking via access token (`GET /api/v1/public/bookings/track/{token}`).
  4. Tidak ada endpoint aksi pengelolaan status booking:
     - `POST /api/v1/bookings/{id}/confirm`
     - `POST /api/v1/bookings/{id}/reject`
     - `POST /api/v1/bookings/{id}/cancel`
     - `POST /api/v1/bookings/{id}/check-in`
     - `POST /api/v1/bookings/calculate-price` (estimasi biaya real-time)

### B. Controller & Service (`BookingController.php`)
- Menggabungkan logika pembuatan booking di dalam controller.
- Belum memiliki `BookingService` terdedikasi untuk menangani ketersediaan, pessimistic concurrency locking, transisi state, pembuatan invoice, dan check-in.
- Multi-property scoping: `BookingController::index` dan `show` sudah memiliki logika isolasi properti untuk staf, namun belum ada Form Request terisolasi dan validasi role yang ketat pada aksi transisi.

---

## 4. Audit Website (Blade & Bootstrap 5)

### A. Website Publik
- **Kondisi Awal**:
  - `routes/web.php` mengarahkan root `/` langsung redirect ke `/dashboard`.
  - Tidak ada halaman publik katalog kamar, detail kamar, form booking, maupun pelacakan status.
- **Rencana Implementasi**:
  1. Route `/catalog` atau `/` untuk melihat katalog properti & tipe kamar dengan filter (lokasi/properti, tipe, rentang harga, tanggal).
  2. Route `/rooms/{id}` untuk detail kamar, fasilitas, aturan kos, dan cek ketersediaan.
  3. Route `/booking/create` atau form di detail kamar untuk submit guest booking.
  4. Route `/booking/track/{token}` untuk pelacakan status booking aman tanpa menampilkan data sensitif publik.

### B. Dashboard Internal (`resources/views/dashboard/bookings/`)
- **Kondisi Awal**:
  - Tidak ada menu pemesanan di sidebar dashboard (hanya ada invoice dan payment).
  - Tidak ada view `resources/views/dashboard/bookings/`.
- **Rencana Implementasi**:
  1. Tambah link "Pemesanan (Booking)" pada sidebar di bawah grup "Operasional Sewa".
  2. Buat `BookingWebController.php` dengan routing:
     - `GET /dashboard/bookings` (daftar dengan filter status, properti, tanggal, dan search)
     - `GET /dashboard/bookings/{id}` (detail booking, invoice, payment, aksi status)
     - `POST /dashboard/bookings` (pembuatan booking internal via Right-Side Drawer `#createBookingDrawer`)
     - `POST /dashboard/bookings/{id}/confirm`
     - `POST /dashboard/bookings/{id}/reject`
     - `POST /dashboard/bookings/{id}/cancel`
     - `POST /dashboard/bookings/{id}/check-in`

---

## 5. Audit Aplikasi Flutter (Android/iOS)

### A. Fitur & Struktur Directory
- `mobile/lib/features/` memiliki modul `properties`, `invoices`, `inspections`, `utilities`, `maintenance`, `complaints`, `housekeeping`, `finance`.
- **Tidak ada** folder `mobile/lib/features/booking/`.
- Pada `property_detail_screen.dart`, tombol "Pesan Kamar Sekarang" hanya menampilkan dummy `SnackBar`.

### B. Routing & Autentikasi Guest
- `mobile/lib/routing/app_router.dart` menerapkan redirect paksa:
  ```dart
  if (!isAuth && !isLoggingIn) {
    return '/login';
  }
  ```
- Tidak mengizinkan guest mengakses fitur publik.
- Perlu penyesuaian whitelist route publik (`/guest/properties`, `/guest/book`, `/guest/booking-track`).

---

## 6. Audit Pengujian (Baseline Test Suite)

- **Backend Test Status**: 105 tests lulus (636 assertions), durasi ~1.8 detik.
- **Flutter Test Status**: 34 tests lulus, durasi ~4 detik.
- **Flutter Analyze Status**: 0 issues found (clean).

---

## 7. Rencana Kerja Bertahap (Next Batches)

1. **Batch B: Domain Booking, Migrasi Additive & REST API**:
   - Migrasi additive: fields guest, access token, DP policy, audit reasons, nullable `user_id`.
   - Update enum `BookingStatus` (`REJECTED`, `EXPIRED`).
   - Implementasi `BookingService` (kalkulasi harga, DP policy, pessimistic locking, anti-double booking, check-in, invoice generation).
   - Update `BookingController` & buat `PublicBookingController` dengan rate limiting dan Form Requests.
2. **Batch C: Website Blade & Bootstrap 5**:
   - Layout publik & halaman: katalog, detail kamar, form booking publik, pelacakan status.
   - Dashboard internal: daftar booking, right-side drawer pembuatan booking, detail booking dengan konfirmasi status dan check-in.
   - Integrasi navigasi sidebar dashboard.
3. **Batch D: Aplikasi Flutter Mobile**:
   - Modul `features/booking/` (models, repository, Riverpod providers, screens).
   - Form booking & tracking status guest.
   - Layar manajemen booking untuk staf/admin.
   - Integrasi tombol "Pesan Kamar Sekarang" pada `property_detail_screen.dart`.
4. **Batch E: Automated Regression Testing & Dokumentasi**:
   - Test suite komprehensif `BookingEndToEndTest.php`.
   - Flutter unit & widget tests untuk booking.
   - Dokumentasi lengkap (`docs/booking-workflow.md`, `docs/booking-api.md`, `docs/booking-testing.md`).

---

## 8. Hasil Audit Final Sebelum Phase 7

- **Status Migrasi**: 23/23 migrasi berhasil dieksekusi pada database lokal (`backend/database/database.sqlite`).
- **Verifikasi UI Web**: Dashboard `/dashboard/bookings` dan `/dashboard/bookings/1` terverifikasi HTTP 200 OK dengan null-safe checks dan right-side drawer aktif.
- **Hasil Pengujian Backend**: 120 tests lulus (704 assertions) via `php artisan test` (0 failures).
- **Hasil Pengujian Mobile**: 41 tests lulus via `flutter test`, `flutter analyze` 0 issues.
- **Catatan Arsitektur Akuntansi**: Pencatatan pendapatan saat ini berbasis kas terverifikasi. Ledger kewajiban deposit terpisah (balance sheet liability) direkomendasikan untuk ditambahkan saat modul akuntansi Phase 7.

