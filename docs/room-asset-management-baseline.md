# Baseline Audit & Gap Analysis: Room Master & Fixed Asset Management
**SIM Kos & Guesthouse System**
**Date:** 19 Oktober 2026
**Commit Baseline:** `0aa8495` on `feature/booking-end-to-end`

---

## 1. Executive Summary & Git Baseline
Dokumen ini menyajikan baseline audit teknis dan gap analysis terhadap modul Master Kamar, Aset Tetap, Inventaris Kos, Pemeliharaan, dan Integrasi Akuntansi Pembukuan Berpasangan (Double-Entry Bookkeeping).

### Status Git & Lingkungan:
- **Active Branch:** `feature/booking-end-to-end`
- **HEAD Commit:** `0aa8495`
- **Working Tree:** Perubahan lokal dari Phase 1–10 tersimpan utuh dan dipertahankan (uncommitted/staged/untracked files dijaga agar tidak hilang atau tertimpa).
- **Automated Tests Baseline:**
  - Backend PHPUnit: **206 tests, 1,141 assertions PASSED** (0 failures).
  - Mobile Flutter: **63 tests PASSED**, `flutter analyze` clean, debug APK valid.
- **Batasan Keamanan:**
  - Tidak ada eksekusi `migrate:fresh`, `db:wipe`, `DROP TABLE`, atau manipulasi destruktif lainnya.
  - Tidak ada perintah `git commit`, `push`, `merge`, `rebase`, atau modifikasi remote repository.

---

## 2. Audit Skema & Model Eksisting

### 2.1 Modul Kamar (`rooms` & `room_types`)
- **Tabel `rooms`**:
  - Kolom: `id`, `property_id`, `room_type_id`, `room_number`, `floor`, `status` (`available`, `occupied`, `maintenance`, `dirty`), `is_active`, `timestamps`.
  - Constraint: `UNIQUE(property_id, room_number)`, foreign key `property_id` cascade, `room_type_id` restrict.
- **Tabel `room_types`**:
  - Kolom: `id`, `property_id`, `name`, `description`, `base_price_daily`, `base_price_monthly`, `base_price_yearly`, `max_occupants`, `size_m2`, `facilities`, `photos`, `timestamps`.
- **Temuan & Gap Analysis**:
  - `RoomWebController` hanya memiliki method `updateStatus()` untuk mengubah status kamar. Belum ada antarmuka web penuh untuk daftar kamar (dengan pencarian, filter status, tipe, gedung/lantai), form tambah kamar, form edit kamar (menggunakan right-side drawer), detail kamar multi-tab (info, fasilitas, riwayat perubahan, booking aktif/riwayat, dan daftar aset).
  - Kamar belum memiliki field kustom opsional jika ada variasi spesifik per kamar (misal: luas/kapasitas khusus, harga override, catatan internal, foto unit spesifik, nama gedung/blok).
  - Belum ada pencatatan riwayat perubahan data kamar (`room_change_histories`).
  - Validasi pengamanan: kamar yang sedang aktif ditempati (booking berstatus `confirmed`, `active`, `checked_in`) tidak boleh dinonaktifkan atau dihapus sembarangan.

### 2.2 Modul Aset Eksisting (`room_assets`)
- **Tabel `room_assets`**:
  - Kolom: `id`, `room_id`, `item_name`, `quantity`, `condition` (`good`, `slightly_damaged`, `broken`, `missing`), `notes`, `timestamps`.
- **Temuan & Gap Analysis**:
  - Tabel `room_assets` adalah struktur legacy sederhana yang hanya terikat ke `room_id` dan hanya memuat barang kamar (misal: bantal, kasur).
  - Kos & Guesthouse membutuhkan entitas Aset Tetap & Inventaris Operasional yang komprehensif (`assets`):
    - Dapat berlokasi di kamar, gedung, lobi/area umum, gudang, atau fasilitas luar ruangan (pompa air, CCTV, mesin cuci, gerbang pagar, motor operasional).
    - Memiliki kode aset unik (`asset_code`), klasifikasi akuntansi (aset tetap kapitalisasi vs inventaris bernilai rendah vs barang habis pakai).
    - Nilai perolehan (`purchase_cost`), nilai residu (`salvage_value`), masa manfaat (`useful_life_months`), tanggal mulai dipakai (`in_service_date`).
    - Lifecycle status (`draft`, `pending_approval`, `active`, `in_maintenance`, `inactive`, `disposed`, `written_off`).
    - Histori perpindahan lokasi (`asset_location_histories`).
    - Skedul penyusutan berkala (`asset_depreciation_schedules`).
  - Solusi arsitektur: Mempertahankan `room_assets` untuk kompatibilitas data lama, sekaligus menyediakan entitas `assets` modern dan menghubungkan keduanya atau mengizinkan migrasi transparan tanpa merusak data lama.

### 2.3 Modul Pemeliharaan & Work Order (`maintenance_requests`)
- **Tabel `maintenance_requests`**:
  - Kolom: `id`, `ticket_number`, `property_id`, `room_id`, `room_asset_id`, `complaint_id`, `reporter_id`, `assigned_to`, `title`, `description`, `category`, `priority`, `status`, `rejection_reason`, `cancellation_reason`, `scheduled_date`, `started_at`, `completed_at`, `verified_at`, `verified_by`, `verification_notes`, `diagnosis_notes`, `action_taken`, `spare_parts`, `labor_cost`, `material_cost`, `total_cost`, `photos_before`, `photos_after`, `sla_due_at`, `is_overdue`, `timestamps`.
