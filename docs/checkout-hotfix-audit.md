# LAPORAN AKHIR: AUDIT & PERBAIKAN HOTFIX ERROR CHECKOUT SIM KOS & GUESTHOUSE

**Dokumen**: `docs/checkout-hotfix-audit.md`  
**Status**: SELESAI, TERUJI & TERVALIDASI 100%  
**Branch Aktif**: `feature/booking-end-to-end`  
**HEAD Commit**: `0aa84952cd0e4f26be1d7ca407ee9ab19eb1905e`  
**Waktu Audit & Perbaikan**: 17 Oktober 2026  

---

## 1. RINGKASAN EKSEKUTIF

Meskipun laporan Phase 9 mencatat seluruh pengujian unit/feature lulus, modul Check-Out & Settlement dilaporkan mengalami kegagalan (HTTP 500 error) saat diakses oleh pengguna melalui antarmuka web.

Melalui audit mendalam dan reproduksi terisolasi:
1. Ditemukan tabrakan routing pada Laravel Web Routes (`backend/routes/web.php`) di mana `Route::get('/{id}', ...)` mendahului `Route::prefix('settlements')`. Hal ini menyebabkan URL `/dashboard/deposits/settlements` ditangkap oleh rute show deposit dengan parameter string `$id = 'settlements'`, memicu fatal exception `TypeError: Argument #1 ($id) must be of type int, string given` dengan HTTP 500.
2. Ditemukan kesalahan nama relasi eager loading `'settlements.inspector'` pada `DepositWebController::show()` yang seharusnya `'settlements.inspection.inspector'`.
3. Ditemukan batasan status pada `DepositService::initiateCheckOutSettlement` yang menolak booking berstatus `OVERSTAY` untuk melakukan check-out.
4. Ditemukan ketiadaan tombol aksi langsung "Proses Check-Out / Settlement" pada halaman detail pemesanan (`bookings/show.blade.php`) bagi hunian yang sedang aktif.
5. Ditemukan aturan validasi yang terlalu ketat pada `finalizeRefund` yang mewajibkan rekening tujuan transfer (`refund_destination`) meskipun nominal pengembalian adalah Rp 0 (saldo deposit nol atau habis terpotong denda).

Seluruh akar masalah telah diperbaiki dengan perubahan minimal (*surgical hotfix*), diverifikasi dengan 16 regression test baru (`CheckOutHotfixTest.php`), dan seluruh test suite backend (178 pengujian) serta Flutter (53 pengujian) lulus 100%.

---

## 2. GEJALA ERROR YANG BERHASIL DIREPRODUKSI

### 2.1 Pesan Error & Stack Trace Aktual
- **URL / Route**: `GET /dashboard/deposits/settlements`
- **HTTP Status**: `500 Internal Server Error`
- **Exception**: `TypeError: App\Http\Controllers\Web\DepositWebController::show(): Argument #1 ($id) must be of type int, string given`
- **Lokasi Terjadinya**: `backend/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php:46` menuju `DepositWebController.php:93`

**Bukti Log Aktual dari `storage/logs/laravel.log`**:
```text
[2026-10-17 11:47:33] local.ERROR: App\Http\Controllers\Web\DepositWebController::show(): Argument #1 ($id) must be of type int, string given, called in /Users/ameliavirismanda/Documents/Mas Huda/kos_baru/backend/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php on line 46 {"userId":1,"exception":"[object] (TypeError(code: 0): App\\Http\\Controllers\\Web\\DepositWebController::show(): Argument #1 ($id) must be of type int, string given, called in /Users/ameliavirismanda/Documents/Mas Huda/kos_baru/backend/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php on line 46 at /Users/ameliavirismanda/Documents/Mas Huda/kos_baru/backend/app/Http/Controllers/Web/DepositWebController.php:93)
[stacktrace]
#0 /Users/ameliavirismanda/Documents/Mas Huda/kos_baru/backend/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(46): App\\Http\\Controllers\\Web\\DepositWebController->show('settlements')
#1 /Users/ameliavirismanda/Documents/Mas Huda/kos_baru/backend/vendor/laravel/framework/src/Illuminate/Routing/Route.php(276): Illuminate\\Routing\\ControllerDispatcher->dispatch(Object(Illuminate\\Routing\\Route), Object(App\\Http\\Controllers\\Web\\DepositWebController), 'show')
```

### 2.2 Hasil Reproduksi via Tinker
```bash
php artisan tinker --execute="echo app('router')->getRoutes()->match(request()->create('/dashboard/deposits/settlements', 'GET'))->getName();"
# Hasil: dashboard.deposits.show (SEHARUSNYA: dashboard.deposits.settlements.index)
```

---

## 3. ROOT CAUSE ANALYSIS (PENYEBAB UTAMA)

