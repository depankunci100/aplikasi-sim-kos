# LAPORAN AUDIT KEAMANAN DAN INTEGRITAS TRANSAKSI
**Fase:** Phase 9 — Security Hardening, Concurrency & Operational Validation  
**Tanggal Audit:** 17 Oktober 2026  
**Status Evaluasi:** Selesai & Divalidasi dengan Regression Test Suite  

---

## 1. Ringkasan Eksekutif

Audit keamanan Phase 9 bertujuan untuk memverifikasi ketahanan aplikasi SIM Kos & Guesthouse terhadap serangan web/API umum, kebocoran data antar-tenant (*tenant data leakage*), pelanggaran batas antar-properti (*multi-property boundary breach*), pemalsuan nominal keuangan (*mass assignment tampering*), dan kegagalan integritas transaksi saat beban konkurensi tinggi.

### Ringkasan Status Temuan
| Kategori | Tingkat Risiko | Jumlah Temuan | Status Perbaikan |
| :--- | :---: | :---: | :--- |
| **Private File Caching Header Gap** | Sedang (Medium) | 2 | **Telah Diperbaiki** (Header private cache diterapkan pada kuitansi pengeluaran) |
| **Akses Modul Akuntansi oleh Penyewa** | Tinggi (High) | 0 | **Aman** (Diblokir dengan 403 Forbidden di level API dan Web) |
| **Isolasi Multi-Property Staf & Manajer** | Tinggi (High) | 0 | **Aman** (Validasi relasi properti aktif pada seluruh endpoint privat) |
| **Mass Assignment Nominal & Status Booking** | Kritis (Critical) | 0 | **Aman** (Kalkulasi tarif dan status awal ditentukan penuh oleh server) |
| **Kebocoran Secret / Kredensial di Git** | Kritis (Critical) | 0 | **Aman** (Tidak ada secret atau berkas `.env` yang terlacak di Git) |
| **Keseimbangan Debit & Kredit Jurnal** | Kritis (Critical) | 0 | **Aman** (Validasi atomik `postJournal` mewajibkan Debit == Kredit) |
| **Idempotensi Transaksi Jurnal** | Tinggi (High) | 0 | **Aman** (Proteksi `idempotency_key` mencegah duplikasi jurnal berulang) |
| **Batas Saldo & Refund Deposit** | Kritis (Critical) | 0 | **Aman** (Saldo jaminan tidak bisa negatif; refund dibatasi <= saldo) |

---

## 2. Audit Autentikasi dan Otorisasi (AuthN & AuthZ)

### 2.1 Isolasi Tenant (Penyewa)
- **Modul Finansial & Akuntansi:** Penyewa dilarang keras mengakses endpoint `/api/v1/finance/accounting/*`, `/dashboard/accounting/*`, dan `/api/v1/expenses/*`. Permintaan dari penyewa ditolak langsung dengan status `403 Forbidden`.
- **Modul Pemesanan & Tagihan:** Penyewa hanya dapat melihat daftar pemesanan (`/api/v1/bookings`) dan faktur miliknya sendiri (`user_id = Auth::id()`). Percobaan mengakses booking pengguna lain mengembalikan respons `403` atau `404`.
- **Subledger Uang Jaminan:** Tenant hanya dapat memantau deposit dan penyelesaian check-out terkait kamar yang disewanya.

### 2.2 Isolasi Multi-Property (Staf & Manajer)
- Staf dan Manajer ditugaskan ke properti tertentu melalui tabel pivot `property_users`.
- Controller memvalidasi wewenang properti sebelum menyajikan data atau berkas privat. Contoh: Staf Properti A yang meminta kuitansi pengeluaran milik Properti B (`/api/v1/expenses/{id}/receipt` atau `/dashboard/expenses/{id}/receipt`) ditolak secara konsisten dengan `403 Forbidden`.
- Super Admin memiliki hak lintas properti untuk keperluan pemeliharaan sistem global.

---

## 3. Validasi Input, Mass Assignment & Sanitasi Data

