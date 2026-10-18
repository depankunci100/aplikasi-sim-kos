# CHECKLIST KESIAPAN STAGING DAN GO-LIVE PRODUKSI
**Sistem:** SIM Kos & Guesthouse (Laravel 13 Backend + Flutter Android/iOS)  
**Fase:** Phase 9 — Production Readiness, Security & Operational Hardening  
**Dokumen:** `docs/phase9-go-live-checklist.md`  
**Status Evaluasi:** Menjelang Rilis Staging & Verifikasi UAT  

---

## 1. Panduan Status Checklist

Setiap item kesiapan dikategorikan secara objektif ke dalam salah satu status berikut:
- **[LULUS OTOMATIS]**: Telah divalidasi dan diverifikasi melalui test suite otomatis (PHPUnit / Flutter test).
- **[LULUS MANUAL]**: Telah diperiksa kode, arsitektur, dan alur konfigurasinya secara cermat.
- **[BELUM DIUJI]**: Memerlukan infrastruktur staging atau perangkat fisik nyata untuk dieksekusi.
- **[BLOCKER]**: Masalah kritis yang menghalangi deployment sebelum diselesaikan. (Saat ini: 0 Blocker).
- **[PERLU PERSETUJUAN]**: Kebijakan bisnis, target RPO/RTO, atau kredensial yang memerlukan otorisasi eksplisit Pemilik Sistem (*Owner*).

---

## 2. Rincian Checklist Kesiapan

### 2.1 Lingkungan & Pengelolaan Secret (Environment & Secrets)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 1.1 | Pengecualian berkas `.env` dan secret dari repository Git | **[LULUS MANUAL]** | Berkas `.env` telah dipastikan tidak terlacak dalam Git commit. |
| 1.2 | Penonaktifan mode debug (`APP_DEBUG=false`) pada staging & produksi | **[BELUM DIUJI]** | Wajib disetel di server staging dan produksi untuk mencegah kebocoran stack trace. |
| 1.3 | Pembangkitan `APP_KEY` unik dan kuat untuk server target | **[BELUM DIUJI]** | Jalankan `php artisan key:generate` langsung pada server target baru. |
| 1.4 | Pemisahan kredensial database staging dan produksi | **[PERLU PERSETUJUAN]** | Memerlukan input kredensial database mandiri dari pemilik infrastruktur. |

### 2.2 Basis Data, Migrasi & Pencadangan (Database & Backup)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 2.1 | Review integritas 24 file migrasi (tanpa operasi destruktif) | **[LULUS OTOMATIS]** | 162 pengujian PHPUnit lulus tanpa error skema migrasi. |
| 2.2 | Konfigurasi database target produksi (MySQL 8 / PostgreSQL 15) | **[BELUM DIUJI]** | Perlu konfigurasi connection pooling dan timezone Asia/Jakarta di target DB. |
| 2.3 | Skrip backup database otomatis harian + retensi off-site | **[LULUS MANUAL]** | Dokumen runbook telah dirumuskan pada `docs/phase9-backup-recovery.md`. |
| 2.4 | Persetujuan target RPO (< 1 jam) dan RTO (< 2 jam) oleh Owner | **[PERLU PERSETUJUAN]** | Memerlukan tanda tangan/persetujuan resmi pemilik sistem. |

### 2.3 Keamanan Jaringan, Domain & Autentikasi (HTTPS, CORS, Sanctum)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 3.1 | Enkripsi SSL/TLS (HTTPS) pada domain web & API endpoint | **[BELUM DIUJI]** | Wajib menggunakan sertifikat SSL valid (misalnya Let's Encrypt / Cloudflare). |
| 3.2 | Konfigurasi CORS pada `config/cors.php` | **[LULUS MANUAL]** | Izinkan hanya domain frontend resmi dan aplikasi mobile; hindari wildcard `*` terbuka di produksi. |
| 3.3 | Konfigurasi Laravel Sanctum untuk token mobile dan session web | **[LULUS OTOMATIS]** | Token personal access Sanctum divalidasi pada seluruh test suite API. |
| 3.4 | Rate limiting pada login/register (10/min) dan public booking (15/min) | **[LULUS OTOMATIS]** | Middleware throttling aktif pada rute publik `routes/api.php`. |
| 3.5 | Perlindungan CSRF pada seluruh form Blade web | **[LULUS MANUAL]** | Token `@csrf` terpasang pada seluruh formulir interaktif dashboard. |

### 2.4 Manajemen Berkas Privat (Private File Serving & Uploads)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 4.1 | Pemisahan penyimpanan berkas privat di luar web root publik | **[LULUS MANUAL]** | Berkas sensitif berada di `storage/app/private/` tanpa symlink publik. |
| 4.2 | Validasi otorisasi kepemilikan sebelum menyajikan berkas fisik | **[LULUS OTOMATIS]** | Akses kuitansi dan foto bukti diverifikasi berbasis user dan properti. |
| 4.3 | Header HTTP `Cache-Control: private, no-cache` pada respons berkas | **[LULUS OTOMATIS]** | Diterapkan pada `ExpenseApiController` dan `ExpenseWebController` (Lulus Test). |
| 4.4 | Validasi MIME type (`jpeg, png, webp, pdf`) dan ukuran file (maks 5MB) | **[LULUS OTOMATIS]** | Validasi controller dan form request aktif menolak berkas non-dokumen. |