1. **Routing Precedence Collision**:
   Pada `backend/routes/web.php` baris 48–62:
   ```php
   Route::prefix('deposits')->name('deposits.')->group(function () {
       Route::get('/', [DepositWebController::class, 'index'])->name('index');
       Route::get('/{id}', [DepositWebController::class, 'show'])->name('show'); // <-- Wildcard tanpa constraint
       Route::post('/{id}/adjustment', [DepositWebController::class, 'recordAdjustment'])->name('adjustment');

       // Check-Out Settlements
       Route::prefix('settlements')->name('settlements.')->group(function () {
           Route::get('/', [DepositWebController::class, 'settlementsIndex'])->name('index');
           ...
       });
   });
   ```
   Laravel mengevaluasi route secara sekuensial. Rute `/{id}` didaftarkan **sebelum** sub-prefix `settlements`, tanpa batasan regular expression `whereNumber('id')`. Sehingga request `GET /dashboard/deposits/settlements` dianggap sebagai pemanggilan rute `show` dengan nilai parameter string `'settlements'`, memicu fatal TypeError karena signature controller adalah `show(int $id)`.

2. **Salah Relasi Eager Loading pada `DepositWebController::show`**:
   Pada baris 112:
   `'settlements.inspector'` dipanggil pada relasi `CheckOutSettlement`, padahal model `CheckOutSettlement` tidak memiliki method `inspector()`. Petugas inspeksi terhubung melalui relasi inspeksi (`settlements.inspection.inspector`).

3. **Status Hunian Overstay Terkunci dari Check-Out**:
   Pada `DepositService::initiateCheckOutSettlement` baris 238:
   `if (!in_array($lockedBooking->status, [BookingStatus::ACTIVE, BookingStatus::CONFIRMED]))`
   Penyewa yang terlambat check-out atau memiliki status `BookingStatus::OVERSTAY` tertolak dengan exception bisnis saat manajemen kos hendak memproses check-out dan memotong denda keterlambatan/kerusakan.

4. **Ketiadaan Tombol Aksi Check-Out pada Detail Booking**:
   Pada Blade `resources/views/dashboard/bookings/show.blade.php`, tombol konfirmasi dan check-in tersedia, namun untuk booking yang sudah aktif tidak terdapat jalan pintas langsung untuk memproses settlement check-out.

5. **Validasi Kaku pada Finalisasi Pengembalian Dana Nol**:
   Pada `DepositWebController::finalizeRefund` dan `DepositController::finalizeRefund`, field `refund_destination` diwajibkan (`required`), menyebabkan kegagalan validasi jika booking tidak memiliki deposit atau seluruh deposit telah habis dipotong denda kerusakan.

---

## 4. PERBAIKAN YANG DITERAPKAN (HOTFIX DIFF)

### 4.1 Perbaikan Urutan Routing & Parameter Regex (`backend/routes/web.php`)
- Memindahkan grup rute `settlements` **sebelum** rute wildcard `/{id}`.
- Menambahkan constraint regex `->whereNumber('id')` pada semua rute dengan parameter integer ID.

### 4.2 Perbaikan Eager Loading (`DepositWebController.php`)
- Mengubah `'settlements.inspector'` menjadi `'settlements.inspection.inspector'`.
- Menyesuaikan query daftar booking aktif pada `settlementsIndex` agar menyertakan status `BookingStatus::OVERSTAY`.
- Menjadikan validasi `refund_destination` kondisional: hanya wajib jika `net_refund_amount > 0`.

### 4.3 Dukungan Check-Out Overstay (`DepositService.php` & `Booking.php`)
- Menambahkan `BookingStatus::OVERSTAY` ke dalam status sah untuk check-out settlement di `DepositService::initiateCheckOutSettlement`.
- Menambahkan helper method `canCheckOut(): bool` pada model `Booking`.

### 4.4 Integrasi UI Tombol Check-Out (`bookings/show.blade.php` & `settlements_index.blade.php`)
- Menambahkan tombol dinamis "Proses Check-Out" atau "Lihat Settlement Check-Out" pada header detail booking.
- Mendukung auto-select kamar/penyewa dan pembukaan drawer otomatis ketika parameter `?booking_id=...` dilewatkan ke halaman settlement.
- Menyesuaikan modal konfirmasi finalisasi refund pada `settlement_show.blade.php` untuk menampilkan pesan informatif saat net refund adalah Rp 0.

---

## 5. HASIL REGRESSION TEST (15 SKENARIO WAJIB + OVERSTAY)

Dibuat test suite komprehensif terisolasi pada `backend/tests/Feature/CheckOutHotfixTest.php`:

