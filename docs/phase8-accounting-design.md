# Dokumen Desain Arsitektur Akuntansi, Double-Entry Journal & Rekonsiliasi (Phase 8)
## SIM Kos & Guesthouse

**Dokumen Versi:** 1.0  
**Tanggal:** 17 Oktober 2026  
**Status:** Disetujui untuk Implementasi  
**Target Lingkungan:** Laravel 13 (Backend API & Blade) + Flutter Android/iOS (Mobile)

---

## 1. Pendahuluan & Prinsip Desain

Fase 8 mengimplementasikan mesin pembukuan berpasangan (*double-entry journal engine*) yang terintegrasi secara otomatis dengan transaksi operasional nyata yang telah diverifikasi pada SIM Kos & Guesthouse.

### Prinsip Utama:
1. **Otomatis & Tidak Membebani Staf**: Petugas kasir/resepsionis tidak perlu memasukkan jurnal debit/kredit manual saat mencatat booking, memverifikasi sewa, atau mencatat pengeluaran. Aksi operasional yang sah secara otomatis memicu jurnal berpasangan yang seimbang.
2. **Keseimbangan Mutlak**: Setiap transaksi jurnal wajib memenuhi persamaan dasar akuntansi:
   $$\sum \text{Debit} = \sum \text{Kredit}$$
   Setiap baris jurnal dilarang memiliki nilai debit dan kredit positif secara bersamaan.
3. **Imutabilitas Jurnal Terposting**: Jurnal yang berstatus `posted` tidak boleh diedit atau dihapus. Koreksi kesalahan dilakukan melalui jurnal pembalik (*reversal*) atau jurnal penyesuaian (*adjusting journal*) berdasar jejak audit.
4. **Idempotensi Ketat pada Level Basis Data**: Setiap posting jurnal memiliki `idempotency_key` unik pada tabel `journal_entries` untuk mencegah duplikasi akibat race condition, retry HTTP, atau double submission.
5. **Pemisahan Uang Jaminan (Deposit) dari Pendapatan**: Uang jaminan penyewa diakui sebagai kewajiban (*liability*), bukan pendapatan sewa. Pemotongan deposit hanya diakui sebagai pendapatan denda setelah settlement check-out disahkan.
6. **Pengakuan Pendapatan Sewa Diterima di Muka**: Pembayaran sewa untuk periode yang belum berjalan dicatat sebagai kewajiban (*Pendapatan Diterima di Muka* / *Unearned Revenue*), dan baru diakui sebagai pendapatan pada periode sewa berjalan.
7. **Isolasi Properti Multitenant**: Setiap akun, jurnal, dan laporan keuangan terisolasi per properti dengan otorisasi berbasis peran (Owner/Manager hanya mengakses properti yang ditugaskan; Tenant dilarang mengakses modul akuntansi).

---

## 2. Struktur Bagan Akun (Chart of Accounts - COA)

COA dirancang dengan klasifikasi standar 5 digit atau 4 digit yang konsisten di semua properti. Akun berakar pada level properti (dapat memiliki konfigurasi spesifik atau mewarisi template bawaan sistem).

### Klasifikasi & Sifat Normal Akun

