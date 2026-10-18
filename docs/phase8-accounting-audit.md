# SIM KOS & GUESTHOUSE — PHASE 8 FINAL ACCOUNTING INTEGRITY AUDIT REPORT
**Double-Entry Journal Engine, Chart of Accounts, Subledger Reconciliation & Financial Reporting**

**Tanggal Audit**: 17 Oktober 2026  
**Status Audit**: AUDITED, ENHANCED & VERIFIED (Lolos 100% Test Suite)  
**Branch**: `feature/booking-end-to-end`  
**HEAD Commit**: `0aa84952cd0e4f26be1d7ca407ee9ab19eb1905e`

---

## 1. STATUS WORKING TREE & PROTEKSI REPOSITORY

### 1.1. Status Awal & Akhir Working Tree
- **Cabang Git**: `feature/booking-end-to-end`
- **HEAD Commit**: `0aa84952cd0e4f26be1d7ca407ee9ab19eb1905e`
- **Working Tree**: **TIDAK BERSIH (Mempertahankan seluruh pekerjaan lokal Phase 1–8)**.
- **Kepatuhan Proteksi**:
  - `git commit`: TIDAK DILAKUKAN (0 commit baru dibuat).
  - `git push` / `git remote`: TIDAK DILAKUKAN.
  - `git reset` / `git clean` / `git checkout --` / `git stash`: TIDAK DILAKUKAN.
  - `migrate:fresh` / `db:wipe` / truncate: TIDAK DILAKUKAN pada basis data produksi/utama.
- Seluruh 30 file modifikasi lokal tracked dan 45 entri file/direktori untracked (`??`) dari modul Booking End-to-End, Drawer Offcanvas, Phase 6 Finance, Phase 7 Deposit, dan Phase 8 Accounting dipertahankan secara utuh.

---

## 2. TEMUAN AUDIT BERDASARKAN TINGKAT RISIKO

| # | Temuan Audit | Tingkat Risiko | Dampak Sebelum Perbaikan | Tindakan Perbaikan Terarah |
|---|---|---|---|---|
| **T-01** | **Inkonsistensi Dokumentasi Kode Akun 4102 vs 4104** | **SEDANG** *(Dokumentasi)* | Tabel ringkasan awal mencatat `4102` sebagai Pendapatan Denda, padahal kode aktual mengalokasikan `4102` untuk Sewa Guesthouse dan `4104` untuk Denda Kerusakan. | Dokumentasi diselaraskan dengan arsitektur kode. Menambahkan tes regresi pembuktian bahwa transaksi Guesthouse (`4102`), Kos (`4101`), dan Denda (`4104`) dicatat terpisah. |
| **T-02** | **Pembayaran Utilitas yang Digabung dalam Invoice Sewa** | **TINGGI** *(Integritas GL)* | Ketika biaya listrik/air ditambahkan ke invoice sewa, verifikasi pembayaran sebelumnya mengkredit seluruh nominal ke `4101` (Sewa Kos) sehingga pendapatan utilitas tidak terklasifikasi ke `4103`. | `JournalPostingService::postPaymentJournal` disempurnakan untuk memeriksa `InvoiceItem` bertipe utilitas (`electricity`, `water`, `utility`), secara otomatis memecah nominal ke `4103` (Utilitas) dan sisanya ke `4101` (Sewa) dengan dua jurnal berimbang dan kunci idempotensi terpisah. |
| **T-03** | **Denda Kerusakan Check-Out Melebihi Saldo Deposit** | **SEDANG** *(Aset/Piutang)* | Jika denda check-out melebihi deposit (misal denda Rp 800.000, deposit Rp 500.000), selisih lebih (Rp 300.000) belum dicatat sebagai piutang denda ke mantan penyewa. | Menambahkan posting otomatis ke akun `1203` (Piutang Denda & Ganti Rugi) sisi debit dan `4104` (Pendapatan Denda) sisi kredit untuk nilai selisih lebih dengan kunci idempotensi `settlement:{id}:excess_damage`. |
| **T-04** | **Proteksi Anti-Penghapusan Jurnal Posted pada Model Eloquent** | **TINGGI** *(Kepatuhan Audit)* | Jurnal posted sebelumnya diproteksi di level Controller/Service, namun belum memiliki proteksi event `deleting` dan `updating` pada model `JournalEntry`. | Menambahkan hook `JournalEntry::booted()` yang secara tegas melempar `DomainException` jika ada pemanggilan `$journal->delete()` atau perubahan field selain `status`, `notes`, dan `reversed_journal_id`. |
| **T-05** | **Verifikasi Concurrency Multi-Thread / High Concurrency** | **RENDAH** *(Lingkungan Pengujian)* | Pengujian lokal menggunakan driver SQLite yang bekerja dalam mode single-writer. | Concurrency row lock (`lockForUpdate()`) dan DB-level unique constraint (`idempotency_key`) aktif di skema, namun konkurensi produksi di bawah load multi-worker MySQL/PostgreSQL dicatat untuk diverifikasi pada staging. |

