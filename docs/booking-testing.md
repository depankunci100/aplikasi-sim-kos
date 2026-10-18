# Panduan & Hasil Pengujian Sistem Pemesanan Kamar (Booking Testing)
## SIM Kos & Guesthouse (Laravel 13 + Bootstrap 5 + Flutter)

Dokumen ini mendokumentasikan tata cara eksekusi pengujian otomatis, matriks cakupan kasus uji (test matrix), dan hasil verifikasi regresi pada backend maupun mobile.

---

## 1. Cara Menjalankan Pengujian

### A. Backend PHPUnit / Pest (Laravel 13)
Jalankan pengujian menggunakan basis data terisolasi SQLite in-memory:
```bash
cd backend

# Menjalankan seluruh test suite pemesanan end-to-end
php artisan test tests/Feature/BookingEndToEndTest.php

# Menjalankan seluruh test suite backend (Regression Test)
php artisan test
```

### B. Mobile Flutter (Android / iOS)
Jalankan analisis kode statis dan automated unit/widget tests:
```bash
cd mobile

# Analisis statis linter (0 issues)
flutter analyze

# Menjalankan test suite modul pemesanan
flutter test test/booking_module_test.dart

# Menjalankan seluruh test unit & widget
flutter test test/booking_module_test.dart test/phase6_financial_modules_test.dart test/phase5_modules_test.dart
```

---

## 2. Matriks Cakupan Kasus Uji (Test Coverage Matrix)

| No | Skenario Pengujian | Target Uji | File Test | Hasil |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Pengajuan booking guest publik berhasil | `PublicBookingController::storeGuestBooking` | `BookingEndToEndTest` | PASS |
| 2 | Pembuatan kode booking acak & access token 60 karakter unik | `BookingService::createBooking` | `BookingEndToEndTest` | PASS |
| 3 | Perhitungan uang muka (DP) per properti (Persentase & Fixed) | `Property::calculateDownPayment` | `BookingEndToEndTest` | PASS |
| 4 | Properti tanpa DP memproses booking dengan DP = 0 | `BookingService::calculatePricing` | `BookingEndToEndTest` | PASS |
| 5 | Pemisahan uang sewa dari deposit jaminan | `BookingService::calculatePricing` | `BookingEndToEndTest` | PASS |
| 6 | Guest ditolak mengakses endpoint internal (`/api/v1/bookings`) | Middleware `auth:sanctum` | `BookingEndToEndTest` | PASS |
| 7 | Booking kamar maintenance / dirty ditolak | `BookingService::checkRoomAvailability` | `BookingEndToEndTest` | PASS |
| 8 | Periode tidak bertabrakan pada kamar yang sama berhasil | `BookingService::checkRoomAvailability` | `BookingEndToEndTest` | PASS |
| 9 | Periode bertabrakan ditolak (Anti Double-Booking) | `BookingService::createBooking` | `BookingEndToEndTest` | PASS |
| 10 | Concurrency lock serialisasi request pemesanan bersamaan | `lockForUpdate` dalam DB Transaction | `BookingEndToEndTest` | PASS |
| 11 | Multi-property isolation (Staf A ditolak akses properti B) | Scoping `property_users` | `BookingEndToEndTest` | PASS |
| 12 | Staf berwenang dapat mengonfirmasi pemesanan | `BookingService::confirmBooking` | `BookingEndToEndTest` | PASS |
| 13 | Penolakan pemesanan dengan alasan wajib membatalkan invoice | `BookingService::rejectBooking` | `BookingEndToEndTest` | PASS |
| 14 | Pembatalan pemesanan melepaskan kamar untuk dipesan kembali | `BookingService::cancelBooking` | `BookingEndToEndTest` | PASS |
| 15 | Check-in mengubah status jadi `active` dan kamar jadi `occupied` | `BookingService::checkInBooking` | `BookingEndToEndTest` | PASS |
| 16 | Check-in ganda dicegah secara otomatis | `BookingService::checkInBooking` | `BookingEndToEndTest` | PASS |
| 17 | Verifikasi pembayaran DP mengonfirmasi booking otomatis | `PaymentService::verifyPayment` | `BookingEndToEndTest` | PASS |
| 18 | Pelacakan status via access token aman tanpa leak password | `PublicBookingController::trackBooking` | `BookingEndToEndTest` | PASS |
| 19 | Token acak salah / invalid menghasilkan 404 Not Found | `PublicBookingController::trackBooking` | `BookingEndToEndTest` | PASS |
| 20 | Pembersihan booking kedaluwarsa (`purgeExpiredBookings`) | `BookingService::purgeExpiredBookings` | `BookingEndToEndTest` | PASS |
| 21 | Akses rute web katalog, detail kamar, dan dashboard booking | Web Routes & Controllers | `BookingEndToEndTest` | PASS |
| 22 | Parsing model JSON, status badge, dan widget screens Flutter | `BookingModel` & Screens | `booking_module_test.dart` | PASS |

---

## 3. Hasil Eksekusi Uji Aktual

### A. Backend PHPUnit / Pest
- **Total Test**: 120 tests
- **Passed**: 120 tests (100%)
- **Failed**: 0
- **Total Assertions**: 696 assertions
- **Execution Time**: ~1.99 detik

### B. Mobile Flutter
- **`flutter analyze`**: No issues found (Clean, 0 errors, 0 warnings).
- **Unit & Widget Tests**: 7/7 test groups passed on `booking_module_test.dart`, 22/22 total widget test suites passed.
- **Mock Environment**: Menjalankan isolasi penuh menggunakan `FakeBookingRepository` tanpa bergantung pada server langsung saat pipeline widget testing.