| Kode Akun | Nama Akun | Kategori | Tipe | Sifat Normal | Keterangan & Sumber Transaksi |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **1101** | Kas Tunai Properti | Kas & Bank | ASET | Debit | Pembayaran tunai di lokasi |
| **1102** | Rekening Bank Operasional | Kas & Bank | ASET | Debit | Transfer bank & QRIS operasional |
| **1103** | Kas Titipan Uang Jaminan | Kas & Bank | ASET | Debit | Rekening terpisah jaminan (opsional) |
| **1201** | Piutang Sewa Kamar | Piutang Usaha | ASET | Debit | Tagihan sewa diterbitkan belum bayar |
| **1202** | Piutang Utilitas Listrik & Air | Piutang Usaha | ASET | Debit | Tagihan meter utilitas disetujui |
| **1203** | Piutang Denda & Ganti Rugi | Piutang Usaha | ASET | Debit | Kerusakan melebihi deposit saat check-out |
| **2101** | Titipan Uang Jaminan (Deposit) | Kewajiban Lancar | KEWAJIBAN | Kredit | Uang jaminan penyewa yang ditahan |
| **2102** | Pendapatan Diterima di Muka | Kewajiban Lancar | KEWAJIBAN | Kredit | Pembayaran sewa/DP periode mendatang |
| **2201** | Utang Biaya Operasional (AP) | Utang Usaha | KEWAJIBAN | Kredit | Pengeluaran disetujui belum lunas |
| **3101** | Modal Pemilik Properti | Ekuitas | EKUITAS | Kredit | Investasi awal / penambahan modal |
| **3201** | Saldo Laba Ditahan | Ekuitas | EKUITAS | Kredit | Akumulasi laba/rugi periode lalu |
| **4101** | Pendapatan Sewa Kamar Kos | Pendapatan Usaha | PENDAPATAN | Kredit | Sewa kamar kos bulanan/tahunan berjalan |
| **4102** | Pendapatan Sewa Guesthouse | Pendapatan Usaha | PENDAPATAN | Kredit | Sewa kamar guesthouse (harian) |
| **4103** | Pendapatan Utilitas | Pendapatan Usaha | PENDAPATAN | Kredit | Reimbursement / tagihan utilitas penyewa |
| **4104** | Pendapatan Denda & Ganti Rugi | Pendapatan Usaha | PENDAPATAN | Kredit | Denda keterlambatan / potongan deposit sah |
| **4105** | Pendapatan Lain-lain | Pendapatan Lain | PENDAPATAN | Kredit | Jasa tambahan / laundry / parkir |
| **5101** | Beban Listrik & Air | Beban Operasional | BEBAN | Debit | Pembayaran tagihan PLN & PDAM properti |
| **5102** | Beban Internet & Komunikasi | Beban Operasional | BEBAN | Debit | Pembayaran WiFi & telepon |
| **5103** | Beban Pemeliharaan & Perbaikan | Beban Operasional | BEBAN | Debit | Biaya tukang, perbaikan AC, plumbing |
| **5104** | Beban Kebersihan & Housekeeping | Beban Operasional | BEBAN | Debit | Perlengkapan kebersihan & sampah |
| **5105** | Beban Gaji & Upah Staf | Beban Operasional | BEBAN | Debit | Gaji pengelola & penjaga kos |
| **5106** | Beban Perlengkapan & Operasional | Beban Operasional | BEBAN | Debit | ATK, pajak, admin, & pengeluaran lain |

---

## 3. Matriks Pemetaan Transaksi Operasional ke Jurnal

Setiap jurnal otomatis dipicu saat status transaksi operasional berpindah ke kondisi sah (*verified/approved/settled*).

### 3.1. Pembayaran Sewa Kamar Kos / Guesthouse
- **Kondisi Pemicu:** `PaymentService::verifyPayment` dieksekusi oleh verifikator sah.
- **Skenario A (Periode Sewa Berjalan: `period_start <= tanggal_bayar`):**
  - **Debit:** `1101 Kas Tunai` atau `1102 Rekening Bank Operasional` (sebesar porsi sewa)
  - **Kredit:** `4101 Pendapatan Sewa Kamar` (atau `4102 Pendapatan Sewa Guesthouse`)
- **Skenario B (Pembayaran di Muka / DP untuk Periode Mendatang: `period_start > tanggal_bayar`):**
  - **Debit:** `1101 Kas Tunai` atau `1102 Rekening Bank Operasional` (sebesar porsi sewa di muka)
  - **Kredit:** `2102 Pendapatan Sewa Diterima di Muka`
- **Idempotency Key:** `payment:{payment_id}:rent`

### 3.2. Penerimaan Uang Jaminan (Security Deposit)
- **Kondisi Pemicu:** `handleVerifiedDepositPayment` atau `recordDepositReceived` saat pembayaran deposit terverifikasi.
- **Jurnal:**
  - **Debit:** `1101 Kas Tunai` atau `1102 Rekening Bank Operasional` (sebesar porsi deposit)
  - **Kredit:** `2101 Titipan Uang Jaminan (Deposit)`
- **Aturan Krusial:** Uang jaminan sama sekali BUKAN pendapatan. Tidak boleh masuk ke akun `4xxx`.
- **Idempotency Key:** `payment:{payment_id}:deposit` atau `deposit_mutation:{mutation_id}`

### 3.3. Tagihan & Pembayaran Utilitas
- **Kondisi Pemicu:** Pembayaran invoice utilitas terverifikasi.
- **Jurnal:**
  - **Debit:** `1101 Kas Tunai` atau `1102 Rekening Bank Operasional`
  - **Kredit:** `4103 Pendapatan Utilitas`
- **Pencegahan Double-Counting:** Jika invoice sewa menggabungkan sewa dan utilitas, rincian pembayaran dipecah berdasarkan proporsi tagihan sehingga pendapatan utilitas dicatat ke `4103` dan pendapatan sewa ke `4101`, tanpa menduplikasi nilai total.
- **Idempotency Key:** `payment:{payment_id}:utility`

### 3.4. Pengeluaran Biaya Operasional (Expense Payment)
- **Kondisi Pemicu:** `ExpenseService::recordPayment` untuk pengeluaran yang telah `approved`.
- **Jurnal:**
  - **Debit:** Akun Beban sesuai kategori (`5101` s/d `5106`)
  - **Kredit:** `1101 Kas Tunai` atau `1102 Rekening Bank Operasional` (sebesar nominal dicairkan)