---

## 3. ARSITEKTUR CHART OF ACCOUNTS & PEMETAAN TRANSAKSI AKTUAL

### 3.1. Bagan Akun Standar (COA) per Properti
Terdapat 17 akun standar dengan isolasi `property_id` penuh:

| Kode Akun | Nama Akun | Klasifikasi | Saldo Normal | Kategori | Fungsi Operasional |
|---|---|---|---|---|---|
| **1101** | Kas Tunai Properti | Aset Lancar | Debit | `cash_bank` | Penerimaan / pengeluaran fisik tunai di lokasi |
| **1102** | Rekening Bank Operasional | Aset Lancar | Debit | `cash_bank` | Rekening bank transfer utama operasional |
| **1103** | Kas Titipan Uang Jaminan | Aset Lancar | Debit | `cash_bank` | Penampungan dana jaminan penyewa |
| **1201** | Piutang Sewa Kamar | Aset Lancar | Debit | `receivable` | Tagihan sewa tertunggak |
| **1202** | Piutang Utilitas Listrik & Air | Aset Lancar | Debit | `receivable` | Tagihan meteran tertunggak |
| **1203** | Piutang Denda & Ganti Rugi | Aset Lancar | Debit | `receivable` | Denda kerusakan melebihi saldo deposit |
| **2101** | Titipan Uang Jaminan (Deposit) | Kewajiban Lancar | Kredit | `current_liability` | Titipan jaminan sewa (Bukan pendapatan!) |
| **2102** | Pendapatan Sewa Diterima di Muka | Kewajiban Lancar | Kredit | `current_liability` | Sewa / DP periode masa depan |
| **2201** | Utang Biaya Operasional (AP) | Kewajiban Lancar | Kredit | `payable` | Tagihan vendor operasional belum bayar |
| **3101** | Modal Pemilik Properti | Ekuitas | Kredit | `equity` | Investasi modal pemilik |
| **3201** | Saldo Laba Ditahan | Ekuitas | Kredit | `equity` | Akumulasi laba periode lalu |
| **4101** | Pendapatan Sewa Kamar Kos | Pendapatan | Kredit | `operating_revenue` | Sewa kos bulanan berjalan |
| **4102** | Pendapatan Sewa Guesthouse | Pendapatan | Kredit | `operating_revenue` | Sewa guesthouse harian |
| **4103** | Pendapatan Utilitas | Pendapatan | Kredit | `operating_revenue` | Pendapatan listrik & air |
| **4104** | Pendapatan Denda & Ganti Rugi | Pendapatan | Kredit | `operating_revenue` | Denda kerusakan & keterlambatan |
| **4105** | Pendapatan Operasional Lainnya | Pendapatan | Kredit | `operating_revenue` | Layanan tambahan (parkir, laundry) |
| **5101**–**5106** | Beban Operasional (5101: Listrik/Air, 5102: Wifi, 5103: Maintenance, 5104: Kebersihan, 5105: Gaji, 5106: Lainnya) | Beban | Debit | `operating_expense` | Beban operasional bulanan properti |