### 3.1 Pencegahan Mass Assignment Pemesanan Kamar
- Pada endpoint pembuatan pemesanan (`POST /api/v1/bookings`):
  - Klien jahat yang mengirimkan muatan berisi `rate_amount`, `deposit_amount`, `total_amount`, atau `status: confirmed` tidak dapat memanipulasi data di database.
  - Server mengabaikan nilai-nilai input tersebut dan secara independen mengambil tarif dasar dari model `RoomType` di database serta menetapkan status awal secara terprogram (`pending` / `pending_payment`).
  - Telah diverifikasi melalui unit test `Phase9SecurityAndOperationalHardeningTest::test_booking_creation_ignores_client_forged_rates_and_statuses`.

### 3.2 Proteksi CSRF & Rate Limiting
- **CSRF Web:** Seluruh form berbasis Blade pada dashboard web diproteksi oleh token `@csrf` dan middleware `VerifyCsrfToken`.
- **API Rate Limiting:**
  - Login dan Registrasi dibatasi `throttle:10,1` (maksimal 10 request per menit per IP) untuk mencegah *brute-force credential stuffing*.
  - Pemesanan tamu publik dan pelacakan token dibatasi `throttle:15,1` untuk mencegah *resource exhaustion* dan *room reservation denial of service*.

### 3.3 Penanganan Respons & Pencegahan Kebocoran Error
- Respons API standar menggunakan struktur seragam (`success`, `data`, `message`).
- Exception `DomainException` ditangkap dan disajikan sebagai pesan bisnis yang ramah (`422 Unprocessable Content`).
- Pada lingkungan produksi, `APP_DEBUG=false` wajib diaktifkan guna memastikan detail stack trace, kredensial database, atau query SQL tidak pernah bocor ke pengguna akhir saat terjadi kegagalan sistem.

---

## 4. Pengelolaan dan Keamanan Berkas Privat (Private Files)

### 4.1 Lokasi Penyimpanan & Akses Fisik
- Seluruh dokumen sensitif disimpan pada disk privat: `storage/app/private/`.
- Berkas meliputi:
  - Bukti transfer pembayaran penyewa (`payments/proofs/`).
  - Kuitansi pengeluaran operasional dan bukti pembayaran vendor (`expenses/receipts/`, `expenses/proofs/`).
  - Foto dokumentasi kondisi kamar pra dan pasca sewa (`inspections/`).
  - Foto meteran utilitas listrik dan air (`utility_meters/`).
  - Foto kerusakan fasilitas dan tiket perbaikan (`maintenance/`, `complaints/`).
- **Tidak ada symlink publik** yang menghubungkan folder berkas privat ini dengan folder web root `public/`.

### 4.2 Sanitasi Berkas & Pencegahan Path Traversal
- Berkas diunggah dengan validasi MIME type ketat (`jpeg, jpg, png, webp, pdf`) dan ukuran maksimal 5MB.
- Nama berkas asli dari pengguna dienkripsi menjadi hash unik acak (`Str::random(40)`) oleh disk storage bawaan Laravel, meniadakan kemungkinan *path traversal* (`../`) atau *file overwrite*.

### 4.3 Perbaikan Cache Header HTTP (Mitigasi Celah)
- **Temuan Awal:** Controller `ExpenseApiController::receipt`, `ExpenseWebController::receipt`, dan `ExpenseWebController::paymentProof` awalnya menyajikan berkas melalui `response()->file()` tanpa mematikan cache publik, berpotensi menyebabkan reverse-proxy atau cache perantara menyimpan dokumen sensitif.
- **Tindakan Perbaikan:** Controller diperbarui menggunakan pola respons privat Symfony:
  ```php
  $fileResponse = response()->file($fullPath, ['Content-Type' => $mimeType]);
  $fileResponse->setPrivate();
  $fileResponse->headers->addCacheControlDirective('no-cache', true);
  return $fileResponse;
  ```
- **Hasil:** Respons HTTP kini secara konsisten menghasilkan header `Cache-Control: must-revalidate, no-cache, private`, mencegah proksi publik menyimpan berkas kuitansi. Telah divalidasi melalui pengujian otomatis.

---

## 5. Audit Kredensial dan Secret Repository