- **Aturan Krusial:** Pembayaran parsial hanya menjurnal nominal yang benar-benar dicairkan pada `ExpensePayment` tersebut. Pengakuan beban tidak digandakan.
- **Idempotency Key:** `expense_payment:{expense_payment_id}`

### 3.5. Finalisasi Check-Out & Settlement
- **Kondisi Pemicu:** `finalizeCheckOutAndRefund` pada `DepositService` setelah settlement disetujui.
- **Komponen 1: Pemotongan Deposit untuk Ganti Rugi / Denda Kerusakan (`deductedFromDeposit > 0`):**
  - **Debit:** `2101 Titipan Uang Jaminan` (mengurangi kewajiban deposit)
  - **Kredit:** `4104 Pendapatan Denda & Ganti Rugi Kerusakan`
  - *Idempotency Key:* `settlement:{settlement_id}:deduction`
- **Komponen 2: Pengembalian Sisa Deposit ke Penyewa (`net_refund_amount > 0`):**
  - **Debit:** `2101 Titipan Uang Jaminan` (mengurangi kewajiban deposit)
  - **Kredit:** `1101 Kas Tunai` atau `1102 Rekening Bank Operasional` (mengeluarkan uang)
  - *Idempotency Key:* `settlement:{settlement_id}:refund`
- **Komponen 3: Denda Melebihi Deposit (Kekurangan yang Ditagihkan):**
  - **Debit:** `1203 Piutang Denda & Ganti Rugi` (hanya jika disahkan)
  - **Kredit:** `4104 Pendapatan Denda & Ganti Rugi Kerusakan`
  - *Idempotency Key:* `settlement:{settlement_id}:excess_deduction`

---

## 4. Mekanisme Idempotensi, Atomic Lock & Reversal

### 4.1. Idempotensi
1. Tabel `journal_entries` memiliki kolom `idempotency_key` dengan indeks `UNIQUE`.
2. Format kunci idempotensi:
   - Pembayaran sewa: `payment:{payment_id}:rent`
   - Pembayaran deposit: `payment:{payment_id}:deposit`
   - Pembayaran utilitas: `payment:{payment_id}:utility`
   - Pencairan beban: `expense_payment:{expense_payment_id}`
   - Potongan check-out: `settlement:{settlement_id}:deduction`
   - Pengembalian deposit check-out: `settlement:{settlement_id}:refund`
   - Jurnal pembalik: `reversal:{original_journal_id}`
3. Jika sebuah proses mencoba memposting ulang transaksi yang sama, kueri database akan ditolak secara atomik atau ditangkap oleh service tanpa menghasilkan baris jurnal ganda.

### 4.2. Database Locking
- Eksekusi pembuatan jurnal berjalan di dalam `DB::transaction(...)`.
- Pada MySQL/PostgreSQL produksi, record sumber dikunci dengan `lockForUpdate()`.
- Validasi total Debit = total Kredit dilakukan di dalam transaksi sebelum commit:
  $$\left| \sum \text{Debit} - \sum \text{Kredit} \right| < 0.0001$$

### 4.3. Jurnal Pembalik (Reversal)
Jika transaksi operasional dibatalkan atau terjadi kesalahan input jurnal manual:
1. Jurnal asli ditandai statusnya menjadi `reversed`.
2. Dibuat jurnal baru bertipe `reversed` dengan referensi `reversed_journal_id`.
3. Seluruh baris debit dan kredit dari jurnal asli ditukar posisinya (Debit asli menjadi Kredit, Kredit asli menjadi Debit).
4. Jurnal pembalik memiliki deskripsi audit trail: `"Pembalik atas Jurnal #[journal_number]: [alasan]"`.

---

## 5. Strategi Backfill Transaksi Historis

Command Artisan: `php artisan finance:backfill-journals`

### Prinsip Keamanan Backfill:
1. **Dukungan Flag `--dry-run`**: Menampilkan ringkasan kandidat transaksi tanpa menulis ke database.
2. **Filter `--property=ID`**: Memungkinkan eksekusi bertahap per properti.
3. **Idempoten & Non-Destruktif**: Tidak mengubah, mereset, atau menghapus data transaksi sumber (`payments`, `invoices`, `deposit_mutations`, `expense_payments`, `check_out_settlements`).
4. **Hanya Memproses Transaksi Sah**:
   - `Payment`: hanya yang berstatus `verified`.
   - `ExpensePayment`: hanya untuk expense yang `approved`.
   - `DepositMutation`: hanya yang sah dari penerimaan deposit atau refund payout.
   - `CheckOutSettlement`: hanya yang berstatus `settled`.