---

## 4. MATRIKS TRANSAKSI & JURNAL OPERASIONAL AKTUAL

Berikut adalah matriks jurnal berpasangan double-entry aktual pada sistem:

| Skenario Transaksi | Trigger Posting | Sisi Debit (Kiri) | Sisi Kredit (Kanan) | Waktu Pengakuan | Idempotency Key |
|---|---|---|---|---|---|
| **Pembuatan Invoice Sewa** | Terbit tagihan | *Tidak ada jurnal* (Cash Basis) | *Tidak ada jurnal* | Belum hak pendapatan | - |
| **Pembayaran Sewa Bulanan Berjalan** | `PaymentService::verifyPayment` | `1101 Kas` / `1102 Bank` | `4101 Pendapatan Sewa Kamar Kos` | Saat diverifikasi sah | `payment:{id}:rent` |
| **Pembayaran Sewa Guesthouse (Harian)** | `PaymentService::verifyPayment` | `1101 Kas` / `1102 Bank` | `4102 Pendapatan Sewa Guesthouse` | Saat diverifikasi sah | `payment:{id}:rent` |
| **Pembayaran Sewa Diterima di Muka (DP / Periode Depan)** | `PaymentService::verifyPayment` | `1101 Kas` / `1102 Bank` | `2102 Sewa Diterima Dimuka` | Saat diverifikasi sah | `payment:{id}:advance_rent` |
| **Penerimaan Uang Jaminan (Deposit)** | `PaymentService::verifyPayment` / `recordDepositReceived` | `1101 Kas` / `1102 Bank` | `2101 Titipan Uang Jaminan` *(Liability)* | Saat dana masuk | `payment:{id}:deposit` |
| **Pembayaran Tagihan Utilitas Murni** | `PaymentService::verifyPayment` | `1101 Kas` / `1102 Bank` | `4103 Pendapatan Utilitas` | Saat diverifikasi sah | `payment:{id}:utility` |
| **Pembayaran Invoice Sewa + Utilitas Tergabung** | `PaymentService::verifyPayment` | `1101 Kas` / `1102 Bank` (Total) | `4103 Utilitas` (Porsi Listrik/Air)<br>`4101 Sewa Kos` (Porsi Sewa) | Saat diverifikasi sah | `payment:{id}:utility`<br>`payment:{id}:rent` |
| **Pencairan Beban Operasional (Penuh / Parsial)** | `ExpenseService::recordPayment` | `5101`–`5106` Akun Beban Terkait | `1101 Kas` / `1102 Bank` | Saat dana dicairkan | `expense_payment:{id}` |
| **Settlement Check-Out: Potongan Kerusakan dari Deposit** | `DepositService::finalizeCheckOutAndRefund` | `2101 Titipan Uang Jaminan` | `4104 Pendapatan Denda & Ganti Rugi` | Saat check-out sah | `settlement:{id}:deduction` |
| **Settlement Check-Out: Denda Melebihi Saldo Deposit** | `DepositService::finalizeCheckOutAndRefund` | `1203 Piutang Denda & Ganti Rugi` | `4104 Pendapatan Denda & Ganti Rugi` | Saat check-out sah | `settlement:{id}:excess_damage` |
| **Settlement Check-Out: Refund Sisa Deposit ke Penyewa** | `DepositService::finalizeCheckOutAndRefund` | `2101 Titipan Uang Jaminan` | `1101 Kas` / `1102 Bank` | Saat refund ditransfer | `settlement:{id}:refund` |
| **Pembalikan Jurnal (Reversal)** | `JournalPostingService::reverseJournal` | Membalik posisi Kredit jurnal asal | Membalik posisi Debit jurnal asal | Saat diajukan user berwenang | `reversal:{journal_id}` |

---

## 5. HASIL AUDIT BACKFILL HISTORIS