- **Pemeriksaan Berkas Terlacak (Tracked Files):**
  - Dilakukan penelusuran git index; berkas `.env`, sertifikat SSL (`.crt`, `.key`), dan token API pribadi tidak ada yang terlacak dalam commit git maupun staging index.
  - Berkas `.gitignore` telah mengonfigurasi pengecualian untuk `.env`, `.env.backup`, `.env.production`, direktori `storage/*.key`, dan folder log.
- **Panduan Operasional Staging/Produksi:**
  - Kredensial `APP_KEY`, kata sandi database, secret JWT/Sanctum, dan kredensial email gateway hanya boleh dikonfigurasi melalui *environment variables* di server target atau *secret manager* terenkripsi (misalnya AWS Secrets Manager atau HashiCorp Vault).

---

## 6. Transaksi Finansial, Idempotensi & Integritas Konkurensi

### 6.1 Integritas Jurnal Pembukuan Berpasangan (General Journal)
- **Keseimbangan Debit = Kredit:** Service `JournalPostingService::postJournal` mengeksekusi validasi atomik bahwa `abs($totalDebit - $totalCredit) <= 0.001`. Upaya memposting jurnal yang tidak seimbang membatalkan transaksi dan melempar `DomainException`.
- **Kunci Idempotensi (`idempotency_key`):** Setiap transaksi keuangan (pembayaran sewa, pengeluaran, mutasi deposit, atau rekonsiliasi) menghasilkan *idempotency key* deterministik berbasis tanggal dan ID sumber transaksi. Permintaan ulang yang membawa kunci sama akan mengembalikan record yang sudah ada tanpa menduplikasi baris jurnal.

### 6.2 Integritas Saldo Uang Jaminan (Security Deposit Subledger)
- **Pencegahan Saldo Negatif:** Pengurangan uang jaminan untuk perbaikan kamar atau denda diverifikasi agar tidak melebihi sisa saldo deposit aktif.
- **Batas Refund Akhir:** Jumlah pengembalian dana (*refund*) saat check-out dibatasi secara ketat: `refund_amount <= deposit_balance - total_deductions`.
- **Mutasi Idempoten:** Mutasi deposit mencatat riwayat transaksi secara *append-only* untuk menjamin keterlacakan audit (*audit trail*).

### 6.3 Analisis Konkurensi: SQLite vs. MySQL / PostgreSQL
- **Lingkungan Pengujian (SQLite):**
  - SQLite menerapkan mekanisme *file-level locking* sederhana. Pengujian konkurensi di SQLite memvalidasi integritas logika aplikasi dan boundaries transaksi `DB::transaction`.
- **Lingkungan Staging & Produksi (MySQL InnoDB / PostgreSQL):**
  - Untuk lingkungan produksi dengan multi-worker dan konkurensi tinggi, aplikasi memanfaatkan *row-level locking* (`SELECT ... FOR UPDATE`) pada pembaruan status kamar, reservasi, dan subledger saldo deposit guna mencegah *race condition* (misalnya dua pemesanan pada kamar yang sama di milidetik bersamaan).

---

## 7. Bukti Validasi Pengujian Regresi

Pengujian regresi keamanan dan operasional dijalankan pada suite fitur khusus `Phase9SecurityAndOperationalHardeningTest`:
1. `test_tenant_is_forbidden_from_accounting_and_expense_endpoints`: **LULUS** (Status 403 terverifikasi).
2. `test_property_isolation_on_expense_and_private_cache_headers`: **LULUS** (Akses silang ditolak 403; header privat terverifikasi).
3. `test_booking_creation_ignores_client_forged_rates_and_statuses`: **LULUS** (Tarif server-side terverifikasi).
4. `test_journal_engine_rejects_unbalanced_entries`: **LULUS** (Entri tidak seimbang ditolak).
5. `test_journal_engine_idempotency_prevents_duplicate_entries`: **LULUS** (Idempotensi terverifikasi).

**Hasil Keseluruhan Backend:**
- Total Pengujian: **162 Tests**
- Total Asersi: **928 Assertions**
- Kegagalan: **0 Failures**
- Waktu Eksekusi: ~3.3 detik