5. **Resume-able**: Transaksi yang sudah memiliki jurnal terkait dilewati secara otomatis (`skipped`).
6. **Laporan Exception**: Transaksi yang tidak memiliki properti valid atau nominal 0 dilaporkan sebagai peringatan.

---

## 6. Laporan Keuangan & Rekonsiliasi

### 6.1. Neraca Saldo (Trial Balance)
- Menghitung mutasi Debit, Kredit, dan Saldo Akhir untuk seluruh akun yang aktif pada periode tertentu.
- Validasi otomatis: Total Debit = Total Kredit.

### 6.2. Laporan Laba Rugi (Income Statement)
- Menghitung seluruh pendapatan (Akun `4xxx`) dikurangi seluruh beban (Akun `5xxx`) untuk jurnal yang telah berstatus `posted`.
- Memisahkan Pendapatan Sewa Kamar, Sewa Guesthouse, Utilitas, dan Denda/Kerusakan.
- Menampilkan Laba/Rugi Bersih Operasional (*Net Operating Income*).

### 6.3. Neraca (Balance Sheet)
- Aset: Kas Tunai, Bank Operasional, Kas Titipan, Piutang.
- Kewajiban: Titipan Uang Jaminan, Pendapatan Diterima di Muka, Utang Usaha.
- Ekuitas: Modal Pemilik, Saldo Laba Ditahan + Laba Bersih Periode Berjalan.
- Validasi Neraca: Total Aset = Total Kewajiban + Total Ekuitas.

### 6.4. Rekonsiliasi Subledger terhadap Jurnal
- **Rekonsiliasi Kas/Bank**: Total pembayaran terverifikasi dari subledger `payments` vs Total Debit Kas/Bank di `journal_lines`.
- **Rekonsiliasi Pengeluaran**: Total pengeluaran `expense_payments` vs Total Kredit Kas/Bank di `journal_lines`.
- **Rekonsiliasi Uang Jaminan**: Saldo pada `deposit_ledgers` vs Saldo Kredit Akun `2101` di `journal_lines`.
- Selisih (*variance*) ditampilkan dengan indikator visual yang jelas.

---

## 7. Desain API & Integrasi Flutter

### Endpoints:
- `GET /api/v1/finance/accounting/coa`: Daftar akun COA (filter properti, kategori).
- `GET /api/v1/finance/accounting/journals`: Daftar jurnal dengan filter tanggal, sumber, dan status.
- `GET /api/v1/finance/accounting/journals/{id}`: Rincian jurnal & baris debit/kredit.
- `POST /api/v1/finance/accounting/journals/{id}/reverse`: Pembalikan jurnal (khusus Owner/Super Admin).
- `GET /api/v1/finance/accounting/reports/trial-balance`: Data Neraca Saldo.
- `GET /api/v1/finance/accounting/reports/income-statement`: Data Laba Rugi.
- `GET /api/v1/finance/accounting/reports/balance-sheet`: Data Neraca.
- `GET /api/v1/finance/accounting/reports/reconciliation`: Data Rekonsiliasi Subledger.

### Otorisasi:
- Tenant: `403 Forbidden` pada seluruh endpoint akuntansi.
- Staff: Hak baca terbatas pada jurnal operasional.
- Manager & Owner: Hak akses penuh pada properti yang ditugaskan.
- Super Admin: Akses lintas properti.

---

## 8. Rencana Implementasi & Pengujian

1. **Migrasi Database**: Tabel `chart_of_accounts`, `journal_entries`, `journal_lines`, `account_mappings`.
2. **Model & Enums**: `Account`, `JournalEntry`, `JournalLine`, `AccountMapping`, `AccountType`, `AccountNormalBalance`, `JournalStatus`.
3. **Seeder Default COA**: Menyediakan akun standar otomatis untuk setiap properti.
4. **Service Engine**: `JournalPostingService` (posting otomatis & idempoten, validasi balance, reversal) & `AccountingReportService` (kalkulasi laporan keuangan & rekonsiliasi).
5. **Integrasi Hook Operasional**: Hubungkan `PaymentService`, `ExpenseService`, dan `DepositService` ke `JournalPostingService`.
6. **Command Backfill**: `finance:backfill-journals` dengan opsi dry-run.
7. **Blade UI & Layout**: Halaman COA, Jurnal, Trial Balance, Laba Rugi, Neraca, dan Rekonsiliasi.
8. **REST API & Flutter**: Controller API, DTO/Model Dart, State Management Riverpod & Layar Mobile.
9. **Pengujian Menyeluruh**: PHPUnit feature test (balance check, idempotency, property isolation, reversal, backfill) dan Flutter test.
