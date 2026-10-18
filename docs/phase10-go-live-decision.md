# KEPUTUSAN KESIAPAN GO-LIVE DAN REKOMENDASI OPERASIONAL
**Fase:** Phase 10 — Staging Deployment, End-to-End Validation & Go-Live Decision  
**Sistem:** SIM Kos & Guesthouse (Laravel 13 REST API + Blade Dashboard + Flutter Mobile)  
**Dokumen:** `docs/phase10-go-live-decision.md`  
**Status Evaluasi:** Staging & UAT Ready; Production Deployment Gated  

---

## 1. Matriks Evaluasi Status Kesiapan (Readiness Classification)

Untuk mencegah klaim prematur mengenai kesiapan sistem, status proyek diklasifikasikan ke dalam 3 gerbang (*gates*) yang terpisah secara tegas:

| Gerbang Evaluasi | Status Proyek | Makna dan Kriteria Pembuktian |
| :--- | :---: | :--- |
| **1. Kesiapan Staging (Staging Readiness)** | **SIAP (READY)** | Seluruh kode backend (162 tests passed, 928 assertions), mobile app (53 tests passed, 0 issues), 25 skema migrasi database (49 tabel), isolasi storage privat, dan integrasi antar-modul telah terbukti stabil dan siap dipasang pada server staging. |
| **2. Kesiapan UAT (UAT Readiness)** | **SIAP (READY)** | Instrumen checklist pengujian pengguna terstruktur untuk 4 peran (*Owner, Resepsionis, Staf Operasional, Penyewa*) telah dirumuskan pada `docs/phase10-uat-checklist.md` dan siap dijalankan oleh perwakilan pengguna nyata. |
| **3. Kesiapan Produksi (Production Readiness)** | **BELUM SIAP (GATED)** | **DILARANG deploy ke server produksi komersial** sebelum pengujian multi-user pada instance MySQL/Postgres staging diselesaikan, UAT ditandatangani oleh Pemilik Kos, dan Saldo Awal Keuangan (*Opening Balances*) diinput secara sah. |

---

## 2. Mengapa Deployment Produksi Masih Tertahan (Gating Rationale)

Sesuai prinsip kehati-hatian operasional dan rekayasa perangkat lunak:

1. **Uji Konkurensi Target Driver Riil:**
   Pengujian otomatis saat ini menggunakan SQLite (file-level locking). Meskipun seluruh batasan transaksi `DB::transaction` dan `lockForUpdate()` telah terpasang, konkurensi multi-koneksi paralel simultan pada engine MySQL InnoDB / PostgreSQL wajib dibuktikan terlebih dahulu pada server staging.
2. **Pengesahan UAT oleh Pengguna Manusia:**
   Pengembang dilarang menandatangani UAT atas nama pengguna. Kelayakan alur pemesanan, verifikasi pembayaran manual, dan laporan akuntansi harus disetujui langsung oleh Pemilik Properti dan staf operasional.
3. **Pencatatan Saldo Awal (Opening Balance):**
   Modul Akuntansi (Bagan Akun / COA dan Jurnal) tidak boleh langsung menerima transaksi komersial tanpa pencatatan modal awal, saldo kas fisik, saldo rekening bank, dan daftar uang jaminan penyewa yang sedang berjalan (*existing active deposits*).
4. **Penerbitan Build Rilis Mobile:**
   Aplikasi Flutter memerlukan *signing key* (Android Keystore / Apple Developer Certificate) dan penyetelan domain staging/produksi pada `ApiEndpoints.baseUrl` sebelum dapat dipublikasikan ke Play Store atau App Store.

---

## 3. Daftar Risiko Residual & Mitigasi Operasional

