# DOKUMEN BASELINE KESIAPAN OPERASIONAL SIM KOS & GUESTHOUSE
**Fase:** Phase 9 — Production Readiness, Security & Operational Hardening  
**Tanggal Audit:** 17 Oktober 2026  
**Status Evaluasi:** Komprehensif (Backend Laravel 13 & Mobile Flutter)  

---

## 1. Arsitektur dan Komponen Backend (Laravel 13)

### 1.1 Struktur Modul & Pola Desain
Backend dibangun menggunakan **Laravel 13** dengan arsitektur modular berlapis:
- **Presentation Layer (Web):** Laravel Blade + Bootstrap 5 dengan navigasi *Collapsible Left Sidebar* dan *Right-Side Action Drawer* untuk operasi cepat (check-in, verifikasi bayar, entri meter, triase komplain).
- **Presentation Layer (API):** RESTful JSON API (`/api/v1/`) dilindungi oleh Laravel Sanctum Token Authentication.
- **Service Layer Pattern:** Logika bisnis dipusatkan dalam service terisolasi:
  - `App\Services\Booking\BookingService`: Validasi ketersediaan kamar, kalkulasi tarif sewa, penanganan DP (down payment), dan pembuatan invoice otomatis.
  - `App\Services\Payment\PaymentService`: Alur verifikasi pembayaran manual, invoice matching, aktivasi booking, dan pemotongan saldo.
  - `App\Services\Deposit\DepositService`: Subledger uang jaminan, mutasi deposit (masuk/keluar/potong), dan settlement akhir sewa (*check-out*).
  - `App\Services\Finance\ExpenseService`: Pengajuan pengeluaran, persetujuan bertingkat (*approval workflow*), dan pencatatan pembayaran biaya operasional.
  - `App\Services\Finance\FinancialReportService`: Laporan arus kas, laba rugi operasional kas, piutang, dan ringkasan eksekutif.
  - `App\Services\Accounting\ChartOfAccountService`: Pengelolaan bagan akun hierarkis, mapping sistem otomatis, dan inisialisasi akun default properti.
  - `App\Services\Accounting\JournalPostingService`: General Journal engine (*double-entry bookkeeping*), validasi keseimbangan Debit = Kredit atomik, dan proteksi idempotensi via `idempotency_key`.
  - `App\Services\Accounting\AccountingReportService`: Neraca Saldo (*Trial Balance*), Laporan Laba Rugi Akrual (*Income Statement*), Neraca Keuangan (*Balance Sheet*), dan Rekonsiliasi Kas/Bank.
  - `App\Services\Utility\UtilityService`: Pencatatan meteran listrik/air, pencegahan pembacaan mundur (*backwards reading*), dan pembuatan invoice utilitas.
  - `App\Services\Inspection\RoomInspectionService`: Checklist inspeksi kamar *check-in* dan *check-out*, dokumentasi fisik, dan deteksi kerusakan.
  - `App\Services\Maintenance\MaintenanceService`: Tiket perbaikan aset/fasilitas, penugasan staf, dan verifikasi penyelesaian.
  - `App\Services\Housekeeping\HousekeepingService`: Penjadwalan pembersihan kamar, inventaris linen, dan verifikasi kebersihan.
  - `App\Services\Complaint\ComplaintService`: Pengaduan penyewa, triase, eskalasi, dan histori komentar dengan foto bukti.

### 1.2 Routing & Middleware Stack
Konfigurasi middleware didefinisikan pada `bootstrap/app.php`:
- **Web Routes (`routes/web.php`):**
  - Menggunakan group `web` dengan CSRF protection (`VerifyCsrfToken`), enkripsi cookies, session stateful, dan auth middleware (`auth`).
  - Prefix rute dashboard: `/dashboard/*` diproteksi autentikasi web.
- **API Routes (`routes/api.php`):**
  - Menggunakan group `api` dengan stateless handling.
  - Throttling publik aktif: `throttle:10,1` pada login/registrasi, `throttle:15,1` pada *public guest booking* dan pelacakan token.
  - Endpoint privat diproteksi middleware `auth:sanctum`.