- **Temuan & Gap Analysis**:
  - Struktur tabel Phase 5 sudah sangat lengkap dan telah memiliki rincian biaya (`labor_cost`, `material_cost`, `total_cost`, `spare_parts`).
  - Gap: Perlu kolom relasi additive `asset_id` (merujuk ke tabel `assets`) sehingga work order dapat ditujukan ke aset umum/gedung maupun aset kamar.
  - Perlu dukungan keputusan akuntansi: apakah perbaikan dicatat sebagai beban langsung (`expense`) atau dikapitalisasi ke nilai aset (`capitalization`).

### 2.4 Modul Keuangan & Beban (`expenses` & `expense_payments`)
- **Tabel `expenses` & `expense_categories`**:
  - Mendukung approval workflow (`draft`, `submitted`, `approved`, `rejected`), payment status (`unpaid`, `partially_paid`, `paid`), dan pencairan via `expense_payments`.
- **Temuan & Gap Analysis**:
  - Perlu integrasi referensi pembelian aset baru ke modul pengeluaran existing (`expense_id`).
  - Mencegah double-posting: jika perolehan aset dicatat dari pengeluaran yang sudah ada, harus dilakukan pengaitan tanpa membuat pengeluaran ganda.

### 2.5 Modul Bagan Akun & Jurnal Berpasangan (Phase 8 COA & General Ledger)
- **Tabel `chart_of_accounts`, `account_mappings`, `journal_entries`, `journal_lines`**:
  - Engine `JournalPostingService` sudah mendukung idempotency, balance check (Debit = Credit), locking, dan reversal.
- **Akun Akuntansi yang Dibutuhkan untuk Aset & Depresiasi**:
  - `1301` — Aset Tetap - Bangunan & Prasarana (Asset, Debit)
  - `1302` — Aset Tetap - Peralatan & Elektronik (Asset, Debit)
  - `1303` — Aset Tetap - Furnitur & Mebel (Asset, Debit)
  - `1304` — Aset Tetap - Kendaraan Operasional (Asset, Debit)
  - `1391` — Akumulasi Penyusutan Aset Tetap (Contra-Asset, Credit)
  - `5201` — Beban Penyusutan Aset Tetap (Expense, Debit)
  - `4201` — Keuntungan Pelepasan Aset Tetap (Revenue, Credit)
  - `5301` — Kerugian Pelepasan Aset Tetap (Expense, Debit)
- **Aturan Jurnal**:
  - **Penyusutan Bulanan:**
    - Debit: `5201` (Beban Penyusutan)
    - Kredit: `1391` (Akumulasi Penyusutan)
    - Idempotency Key: `asset_depreciation_{asset_id}_{year}_{month}`
  - **Pelepasan / Penjualan Aset:**
    - Debit: `1101`/`1102` (Kas/Bank sebesar harga jual kas)
    - Debit: `1391` (Akumulasi Penyusutan yang telah dibukukan)
    - Debit (jika rugi): `5301` (Kerugian Pelepasan Aset)
    - Kredit: `13xx` (Harga Perolehan Aset Asli)
    - Kredit (jika untung): `4201` (Keuntungan Pelepasan Aset)

---

## 3. Rencana Migrasi Aditif & Desain Arsitektur

1. **Migration 1: `add_master_management_fields_to_rooms_and_create_histories_table`**
   - Menambahkan kolom fleksibel pada `rooms`: `building`, `size_m2`, `capacity`, `description`, `facilities`, `price_daily`, `price_monthly`, `price_yearly`, `deposit_amount`, `down_payment_amount`, `photos`, `internal_notes`.
   - Membuat tabel `room_change_histories`.

2. **Migration 2: `create_fixed_assets_and_depreciation_tables`**
   - Membuat tabel `assets`.
   - Membuat tabel `asset_location_histories`.
   - Membuat tabel `asset_depreciation_schedules`.
   - Menambahkan foreign key `asset_id` pada `maintenance_requests` (additive nullable).

3. **Chart of Accounts Service**:
   - Menambahkan akun `1301`, `1302`, `1303`, `1304`, `1391`, `5201`, `4201`, `5301` ke `ChartOfAccountService::DEFAULT_ACCOUNTS`.

4. **Web UI & Navigation**:
   - Menambahkan menu **Master Kamar** dan **Aset & Inventaris** di sidebar layout Blade + Bootstrap 5.
   - Implementasi view `dashboard/rooms/index.blade.php`, `dashboard/rooms/show.blade.php`, dan right-side drawer slide-in panel.
   - Implementasi view `dashboard/assets/index.blade.php`, `dashboard/assets/show.blade.php`, right-side drawer tambah/edit aset, transfer lokasi modal, penyusutan modal, dan pelepasan modal.

5. **Flutter Mobile**:
   - Memastikan API master kamar dan aset aman dengan role-based access control (RBAC): tenant tidak diizinkan melakukan operasi internal aset atau manipulasi kamar. Staf/manajer dapat melihat aset dan melaporkan perbaikan langsung.