| No | Risiko Residual | Dampak | Tindakan Mitigasi Terencana |
| :---: | :--- | :--- | :--- |
| **R-1** | Worker Queue / Daemon Scheduler mati di server | Notifikasi email lambat; tugas cron tidak jalan | Pasang Supervisor atau Systemd unit dengan `autorestart=true` dan alert via health check. |
| **R-2** | Keterlambatan verifikasi transfer manual oleh staf | Penyewa menunggu aktivasi kamar terlalu lama | Sediakan notifikasi antrian verifikasi pada web dashboard (Badge Counter) dan Right-Side Drawer. |
| **R-3** | Selisih kas fisik vs pencatatan sistem | Laporan keuangan tidak akurat | Jalankan fitur Rekonsiliasi Kas/Bank di `/dashboard/accounting/reports/reconciliation` secara mingguan. |
| **R-4** | Kehilangan berkas privat akibat kerusakan disk | Hilangnya foto bukti bayar dan kuitansi | Aktifkan sinkronisasi harian folder `storage/app/private/` ke Cloud Object Storage (S3 / R2) dengan enkripsi. |

---

## 4. Keputusan dan Masukan yang Diperlukan dari Pemilik Sistem (*Owner*)

Sebelum peluncuran komersial (*Go-Live*), Pemilik Properti **wajib memberikan persetujuan dan masukan atas**:

1. **Konfirmasi Target RPO & RTO:**
   Menyetujui rekomendasi target pemulihan: **RPO < 1 Jam** dan **RTO < 2 Jam** sesuai dokumen [`docs/phase9-backup-recovery.md`](file:///Users/ameliavirismanda/Documents/Mas%20Huda/kos_baru/docs/phase9-backup-recovery.md) dan bukti empiris [`docs/phase10-backup-restore-evidence.md`](file:///Users/ameliavirismanda/Documents/Mas%20Huda/kos_baru/docs/phase10-backup-restore-evidence.md).
2. **Penyediaan Server & Kredensial Staging:**
   Menyediakan akses server Linux (Ubuntu/Debian) dengan IP/Domain staging, kredensial basis data MySQL/Postgres baru, dan akun Mailtrap untuk pengujian email.
3. **Penyusunan Data Saldo Awal Keuangan:**
   Menyiapkan neraca saldo awal per tanggal cut-off (Kas di Tangan, Bank BCA/Mandiri, Uang Muka Sewa, dan Uang Jaminan Penyewa Aktif).
4. **Penunjukan Personel Penguji UAT:**
   Menunjuk 1 orang perwakilan pemilik/manajer, 1 resepsionis, 1 teknisi/staf kebersihan, dan 1 penyewa untuk mencoba sistem di server staging.
5. **Penandatanganan Dokumen Go-Live Sign-Off:**
   Memberikan persetujuan akhir tertulis setelah seluruh checklist UAT berstatus Lulus.

---

## 5. Rekomendasi Urutan Langkah Selanjutnya (Actionable Next Steps)

1. **Langkah 1 (Infrastruktur Staging):**
   Deploy kode ke server staging menggunakan skrip panduan pada [`docs/phase10-staging-deployment-audit.md`](file:///Users/ameliavirismanda/Documents/Mas%20Huda/kos_baru/docs/phase10-staging-deployment-audit.md) dan jalankan migrasi database ke instance MySQL/MariaDB.
2. **Langkah 2 (Pengujian Konkurensi Staging):**
   Jalankan simulasi beban pemesanan dan mutasi deposit simultan pada database staging untuk memvalidasi performa InnoDB row-level locking.
3. **Langkah 3 (Pelaksanaan UAT):**
   Bagikan instrumen [`docs/phase10-uat-checklist.md`](file:///Users/ameliavirismanda/Documents/Mas%20Huda/kos_baru/docs/phase10-uat-checklist.md) kepada para penguji dan dokumentasikan seluruh masukan.
4. **Langkah 4 (Opening Balance & Cut-Over):**
   Input saldo awal akuntansi pada database produksi yang bersih.
5. **Langkah 5 (Peluncuran Resmi / Go-Live):**
   Arahkan domain komersial ke server produksi dan rilis aplikasi Flutter ke toko aplikasi resmi.