Simulasi backfill non-destruktif dijalankan via command Artisan:
`php artisan finance:backfill-journals --dry-run`

### Ringkasan Eksekusi Aktual:
```
=========================================================
 SIM KOS & GUESTHOUSE — BACKFILL JURNAL HISTORIS (PHASE 8)
=========================================================
 [MODE SIMULASI / DRY-RUN AKTIF]
 Tidak ada data jurnal yang akan disimpan ke basis data.
 Properti Target: Semua Properti Terdaftar

1. Memproses Pembayaran Terverifikasi (Verified Payments)...
2. Memproses Pencairan Beban Operasional (Expense Payments)...
3. Memproses Penyelesaian Check-Out (Settlements)...
4. Memproses Mutasi Pengembalian Deposit Tunggal (Deposit Refunds)...

--- RINGKASAN EKSEKUSI BACKFILL JURNAL ---
+--------------------------------------+----------+----------------+---------------------+--------------------+
| Modul Transaksi                      | Kandidat | Siap Diposting | Sudah Ada (Skipped) | Gagal / Peringatan |
+--------------------------------------+----------+----------------+---------------------+--------------------+
| Penerimaan Pembayaran (Payments)     | 0        | 0              | 0                   | 0                  |
| Pencairan Beban (Expenses)           | 1        | 1              | 0                   | 0                  |
| Penyelesaian Check-Out (Settlements) | 0        | 0              | 0                   | 0                  |
| Pengembalian Deposit (Refunds)       | 0        | 0              | 0                   | 0                  |
+--------------------------------------+----------+----------------+---------------------+--------------------+

Simulasi selesai: 1 transaksi siap diposting ke jurnal.
```

### Hasil Verifikasi Idempotensi & Integritas:
- **Transaksi Sumber Tidak Diubah**: Status pembayaran, nominal, dan verifikasi pada tabel operasional tetap utuh 100%.
- **Uji Idempotensi**: Telah dibuktikan via unit test `test_backfill_artisan_command_is_idempotent_and_supports_dry_run`:
  - Eksekusi pertama memposting data kandidat.
  - Eksekusi kedua melewati seluruh data yang sudah ada (0 jurnal duplikat).

---

## 6. AUDIT LAPORAN KEUANGAN & REKONSILIASI SUBLEDGER

1. **Trial Balance (Neraca Saldo)**:
   - Terbukti seimbang (`total_debit == total_credit`) pada seluruh properti.
   - Mengelompokkan saldo akhir akun sesuai Saldo Normal (Debit untuk Aset & Beban; Kredit untuk Kewajiban, Ekuitas, Pendapatan).
2. **Income Statement (Laba Rugi)**:
   - Menghitung `Laba Bersih = Total Pendapatan Operasional - Total Beban Operasional`.
   - Mengisolasi pendapatan kos (`4101`), guesthouse (`4102`), utilitas (`4103`), dan denda (`4104`).
3. **Balance Sheet (Neraca Keuangan)**:
   - Persamaan akuntansi terverifikasi presisi: `Total Aset = Total Kewajiban + Total Ekuitas`.
   - Laba Bersih Tahun Berjalan dihitung kumulatif s/d tanggal laporan dan dimasukkan ke baris ekuitas tanpa duplikasi.
   - Uang jaminan penyewa (`2101`) tetap berstatus Kewajiban sampai ada penyelesaian check-out sah.
4. **Independensi Rekonsiliasi Subledger**:
   - **Arus Kas Masuk**: Membandingkan tabel operasional `payments` (status `verified`) terhadap baris jurnal kas/bank berkategori `cash_bank` dari sumber `payment`.
   - **Arus Kas Keluar**: Membandingkan tabel operasional `expense_payments` terhadap baris jurnal kas/bank dari sumber `expense_payment`.
   - **Uang Jaminan**: Membandingkan saldo subledger `deposit_ledgers` terhadap saldo akumulasi kredit dikurangi debit akun `2101`.
   - Kedua sisi rekonsiliasi berasal dari tabel yang berbeda secara independen.

