# RENCANA CADANGAN DATA DAN PEMULIHAN BENCANA (BACKUP & DISASTER RECOVERY)
**Fase:** Phase 9 — Production Readiness, Security & Operational Hardening  
**Target Sistem:** SIM Kos & Guesthouse (Laravel 13 + MySQL 8.0 / PostgreSQL 15)  
**Dokumen:** `docs/phase9-backup-recovery.md`  
**Status:** Rekomendasi Siap Divalidasi Pemilik Sistem  

---

## 1. Tujuan dan Ruang Lingkup

Dokumen ini menetapkan standar operasional pencadangan (*backup*), penyimpanan terisolasi (*off-site storage*), retensi, dan pemulihan darurat (*disaster recovery*) untuk seluruh aset kritis sistem SIM Kos & Guesthouse.

Aset kritis yang dicakup:
1. **Basis Data Transaksional:** Akun keuangan, jurnal umum, subledger deposit, booking, invoice, riwayat pembayaran, data kamar, tarif, dan audit logs.
2. **Berkas Privat & Dokumen Fisik (`storage/app/private/`):** Foto bukti pembayaran penyewa, kuitansi pengeluaran operasional, bukti transfer vendor, foto inspeksi kamar pra/pasca sewa, dan foto meteran utilitas.
3. **Konfigurasi Lingkungan Aplikasi:** Berkas templat konfigurasi (`.env.example`), struktur migrasi, dan kunci publik aplikasi.

---

## 2. Target Kesiapan Layanan (RPO & RTO)

Rekomendasi target pemulihan yang diajukan untuk disetujui oleh Pemilik Sistem (*Owner*):

| Parameter | Target yang Direkomendasikan | Penjelasan Teknis |
| :--- | :---: | :--- |
| **RPO (Recovery Point Objective)** | **< 1 Jam** | Batas maksimal toleransi kehilangan data transaksional jika terjadi bencana server utama. Dicapai melalui kombinasi *daily full backup* dan pencatatan *binary logs* (MySQL) atau *WAL archiving* (PostgreSQL). |
| **RTO (Recovery Time Objective)** | **< 2 Jam** | Durasi waktu maksimal dari insiden terjadi hingga seluruh sistem (database, private storage, dan web/API backend) beroperasi normal kembali di server pengganti. |

---

## 3. Strategi dan Metode Pencadangan

### 3.1 Pencadangan Basis Data Relasional (Target: MySQL 8 / PostgreSQL 15)

#### A. Target MySQL 8.0+
- **Metode Pencadangan Penuh (Full Backup):**
  Menggunakan `mysqldump` dengan *single transaction* untuk menghindari penguncian tabel:
  ```bash
  mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASSWORD \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --databases sim_kos_production | gzip -9 > /backup/db/db_sim_kos_$(date +%Y%m%d_%H%M%S).sql.gz
  ```
- **Pencadangan Berkelanjutan (Point-In-Time Recovery):**
  Mengaktifkan `binlog` pada MySQL dengan retensi minimal 7 hari. Seluruh transaksi di antara backup harian dapat diputar ulang (*replay*) hingga menit terjadinya insiden.

#### B. Target PostgreSQL 15+
- **Metode Pencadangan Penuh:**
  ```bash
  pg_dump -h $DB_HOST -U $DB_USER -F c -b -v -f /backup/db/db_sim_kos_$(date +%Y%m%d_%H%M%S).dump sim_kos_production
  ```
- **Pencadangan Berkelanjutan:**
  Menggunakan *Write-Ahead Logging* (WAL-G atau pgBackRest) yang dialirkan ke cloud storage.

### 3.2 Pencadangan Berkas Privat (`storage/app/private/`)
- Seluruh berkas di `storage/app/private/` dicadangkan secara harian menggunakan sinkronisasi inkremental terenkripsi (misalnya `rclone` atau `aws s3 sync`).
- Berkas lama tidak ditimpa; metadata timestamps dipertahankan.

---

## 4. Jadwal dan Kebijakan Retensi

### 4.1 Jadwal Otomatisasi (Cron Job Server Terpisah)
- **Full Database Dump:** Setiap hari pukul 02:00 WIB (saat trafik rendah).
- **Sinkronisasi Berkas Privat:** Setiap hari pukul 03:00 WIB.
- **Log Transaksi (Binlog/WAL):** Diarsipkan setiap 15 menit ke remote storage.

### 4.2 Kebijakan Retensi (Grandfather-Father-Son Model)
- **Daily Backups:** Disimpan selama **14 hari**.
- **Weekly Backups:** Disimpan setiap akhir pekan selama **8 minggu**.
- **Monthly Backups:** Disimpan setiap akhir bulan selama **12 bulan**.
- **Annual Backups:** Disimpan pada akhir tahun kalender selama **5 tahun** (kebutuhan kepatuhan pajak & audit keuangan).

---

## 5. Keamanan dan Isolasi Penyimpanan Cadangan

1. **Penyimpanan Terpisah (Off-Site Storage):**
   - Berkas cadangan **dilarang disimpan di server aplikasi yang sama**.
   - Cadangan disimpan pada Object Storage terpisah (misalnya AWS S3, Cloudflare R2, atau MinIO dedicated) di region yang berbeda (*cross-region redundancy*).