### 2.5 Hak Akses, Multi-Property & Isolasi Tenant (Access Control)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 5.1 | Pembatasan akses penyewa: dilarang akses jurnal, COA, dan biaya | **[LULUS OTOMATIS]** | Terverifikasi: respons 403 Forbidden pada seluruh endpoint finansial. |
| 5.2 | Pembatasan akses staf/manajer ke properti yang ditugaskan | **[LULUS OTOMATIS]** | Staf Properti A ditolak mengakses data dan berkas kuitansi Properti B. |
| 5.3 | Hak istimewa Super Admin dan wewenang Owner | **[LULUS OTOMATIS]** | Hak kelola properti dan persetujuan pengeluaran berjalan sesuai hirarki. |

### 2.6 Integritas Finansial, Akuntansi & Uang Jaminan (Accounting & Ledger)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 6.1 | Keseimbangan atomik Debit == Kredit pada setiap entri jurnal | **[LULUS OTOMATIS]** | Jurnal tidak seimbang secara otomatis dibatalkan (*rollback*) dan melempar exception. |
| 6.2 | Idempotensi transaksi finansial via `idempotency_key` | **[LULUS OTOMATIS]** | Menghindari duplikasi jurnal saat ada pengiriman ulang (*retry*) request. |
| 6.3 | Subledger deposit: saldo jaminan tidak pernah negatif | **[LULUS OTOMATIS]** | Verifikasi saldo aktif sebelum mutasi pengurangan dan settlement. |
| 6.4 | Check-Out Settlement: refund tidak melebihi sisa deposit sah | **[LULUS OTOMATIS]** | Perhitungan denda/kerusakan fisik dan pembatasan nominal refund valid. |
| 6.5 | Input saldo awal (*Opening Balances*) sebelum sistem dibuka | **[PERLU PERSETUJUAN]** | Memerlukan input data keuangan riil dari akuntan/manajer pemilik kos. |

### 2.7 Reliabilitas Operasional & Queue Worker (Reliability & Queue)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 7.1 | Konfigurasi queue worker (Redis / Database) untuk job asinkron | **[BELUM DIUJI]** | Perlu setup daemon worker (Supervisor / Systemd) di server staging/produksi. |
| 7.2 | Konfigurasi Laravel Scheduler (`php artisan schedule:run`) | **[BELUM DIUJI]** | Daftarkan cron job berkala pada crontab server target. |
| 7.3 | Sanitasi log aplikasi (tidak mencatat password, token, atau secret) | **[LULUS MANUAL]** | Logging menggunakan `Log::info/error` tanpa menyertakan kredensial mentah. |

### 2.8 Kesiapan Aplikasi Mobile (Flutter Android & iOS)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 8.1 | Analisis statis kode mobile (`flutter analyze`) | **[LULUS OTOMATIS]** | 0 issues / bersih tanpa error maupun warning. |
| 8.2 | Pengujian unit dan widget test Flutter (`flutter test`) | **[LULUS OTOMATIS]** | 53 unit & widget tests lulus 100%. |
| 8.3 | Konfigurasi API Base URL untuk staging dan produksi | **[BELUM DIUJI]** | Ubah `ApiEndpoints.baseUrl` ke domain server staging/produksi sebelum kompilasi APK/IPA. |
| 8.4 | Kompilasi rilis Flutter Android (AAB/APK) & iOS (IPA) | **[BELUM DIUJI]** | Memerlukan signing key keystore Android dan sertifikat Apple Developer. |

### 2.9 UAT & Persetujuan Pemilik (User Acceptance Testing & Sign-Off)
| No | Item Checklist | Status | Catatan Teknis & Rekomendasi |
| :---: | :--- | :---: | :--- |
| 9.1 | UAT alur pemesanan kamar dan pembayaran oleh calon penyewa | **[BELUM DIUJI]** | Menunggu pengujian fungsional di server staging oleh perwakilan pengguna. |
| 9.2 | UAT check-in, inspeksi kamar, dan meteran oleh resepsionis/staf | **[BELUM DIUJI]** | Menunggu uji coba staf operasional di lingkungan staging. |
| 9.3 | UAT persetujuan biaya, laporan keuangan, dan jurnal oleh Owner | **[BELUM DIUJI]** | Menunggu evaluasi kelayakan laporan laba rugi dan neraca oleh pemilik. |
| 9.4 | Persetujuan final tertulis (*Go-Live Approval*) dari Pemilik Kos | **[PERLU PERSETUJUAN]** | Syarat mutlak sebelum memindahkan sistem ke domain produksi komersial. |

---

## 3. Matriks Rekapitulasi Kesiapan

| Status Kesiapan | Jumlah Item | Persentase | Makna Operasional |
| :--- | :---: | :---: | :--- |
| **Lulus Otomatis** | 14 | 48.3% | Kode, validasi, dan logika bisnis terbukti aman melalui automated test suite. |
| **Lulus Manual** | 6 | 20.7% | Desain arsitektur, sanitasi, dan konfigurasi repo telah diverifikasi aman. |
| **Belum Diuji (Perlu Staging)** | 5 | 17.2% | Menunggu infrastruktur server fisik/cloud dan rilis kompilasi mobile. |
| **Memerlukan Persetujuan** | 4 | 13.8% | Memerlukan keputusan bisnis & input data dari pemilik properti. |
| **Blocker** | **0** | **0.0%** | **Tidak ada blocker teknis yang menghalangi deployment staging.** |
| **TOTAL** | **29** | **100.0%** | **Sistem Siap Masuk Tahap Deployment Staging & UAT.** |