---

## 7. BUKTI KELULUSAN TEST SUITE

### 7.1. Backend PHPUnit Suite (`php artisan test`)
File test: `backend/tests/Feature/AccountingAndJournalEngineTest.php`  
Hasil eksekusi: **19 PASSED, 109 ASSERTIONS (100% Lulus)**

```
Accounting And Journal Engine (Tests\Feature\AccountingAndJournalEngine)
 ✔ Default coa seeded and property isolated
 ✔ Journal posting requires balanced debit credit
 ✔ Journal line cannot have both positive debit and credit
 ✔ Journal posting is strictly idempotent
 ✔ Rental payment posts to revenue for current period
 ✔ Advance rental payment posts to unearned revenue
 ✔ Deposit payment strictly credited to deposit liability not revenue
 ✔ Expense payment debits appropriate expense category and credits cash
 ✔ Checkout settlement journal records damage revenue and refund cash out
 ✔ Journal reversal creates compensating balanced entry
 ✔ Trial balance is balanced
 ✔ Income statement and balance sheet
 ✔ Reconciliation report checks match
 ✔ Backfill artisan command is idempotent and supports dry run
 ✔ Tenant is forbidden from accounting api and web
 ✔ Guesthouse daily rental posts to 4102 distinct from monthly kos and damage
 ✔ Bundled utility invoice splits rent to 4101 and utility to 4103
 ✔ Damage deduction exceeding deposit posts excess to receivable 1203
 ✔ Posted journal cannot be deleted or mutated

OK (19 tests, 109 assertions)
```

**Hasil Keseluruhan Suite Backend**:
```
Tests:    157 passed (910 assertions)
Duration: 3.33s
Status:   100% PASSED (0 failures, 0 errors)
```

### 7.2. Mobile Flutter Suite (`flutter analyze` & `flutter test`)
- **Analisis Statis**:
  ```bash
  $ flutter analyze
  Analyzing mobile...
  No issues found! (ran in 1.8s)
  ```
- **Eksekusi Pengujian Mobile**:
  File test: `mobile/test/phase8_accounting_modules_test.dart` + seluruh test suite mobile:
  ```bash
  $ flutter test
  00:05 +53: All tests passed!
  ```
  Total 53 pengujian unit dan widget mobile lulus 100%.

---

## 8. RISIKO TERSISA & TINDAKAN YANG MEMERLUKAN PERSETUJUAN PENGGUNA

### 8.1. Risiko yang Masih Tersisa
1. **Concurrency Skala Tinggi pada Driver MySQL / PostgreSQL**:
   Meskipun database-level unique constraint dan row lock `lockForUpdate()` telah diimplementasikan, verifikasi penanganan *race condition* multi-worker simultan pada RDBMS produksi (MySQL 8 / PostgreSQL 15) perlu diaudit di lingkungan staging dengan load test concurrency sebelum go-live.

### 8.2. Tindakan yang Memerlukan Persetujuan Pengguna (User Approval)
1. **Eksekusi Nyata Backfill Jurnal Historis Produksi**:
   Command `php artisan finance:backfill-journals` saat ini hanya dijalankan dalam mode `--dry-run`. Eksekusi permanen (tanpa `--dry-run`) pada database produksi **memerlukan persetujuan pengguna** dan harus didahului dengan backup database penuh.
2. **Setup Saldo Awal Kas / Bank (Opening Balance)**:
   Sebelum tanggal efektif *cut-off* sistem, pemilik/manajer perlu memasukkan saldo awal kas dan modal via jurnal manual pembuka (Opening Journal) agar neraca mencerminkan posisi fisik rekening riil.

---
**Kesimpulan Audit**: Phase 8 telah memenuhi seluruh standar integritas pembukuan berpasangan double-entry, pemisahan akun guesthouse, penanganan tagihan utilitas gabungan, proteksi denda melebihi deposit, dan immutabilitas audit trail. Modul dinyatakan **SIAP & LOLOS AUDIT FINAL**.
