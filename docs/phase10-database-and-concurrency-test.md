# AUDIT MIGRASI BASIS DATA, INTEGRITAS SKEMA & PENGUJIAN KONKURENSI
**Fase:** Phase 10 — Database Compatibility, Schema Integrity & Concurrency Verification  
**Sistem:** SIM Kos & Guesthouse (Laravel 13)  
**Dokumen:** `docs/phase10-database-and-concurrency-test.md`  
**Status Evaluasi:** Teruji Secara Empiris pada Lingkungan Terisolasi  

---

## 1. Audit Skema dan Urutan Ketergantungan Migrasi

Aplikasi memiliki **25 berkas migrasi** yang disusun secara kronologis dan berurutan sesuai fase pengembangan:

| No | Berkas Migrasi | Modul / Entitas Utama | Ketergantungan Induk |
| :---: | :--- | :--- | :--- |
| 1 | `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` | Root |
| 2 | `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` | Framework |
| 3 | `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` | Framework |
| 4 | `2026_10_13_140643_create_personal_access_tokens_table.php` | `personal_access_tokens` (Sanctum) | `users` |
| 5 | `2026_10_13_150000_create_properties_table.php` | `properties` | Root Bisnis |
| 6 | `2026_10_13_150001_create_property_users_table.php` | `property_users` (Pivot Role) | `properties`, `users` |
| 7 | `2026_10_13_150002_create_tenant_profiles_table.php` | `tenant_profiles` | `users` |
| 8 | `2026_10_13_150003_create_room_types_table.php` | `room_types` | `properties` |
| 9 | `2026_10_13_150004_create_rooms_table.php` | `rooms` | `properties`, `room_types` |
| 10 | `2026_10_13_150005_create_room_assets_table.php` | `room_assets` | `rooms` |
| 11 | `2026_10_13_150006_create_bookings_table.php` | `bookings` | `properties`, `rooms`, `users` |
| 12 | `2026_10_13_150007_create_invoices_table.php` | `invoices` | `properties`, `bookings`, `users` |
| 13 | `2026_10_13_150008_create_payments_table.php` | `payments` | `invoices`, `users` |
| 14 | `2026_10_13_150009_create_utility_readings_table.php` | `utility_readings` (Legacy) | `rooms` |
| 15 | `2026_10_13_150010_create_expenses_table.php` | `expenses` (Legacy) | `properties`, `users` |
| 16 | `2026_10_13_150011_create_complaints_table.php` | `complaints` | `properties`, `rooms`, `users` |
| 17 | `2026_10_13_150012_create_announcements_table.php` | `announcements` | `properties`, `users` |
| 18 | `2026_10_13_160000_update_invoices_and_payments_tables.php` | Kolom tambahan invoicing | `invoices`, `payments` |
| 19 | `2026_10_15_100000_create_inspections_and_checklist_tables.php` | `inspections`, `inspection_items`, `inspection_media` | `bookings`, `rooms`, `users` |
| 20 | `2026_10_15_200000_create_utility_meters_and_tariffs_tables.php` | `utility_tariffs`, `utility_meters`, meter readings | `properties`, `rooms` |
| 21 | `2026_10_15_300000_create_phase5_maintenance_complaints_housekeeping_tables.php` | `maintenance_requests`, `housekeeping_tasks`, comment logs | `properties`, `rooms`, `users` |
| 22 | `2026_10_16_000000_create_phase6_financial_and_expenses_tables.php` | `expense_categories`, `expenses`, `expense_payments` | `properties`, `users` |
| 23 | `2026_10_16_100000_add_guest_and_dp_fields_to_bookings_and_properties.php` | Tambahan field Down Payment & Guest Booking | `bookings`, `properties` |
| 24 | `2026_10_17_100000_create_phase7_deposit_and_settlement_tables.php` | `deposit_ledgers`, `deposit_mutations`, `check_out_settlements`, `settlement_deductions` | `bookings`, `users` |
| 25 | `2026_10_17_200000_create_phase8_accounting_and_journal_tables.php` | `accounts`, `account_mappings`, `journal_entries`, `journal_lines` | `properties`, `users` |

---

## 2. Hasil Uji Migrasi Empiris (Database Terisolasi)

Pengujian migrasi dijalankan pada database SQLite baru terisolasi (`scratch/staging_sim.sqlite`) untuk menguji eksekusi dari skema kosong:
- **Jumlah Berkas Migrasi Dijalankan:** 25 berkas migrasi.
- **Total Tabel Dihasilkan:** **49 tabel** (termasuk tabel sistem dan junction).
- **Durasi Eksekusi Migrasi:** **222.91 milidetik**.
- **Error / Integrity Violation:** **0 Error**.
- **Kesimpulan:** Seluruh Foreign Key cascades, default constraints, dan nullable fields kompatibel satu sama lain dan dapat dijalankan tanpa bentrok urutan.

---

