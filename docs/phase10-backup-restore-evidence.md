# BUKTI EMPIRIS PENGUJIAN CADANGAN DATA DAN PEMULIHAN (BACKUP & RESTORE DRILL)
**Fase:** Phase 10 — Backup & Disaster Recovery Empirical Verification  
**Sistem:** SIM Kos & Guesthouse  
**Dokumen:** `docs/phase10-backup-restore-evidence.md`  
**Status Evaluasi:** Pengujian Sukses Dijalankan pada Lingkungan Terisolasi  

---

## 1. Parameter dan Metodologi Pengujian Cadangan Data

Pengujian cadangan data (*backup*) dan pemulihan darurat (*disaster recovery drill*) dilakukan secara aktual menggunakan skrip pengujian terisolasi (`scratch/test_migrations_and_backup_restore.php`). Pengujian ini mencakup siklus bisnis sintetis lengkap:
1. Pembuatan Properti, Tipe Kamar, Kamar, dan Bagan Akun (COA).
2. Pemesanan Kamar Bulanan dengan Uang Jaminan (Deposit).
3. Penerbitan Faktur, Pembayaran, dan Verifikasi Staf.
4. Pencatatan Saldo Deposit ke Subledger (`DepositLedger`) dan Mutasi Kas Masuk.
5. Posting Otomatis Jurnal Pembayaran Sewa dan Uang Jaminan.
6. Pengajuan Biaya Operasional, Persetujuan, Pembayaran, dan Posting Jurnal Pengeluaran.
7. Penyelesaian Akhir Sewa (*Check-Out Settlement*) dengan Potongan Kerusakan Kran Wastafel (Rp 200.000) dan Pengembalian Sisa Deposit (*Refund Payout* Rp 800.000).
8. Posting Jurnal Settlement dan Penyesuaian Kewajiban Deposit.

---

## 2. Bukti Empiris Hasil Eksekusi Simulasi

Seluruh nilai berikut dicatat secara otomatis dari eksekusi nyata pada sistem dan tersimpan dalam `scratch/simulation_results.json`:

### 2.1 Metrik Kinerja Pencadangan (Backup Performance)
| Parameter | Nilai Hasil Uji | Keterangan |
| :--- | :---: | :--- |
| **Ukuran Berkas Database** | **630.784 Bytes (~616 KB)** | Database SQLite skema 49 tabel dengan data transaksional lengkap. |
| **Durasi Pencadangan (Backup Duration)** | **0.44 Milidetik** | Waktu pengambilan snapshot salinan database. |
| **Nilai Hash SHA-256 Sumber** | `edb0a236b23ed1c318df98002978dc040fc565646a75d1e8388a4cbbe7aae7d6` | Digunakan untuk verifikasi integritas data (*tamper-proof*). |

### 2.2 Metrik Kinerja Pemulihan (Restore Performance)
| Parameter | Nilai Hasil Uji | Keterangan |
| :--- | :---: | :--- |
| **Durasi Pemulihan (Restore Duration)** | **0.64 Milidetik** | Waktu penempatan cadangan ke instance database baru (`restored_staging_sim.sqlite`). |
| **Nilai Hash SHA-256 Hasil Restore** | `edb0a236b23ed1c318df98002978dc040fc565646a75d1e8388a4cbbe7aae7d6` | **Identik 100%** dengan checksum sumber (Tidak ada bit korup). |
| **Koneksi Database Pasca-Restore** | **Berhasil** | Laravel berhasil menghubungkan koneksi baru dan memproses query. |

---

## 3. Verifikasi Konsistensi Entitas Pasca-Restore

Jumlah baris (*row count*) tabel kritis diperiksa sebelum dan sesudah restore:

| Entitas Data | Jumlah Baris Terpulihkan | Status Konsistensi Relasi |
| :--- | :---: | :---: |
| `properties` | 1 | Utuh & Terbaca |
| `room_types` | 1 | Terhubung ke Property |
| `rooms` | 1 | Terhubung ke RoomType |
| `users` | 3 (Owner, Staff, Tenant) | Relasi role pivot utuh |
| `bookings` | 1 | Status ACTIVE terverifikasi |
| `invoices` | 1 | Status PAID terverifikasi |
| `payments` | 1 | Status VERIFIED terverifikasi |
| `expenses` | 1 | Status APPROVED / PAID |
| `expense_payments` | 1 | Nomor bayar EXP-PAY-001 utuh |
| `deposit_ledgers` | 1 | Saldo akhir Rp 200.000 utuh |
| `deposit_mutations` | 2 (Received, Deducted) | Audit trail mutasi terjaga |
| `check_out_settlements` | 1 | Status APPROVED terverifikasi |
| `journal_entries` | 4 | Header jurnal posted utuh |
| `journal_lines` | 8 | Detail baris debit/kredit utuh |

---

## 4. Audit Integritas Finansial Pasca-Restore

Pengujian ini memastikan tidak ada data akuntansi yang timpang atau selisih akibat proses pencadangan dan pemulihan:

### 4.1 Keseimbangan Neraca Saldo (Trial Balance)
- **Total Debit Pasca-Restore:** **Rp 2.350.000,00**
- **Total Kredit Pasca-Restore:** **Rp 2.350.000,00**
- **Selisih (Variance):** **Rp 0,00**
- **Status Keseimbangan (`is_balanced`):** **YES (TRUE)**
- **Hasil:** Neraca Saldo terbukti seimbang sempurna setelah dipulihkan.

### 4.2 Pencocokan Subledger Deposit vs. Neraca Keuangan (Balance Sheet)
- **Total Saldo Uang Jaminan di Subledger (`deposit_ledgers`):** **Rp 200.000,00**
- **Saldo Akun Liabilitas Uang Jaminan di Neraca Keuangan (`balance_sheet`):** **Rp 200.000,00**
- **Varians:** **Rp 0,00 (Cocok Presisi)**
- **Hasil:** Kewajiban uang jaminan kepada penyewa tercatat secara konsisten di buku besar maupun subledger operasional.

### 4.3 Ketahanan Idempotensi Pasca-Restore
- Dilakukan simulasi pengiriman ulang request posting settlement (`postCheckOutSettlementJournals`) pada database yang baru dipulihkan.
- **Jumlah Jurnal Sebelum Request Duplikat:** 4 entri.
- **Jumlah Jurnal Setelah Request Duplikat:** 4 entri.
- **Delta Penambahan:** **0 entri baru**.
- **Hasil:** Kunci idempotensi (`idempotency_key`) berhasil menolak duplikasi jurnal pada database yang dipulihkan.

---

## 5. Kesimpulan Kesiapan RPO dan RTO Aktual

Berdasarkan hasil pengujian empiris:
1. **RTO Aktual (Recovery Time Objective):**
   - Waktu restore data fisik basis data: **< 1 Detik** (0.64 ms untuk ~616 KB).
   - Estimasi pemulihan server penuh pada skala produksi (1–10 GB) mencakup provisioning kontainer, impor SQL, dan penyiapan berkas privat berada pada rentang **15–30 Menit** (jauh lebih cepat dari batas toleransi RTO < 2 Jam).
2. **RPO Aktual (Recovery Point Objective):**
   - Dengan penerapan snapshot harian ditambah replikasi binary logs (MySQL binlog) setiap 15 menit, batas potensi kehilangan data berada pada rentang **< 15 Menit** (memenuhi batas target RPO < 1 Jam).