2. **Kekebalan Cadangan (Object Lock / WORM Storage):**
   - Mengaktifkan fitur *Write-Once-Read-Many* (WORM) atau *Compliance Mode* selama 30 hari untuk mencegah penghapusan atau enkripsi oleh ransomware.
3. **Enkripsi Data At-Rest:**
   - Berkas backup dikompresi dan dienkripsi menggunakan AES-256 atau GPG asymmetric key sebelum dikirim keluar server:
     ```bash
     gpg --symmetric --cipher-algo AES256 -o backup_encrypted.tar.gz.gpg backup.tar.gz
     ```
4. **Prinsip Hak Akses Terkecil (Least Privilege):**
   - Kredensial IAM yang digunakan server aplikasi hanya memiliki izin `PutObject` (tidak boleh memiliki izin `DeleteObject` atau `ListBucket`).

---

## 6. Prosedur Pemulihan Sistem (Disaster Recovery Runbook)

### 6.1 Langkah 1: Persiapan Server Pengganti
1. Siapkan virtual machine/container baru dengan OS Linux (Ubuntu 24.04 LTS / Debian 12).
2. Pasang runtime: PHP 8.4 (beserta ekstensi `pdo_mysql`, `mbstring`, `bcmath`, `curl`, `gd`, `zip`, `xml`), Nginx/Caddy, dan Database Server.
3. Siapkan direktori kerja aplikasi dan clone repository kode (sesuai commit/tag rilis yang disetujui).

### 6.2 Langkah 2: Pemulihan Basis Data
1. Ambil berkas cadangan database terbaru dari Object Storage dan lakukan dekripsi.
2. Verifikasi integritas checksum SHA-256:
   ```bash
   sha256sum -c backup_checksum.sha256
   ```
3. Impor data ke instance database baru:
   ```bash
   gunzip < db_sim_kos_YYYYMMDD_HHMMSS.sql.gz | mysql -h localhost -u simkos_user -p sim_kos_production
   ```
4. Jika point-in-time recovery diperlukan, aplikasikan binary logs dari backup terakhir hingga titik insiden.

### 6.3 Langkah 3: Pemulihan Berkas Privat
1. Unduh arsip berkas privat dari bucket penyimpanan cadangan.
2. Ekstrak ke folder `storage/app/private/`.
3. Setel izin direktori (*file permissions*) yang aman:
   ```bash
   chown -R www-data:www-data storage/app/private
   chmod -R 750 storage/app/private
   ```

### 6.4 Langkah 4: Konfigurasi dan Pemulihan Lingkungan
1. Salin berkas konfigurasi `.env` dari safe secret storage (jangan menggunakan nilai default).
2. Pastikan `APP_ENV=production` dan `APP_DEBUG=false`.
3. Jalankan penguncian konfigurasi Laravel:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 7. Verifikasi Integritas Transaksi Pasca-Restore

Setelah basis data berhasil dipulihkan, tim operasional **wajib** menjalankan pengujian integritas finansial sebelum membuka kembali akses sistem untuk pengguna publik:

1. **Keseimbangan Neraca Saldo (Trial Balance Integrity):**
   Jalankan pemeriksaan via service laporan akuntansi:
   - Pastikan `Total Debit == Total Kredit` di seluruh akun.
   - Selisih (*variance*) harus tepat **0.00**.
2. **Integritas Subledger Uang Jaminan (Deposit Ledger vs Balance Sheet):**
   - Bandingkan total saldo uang jaminan pada tabel `deposit_ledgers` dengan saldo akun kewajiban *2050 - Uang Jaminan Penyewa (Deposit)* di neraca keuangan.
   - Nilai keduanya harus cocok secara presisi.
3. **Pemeriksaan Status Booking Aktif:**
   - Verifikasi tidak ada kamar yang berstatus ganda (*double occupancy*).
4. **Pemeriksaan Rekonsiliasi Bank:**
   - Jalankan laporan rekonsiliasi kas/bank untuk mencocokkan saldo tercatat dengan rekening koran bank operasional.

---

## 8. Rencana Penanganan Kegagalan Deployment / Migration

Jika pembaruan sistem (*deployment*) atau migrasi skema database mengalami kegagalan di tengah jalan:
1. **Otomatisasi Rollback Migrasi:**
   - Jika migrasi terakhir gagal, eksekusi perintah pembalikan:
     ```bash
     php artisan migrate:rollback --step=1
     ```
2. **Snapshot Rollback:**
   - Jika migrasi merusak struktur tabel atau data transaksional, pulihkan snapshot database yang diambil tepat sebelum deployment dimulai.
3. **Mode Pemeliharaan (Maintenance Mode):**
   - Saat insiden ditangani, aktifkan mode pemeliharaan agar tidak ada transaksi baru yang masuk:
     ```bash
     php artisan down --secret="simkos-bypass-key" --render="errors::503"
     ```

---

## 9. Jadwal Simulasi dan Pengujian Pemulihan Berkala

- **Simulasi Restore Kering (Dry-Run Restore Drill):**
  - Dilakukan secara terjadwal **1 kali setiap bulan** pada server staging terisolasi.
  - Skrip pengujian otomatis akan mengunduh backup acak, melakukan restore ke database pengujian sementara, memvalidasi integritas baris data, dan menghapus database uji setelah pengujian selesai.
  - Hasil simulasi dicatat dalam log pemeliharaan infrastruktur.