## 3. Presisi Finansial, Integritas Relasi & Kunci Idempotensi

### 3.1 Presisi Tipe Data Desimal (`decimal:2`)
Seluruh kolom mata uang nominal uang sewa, tagihan utilitas, pengeluaran operasional, saldo uang jaminan, dan baris jurnal double-entry menggunakan tipe data `decimal(15, 2)` pada skema SQL dan dicast sebagai `'decimal:2'` pada Eloquent:
- `bookings.rate_amount`, `bookings.deposit_amount`, `bookings.total_amount`
- `invoices.subtotal`, `invoices.discount`, `invoices.penalty`, `invoices.total_amount`
- `deposit_ledgers.initial_deposit_amount`, `current_balance`, `total_received`, `total_refunded`
- `check_out_settlements.deposit_held`, `total_deductions`, `net_refund_amount`
- `journal_entries.total_amount`
- `journal_lines.debit`, `journal_lines.credit`
- **Keuntungan:** Menghilangkan masalah pembulatan biner (*floating-point roundoff error*) yang umum terjadi jika menggunakan tipe `float` atau `double`.

### 3.2 Kunci Idempotensi (`idempotency_key`)
- Tabel `journal_entries` menerapkan `unique('idempotency_key')`.
- Setiap transaksi finansial otomatis membentuk kunci unik deterministik:
  - Pembayaran sewa: `payment:{payment_id}:{timestamp_date}`
  - Pengeluaran operasional: `expense_payment:{expense_payment_id}:{date}`
  - Check-out settlement: `settlement:{settlement_id}:{type}:{date}`
  - Pengembalian deposit: `deposit_refund:{mutation_id}:{date}`
- **Hasil Uji:** Permintaan posting duplikat dengan kunci yang sama terbukti tidak menambah baris baru di database dan mengembalikan record yang sudah ada (`count before = 4, count after duplicate = 4, delta = 0`).

---

## 4. Evaluasi Konkurensi & Locking: SQLite vs. MySQL / PostgreSQL

### 4.1 Perbedaan Fundamental Mekanisme Penguncian
| Parameter | SQLite 3 (Driver Pengujian Lokal) | MySQL 8.0 (InnoDB) / PostgreSQL 15+ (Target Produksi) |
| :--- | :--- | :--- |
| **Granularitas Penguncian** | **File-Level Database Lock** | **Row-Level Lock (`SELECT ... FOR UPDATE`)** |
| **Konkurensi Multi-User** | Menolak penulisan simultan; transaksi mengantri di level seluruh file basis data. | Transaksi simultan dapat memodifikasi baris berbeda pada tabel yang sama secara paralel. |
| **Deadlock Handling** | Menghasilkan error `Database is locked` jika timeout tercapai. | Engine InnoDB mendeteksi graf siklus deadlock dan secara otomatis me-rollback salah satu transaksi. |
| **Keterbatasan Bukti Uji** | Memvalidasi batasan logika `DB::transaction` dan rollback aplikasi, tetapi **bukan bukti final** row locking multi-worker. | Wajib diuji pada server staging dengan instance MySQL nyata sebelum pembukaan komersial. |

### 4.2 Penerapan Pessimistic Row Locking pada Kode Aplikasi
Aplikasi telah secara eksplisit menerapkan `lockForUpdate()` pada titik-titik rawan persaingan data (*race conditions*):
1. **Pemesanan Kamar & Ketersediaan (`BookingService`):**
   ```php
   $room = Room::where('id', $roomId)->lockForUpdate()->firstOrFail();
   ```
   Mencegah dua calon penyewa memesan kamar yang sama pada rentang tanggal bertabrakan.
2. **Subledger Uang Jaminan (`DepositService`):**
   ```php
   $locked = DepositLedger::where('id', $ledger->id)->lockForUpdate()->firstOrFail();
   ```
   Memastikan saldo deposit dibaca dan diperbarui secara atomik saat pencatatan penerimaan deposit, pemotongan kerusakan, atau pengembalian dana (*refund*).
3. **Penyelesaian Check-Out (`DepositService`):**
   ```php
   $lockedSettlement = CheckOutSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();
   ```
   Mencegah proses *double-refund* atau otorisasi ganda saat dua staf menekan tombol finalisasi secara bersamaan.

---

## 5. Ringkasan Kesiapan Skema Basis Data

- **Kompatibilitas Skema:** **LULUS (100%)** pada pengujian terisolasi.
- **Foreign Key Constraints:** **LULUS (100%)** tanpa siklus referensi yang melanggar.
- **Pencegahan Saldo Negatif:** **LULUS (100%)** pada logic service dan constraint transaksi.
- **Rekomendasi Tindakan Staging:** Jalankan simulasi konkurensi multi-koneksi menggunakan skrip `ab` (ApacheBench) atau k6 terhadap endpoint `/api/v1/bookings` dan `/dashboard/deposits/*` pada instance MySQL staging.