| No | Skenario Pengujian | Hasil | Catatan / Bukti |
|---|---|---|---|
| 1 | Checkout normal tanpa potongan deposit | **PASSED** | Saldo utuh, status settlement APPROVED, booking COMPLETED, room DIRTY |
| 2 | Checkout dengan potongan kerusakan valid | **PASSED** | Potongan dicatat, saldo bersih berkurang, disetujui penyewa, mutasi klop |
| 3 | Checkout dengan refund deposit | **PASSED** | Mutasi refund `REFUND_PAYOUT`, `direction = 'out'`, saldo sebelum/sesudah valid |
| 4 | Checkout tanpa deposit / saldo deposit nol | **PASSED** | Net refund Rp 0, tidak mewajibkan rekening transfer, booking COMPLETED |
| 5 | Checkout ketika inspeksi belum lengkap / absen | **PASSED** | Tetap berjalan aman, inspeksi bernilai null tanpa error |
| 6 | Checkout berulang / request retry dicegah | **PASSED** | DomainException dilempar jika sudah ada settlement aktif |
| 7 | Tenant tidak berwenang dilarang finalisasi | **PASSED** | HTTP 403 Forbidden ketika tenant mencoba memfinalisasi refund |
| 8 | Isolasi antar-properti (Staf A vs Properti B) | **PASSED** | HTTP 403 Forbidden pada akses Web dan API antarcabang |
| 9 | Penanganan booking/settlement tidak ditemukan | **PASSED** | HTTP 404 Not Found dikembalikan secara konsisten |
| 10 | Rollback transaksi saat kegagalan jurnal/sistem | **PASSED** | Exception pada jurnal me-rollback status booking dan kamar |
| 11 | Status kamar berubah ke DIRTY setelah checkout | **PASSED** | Kamar dialihkan ke `RoomStatus::DIRTY` (bukan langsung `AVAILABLE`) |
| 12 | Data lama dengan field opsional kosong | **PASSED** | Guest booking tanpa user ID atau catatan lama tetap diproses mulus |
| 13 | Website routing `/dashboard/deposits/settlements` | **PASSED** | HTTP 200 OK, me-resolve method yang tepat tanpa tabrakan tipe data |
| 14 | Kontrak API Endpoint untuk Flutter Mobile | **PASSED** | Response JSON valid: index, detail, tenant decision, dan finalize |
| 15 | Idempotensi: Bebas mutasi/jurnal duplikat | **PASSED** | Mutasi refund tunggal, jurnal double-entry seimbang debit = kredit |
| 16 | Booking berstatus OVERSTAY dapat check out | **PASSED** | Status overstay berhasil diproses settlement dan diselesaikan |

---

## 6. VALIDASI TEST SUITE LENGKAP

1. **CheckOutHotfixTest (Spesifik)**:
   ```text
   OK (16 tests, 66 assertions) — 100% Passed
   ```
2. **Backend Test Suite Total (`php artisan test`)**:
   ```text
   PASS Tests: 178 passed (994 assertions) — 100% Passed
   Duration: 3.91s
   ```
3. **Flutter Static Analysis (`flutter analyze`)**:
   ```text
   Analyzing mobile...
   No issues found! (ran in 1.6s)
   ```
4. **Flutter Test Suite Total (`flutter test`)**:
   ```text
   00:06 +53: All tests passed! — 100% Passed
   ```

---

## 7. DAMPAK TERHADAP SISTEM

- **Booking**: Siklus hidup booking kini lengkap (`PENDING` -> `CONFIRMED` -> `ACTIVE` / `OVERSTAY` -> `COMPLETED`).
- **Deposit & Settlement**: Subledger deposit mencatat mutasi `DAMAGE_DEDUCTION` dan `REFUND_PAYOUT` secara matematis akurat dan idempotent.
- **Akuntansi & Jurnal**: Jurnal double-entry otomatis mencatat pelepasan kewajiban titipan deposit (Akun 2101) terhadap pendapatan ganti rugi (Akun 4104) dan kas/bank keluar (Akun 1001/1002) dalam keadaan seimbang (balance).
- **Operasional Kamar**: Kamar dialihkan ke status `DIRTY` sehingga staf housekeeping wajib membersihkan dan melakukan inspeksi kebersihan sebelum kamar dapat disewakan kembali.
- **Web & Mobile**: Akses rute `/dashboard/deposits/settlements` berjalan stabil tanpa exception, dan endpoint mobile siap melayani aplikasi Android/iOS.

---

## 8. INTEGRITAS WORKING TREE & KESELAMATAN DATA

- Tidak ada perintah destruktif yang dijalankan (`git reset`, `git clean`, `git stash`, force checkout).
- Tidak ada commit, push, merge, rebase, atau perubahan remote git yang dilakukan.
- Tidak ada data riil yang dihapus atau diubah; pengujian dijalankan pada database memori/terisolasi.
- Seluruh 32 file tracked modified dan 50 file untracked dari Fase 1 hingga 10 tetap terjaga utuh.