- **Role Enforcement:** Middleware `EnsureRole` (`app/Http/Middleware/EnsureRole.php`) dan helper method model `User` (`isSuperAdmin()`, `isOwner()`, `isManager()`, `isReceptionist()`, `isStaff()`, `isTenant()`) digunakan di seluruh controller.

### 1.3 Database & Penyimpanan Data
- **Driver Development/Testing:** SQLite 3 (in-memory dan file-based isolated).
- **Target Database Staging/Production:** MySQL 8.0+ atau PostgreSQL 15+.
- **Database Migrations:** 24 berkas migrasi terstruktur tanpa operasi destruktif (`migrate:fresh` dilarang).
- **Sistem Penyimpanan File (Storage):**
  - Disk `local` dikonfigurasi ke direktori `storage/app/private`.
  - Semua dokumen sensitif (foto identitas, bukti bayar kuitansi, foto inspeksi kamar, foto meteran, berkas perbaikan) disimpan pada disk `local` privat dan **TIDAK PERNAH** diekspos melalui symlink publik `public/storage`.
  - Akses fisik berkas dilayani oleh controller dengan validasi otorisasi dan response header `Cache-Control: private, no-cache, no-store, must-revalidate`.

---

## 2. Arsitektur dan Komponen Mobile (Flutter)

### 2.1 Pola Desain & State Management
- **Framework:** Flutter (Android & iOS).
- **Arsitektur:** Feature-first modular pattern:
  - `features/auth`: Otentikasi, penyimpanan token aman, pergantian role.
  - `features/properties`: Daftar properti, tipe kamar, ketersediaan unit.
  - `features/booking`: Pemesanan kamar, kalkulasi tarif sewa, pelacakan status.
  - `features/payments`: Konfirmasi pembayaran, unggah bukti bayar.
  - `features/deposit`: Informasi saldo jaminan, mutasi deposit, penyelesaian check-out.
  - `features/finance`: Dashboard keuangan eksekutif, analitik biaya, modul akuntansi (COA, jurnal, laporan keuangan).
  - `features/utilities`: Pencatatan meteran mandiri dan riwayat tagihan.
  - `features/inspections`: Checklist kondisi kamar masuk dan keluar.
  - `features/maintenance`: Tiket perawatan fasilitas.
  - `features/housekeeping`: Tugas kebersihan dan checklist sanitasi.
  - `features/complaints`: Pengaduan kendala dan interaksi petugas.
- **State Management:** **Riverpod** (`flutter_riverpod`) dengan pemisahan immutable state, notifier, dan UI bindings.
- **Networking:** **Dio** HTTP client dengan interceptor logging terpusat, injection header token Sanctum (`Authorization: Bearer <token>`), dan penanganan response format seragam (`success`, `data`, `message`).
- **Routing:** **GoRouter** dengan guard redirect autentikasi dan penanganan rute tidak ditemukan.
- **Penyimpanan Token:** Penyimpanan kredensial token terisolasi lokal.
- **Kondisi Codebase Mobile:** `flutter analyze` 0 issues (bersih), 53 unit & widget tests lulus 100%.

---

## 3. Matriks Peran (Roles) & Izin (Permissions) Aktual

| Role | Hak Akses Properti | Akses Operasional Kamar | Akses Uang Jaminan & Settlement | Akses Pengeluaran (Expense) | Akses Akuntansi (COA & Jurnal) | Akses Laporan Keuangan |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Super Admin** | Semua Properti Global | Penuh (CRUD) | Penuh (CRUD + Finalisasi) | Penuh (Approve, Pay, Void) | Penuh (Kelola COA, Reverse Jurnal) | Penuh (Semua Laporan & Rekonsiliasi) |
| **Owner** | Properti Ditugaskan | Pantau & Kelola Unit | Pantau & Setujui Settlement | Approve & Bayar Pengeluaran | Baca & Analisis Jurnal/COA | Penuh untuk Properti Miliknya |
| **Manager** | Properti Ditugaskan | Penuh di Properti | Buat Settlement & Ajukan Refund | Ajukan & Setujui Biaya Operasional | Baca/Entri Jurnal Properti | Penuh untuk Properti Terkait |
| **Receptionist / Front Desk** | Properti Ditugaskan | Booking, Check-In, Check-Out | Terima Deposit & Input Klaim | Input Pengeluaran Kas Kecil | Terbatas (Hanya Pencatatan Otomatis) | Tidak Diizinkan |
| **Staff / Maintenance** | Properti Ditugaskan | Update Status Unit, Tugas Harian | Input Kerusakan Saat Inspeksi | Input Pengeluaran Material | Dilarang | Dilarang |
| **Tenant (Penyewa)** | Unit Kamar Sendiri | Hanya Lihat Booking Sendiri | Lihat Saldo Jaminan Sendiri | Dilarang Keras (403) | Dilarang Keras (403) | Dilarang Keras (403) |

---

## 4. Evaluasi Isolasi Multi-Property & Tenant Isolation

### 4.1 Isolasi Tenant
- Setiap query pemesanan, faktur, tagihan utilitas, komplain, dan mutasi uang jaminan pada API tenant difilter berdasarkan `user_id = Auth::id()`.
- Upaya tenant mengakses detail data atau berkas penyewa lain secara eksplisit diblokir dan menghasilkan HTTP `403 Forbidden` atau `404 Not Found`.
- Tenant diblokir total dari seluruh modul finansial internal: Chart of Accounts, General Journal, Buku Kas, Pengeluaran Operasional, dan Rekonsiliasi Bank.

### 4.2 Isolasi Multi-Property (Staff / Manager / Owner)
- Hubungan user staf/manajer dengan properti dikelola melalui tabel pivot `property_users`.
- Controller memvalidasi `user->properties()->where('properties.id', $propertyId)->exists()` pada setiap operasi spesifik properti.
- Staf pada Properti A tidak dapat membaca, mengunduh bukti kuitansi, atau memanipulasi pengeluaran, inspeksi, maupun jurnal Properti B.

---

## 5. Ringkasan Status Baseline Komponen

| Komponen | Status Implementasi | Otorisasi & Isolasi | Tingkat Kesiapan | Catatan Operasional |
| :--- | :---: | :---: | :---: | :--- |
| **Booking & Ketersediaan** | Lengkap | Aman (Server-side rate) | Siap Staging | Menolak tanggal bertabrakan; DP dan kalkulasi sewa dihitung server. |
| **Invoicing & Payments** | Lengkap | Aman (Verifikasi manual) | Siap Staging | Upload bukti bayar tersimpan di private storage. |
| **Security Deposit (Uang Jaminan)** | Lengkap | Aman (Subledger terisolasi) | Siap Staging | Mutasi masuk, potong, refund; saldo jaminan tidak pernah negatif. |
| **Check-Out Settlement** | Lengkap | Aman (Validasi ganda) | Siap Staging | Menghitung denda/kerusakan fisik dan membatasi refund <= saldo. |
| **Pengeluaran (Expenses)** | Lengkap | Aman (Private cache header) | Siap Staging | Header `Cache-Control: private, no-cache` diterapkan pada berkas kuitansi. |
| **Akuntansi & Jurnal Umum** | Lengkap | Aman (Role guard ketat) | Siap Staging | Jurnal berpasangan atomik; menolak entri tidak seimbang; idempotensi aktif. |
| **Laporan Finansial & Rekonsiliasi** | Lengkap | Aman (Owner/Manager only) | Siap Staging | Neraca saldo, laba rugi, neraca keuangan, dan audit rekonsiliasi. |
| **Inspeksi, Komplain, Perawatan** | Lengkap | Aman (Photo streaming aman) | Siap Staging | Path traversal dicegah; foto privat dialirkan dengan otorisasi. |
| **Mobile Flutter Suite** | Lengkap | Teruji (0 analyze error, 53 tests) | Siap Staging | Endpoint tersinkronisasi penuh dengan REST API Laravel. |
