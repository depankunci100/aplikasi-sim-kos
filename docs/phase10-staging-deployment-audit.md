# AUDIT KESIAPAN DEPLOYMENT SERVER STAGING
**Fase:** Phase 10 — Staging Deployment, End-to-End Validation & UAT  
**Sistem:** SIM Kos & Guesthouse (Laravel 13 REST API + Blade Dashboard + Flutter Mobile)  
**Dokumen:** `docs/phase10-staging-deployment-audit.md`  
**Status Evaluasi:** Tervalidasi & Siap untuk Konfigurasi Infrastruktur Staging  

---

## 1. Audit Baseline dan Perlindungan Perubahan Repositori

### 1.1 Kondisi Repositori Terverifikasi
- **Branch Aktif:** `feature/booking-end-to-end`
- **HEAD Commit:** `0aa84952cd0e4f26be1d7ca407ee9ab19eb1905e` (`feat(ui): redesign web dashboard layout from top navbar to modern collapsible vertical sidebar`)
- **Status Working Tree:**
  - 32 berkas termodifikasi (*tracked*) dari Fase 1–9.
  - 50 entri direktori/berkas baru (*untracked*) dari Fase 7–9.
  - Seluruh pekerjaan lokal dipertahankan tanpa ada file yang terhapus, tertimpa, di-stash, atau di-reset.
- **Pengecekan Kebocoran Kredensial:**
  - Berkas `.env` lokal tidak terlacak pada Git index.
  - Seluruh token, kunci privat SSL, dan kata sandi berada di luar repository.

---

## 2. Persyaratan Lingkungan Server Staging

Berdasarkan audit dependensi aplikasi, server staging harus memenuhi spesifikasi berikut:

### 2.1 Runtime & Bahasa Pemrograman
| Komponen | Versi Minimal | Versi Teruji | Keterangan |
| :--- | :---: | :---: | :--- |
| **PHP** | 8.4.0+ | **8.4.18 (CLI / FPM)** | Menggunakan Laravel Herd / Docker PHP 8.4 FPM Alpine. |
| **Composer** | 2.8.0+ | **2.8.x** | Package manager dependensi backend. |
| **Flutter SDK** | 3.24.0+ | **3.24.3 (Dart 3.5.3)** | Diperlukan untuk kompilasi client mobile. |
| **Web Server** | Nginx 1.25+ / Caddy 2 | **Nginx Alpine** | Reverse proxy dengan dukungan HTTP/2 dan TLS 1.3. |
| **Process Manager** | Supervisor / Systemd | **Systemd / Supervisor** | Menjaga daemon queue worker dan cron scheduler tetap hidup. |

### 2.2 Ekstensi PHP Wajib
Server staging **harus mengaktifkan** modul PHP berikut (seluruhnya telah terverifikasi aktif pada environment lokal):
- `bcmath` (Kalkulasi akuntansi desimal presisi tinggi tanpa floating-point roundoff).
- `pdo_mysql` / `pdo_pgsql` (Driver database relasional target produksi).
- `mbstring`, `intl`, `xml`, `dom` (Format teks multibahasa dan rendering template Blade).
- `openssl`, `sodium` (Enkripsi token Sanctum dan password hash Argon2id/Bcrypt).
- `gd` / `imagick` / `exif` (Pemrosesan dan sanitasi foto bukti bayar/meteran/inspeksi).
- `zip` (Ekstraksi arsip dan backup).
- `redis` (Cache layer, session driver, dan queue broker).
- `pcntl`, `posix` (Manajemen sinyal proses worker queue).

---

## 3. Konfigurasi Lingkungan & Pemisahan Staging

### 3.1 Templat Variabel Lingkungan Staging (`.env.staging`)
Administrator sistem atau pemilik server wajib mengisi berkas `.env` pada server staging dengan panduan parameter berikut:

```dotenv
# ==============================================================================
# PENGATURAN APLIKASI STAGING (JANGAN MENGAKTIFKAN DEBUG DI PRODUKSI)
# ==============================================================================
APP_NAME="SIM Kos & Guesthouse (Staging)"
APP_ENV=staging
APP_KEY=base64:GENERATE_VIA_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://staging.simkos.yourdomain.com

# ==============================================================================
# LOGGING & AUDIT TRAIL
# ==============================================================================
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null

# ==============================================================================
# BASIS DATA STAGING (ISOLASI DARI LOKAL & PRODUKSI)
# ==============================================================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simkos_staging
DB_USERNAME=simkos_staging_user
DB_PASSWORD=ISI_DENGAN_PASSWORD_DATABASE_KUAT_ACAK

# ==============================================================================
# CACHE, SESSION & QUEUE (REDIS ISOLATED DB)
# ==============================================================================
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=ISI_DENGAN_PASSWORD_REDIS_KUAT
REDIS_PORT=6379
REDIS_DB=1
REDIS_CACHE_DB=2

# ==============================================================================
# SANCTUM & CORS
# ==============================================================================
SANCTUM_STATEFUL_DOMAINS=staging.simkos.yourdomain.com
CORS_ALLOWED_ORIGINS=https://staging.simkos.yourdomain.com

# ==============================================================================
# FILE SYSTEM & STORAGE PRIVAT
# ==============================================================================
FILESYSTEM_DISK=local

# ==============================================================================
# EMAIL GATEWAY (SANDBOX / MAILTRAP UNTUK STAGING)
# ==============================================================================
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=ISI_USER_MAILTRAP
MAIL_PASSWORD=ISI_PASS_MAILTRAP
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@staging.simkos.yourdomain.com"
MAIL_FROM_NAME="SIM Kos Staging"
```

> [!IMPORTANT]
> Jangan pernah menggunakan kredensial database produksi untuk server staging. Database staging harus merupakan instance baru atau salinan data sintetis yang telah disanitasi.

---

## 4. Keamanan Jaringan, Session & Storage Privat

### 4.1 HTTPS & Trusted Proxies
- Jika server staging berada di balik Load Balancer atau Cloudflare, daftarkan IP proxy pada `bootstrap/app.php` melalui middleware `trustProxies(at: ['127.0.0.1', '10.0.0.0/8', ...])`.
- Wajib menyetel header `X-Forwarded-Proto https` pada Nginx untuk memastikan tautan rute dan URL asset dihasilkan dengan protokol HTTPS.

### 4.2 Isolasi Storage Privat (`storage/app/private/`)
- Seluruh berkas kuitansi, foto inspeksi kamar, foto meteran, dan bukti transfer pembayaran tersimpan di `storage/app/private/`.
- **Dilarang membuat symlink publik** (`php artisan storage:link`) yang menghubungkan folder privat ke `public/storage`.
- Akses berkas fisik hanya dilayani via controller terotentikasi dengan header respons:
  ```http
  Cache-Control: private, no-cache, no-store, must-revalidate
  ```

---

## 5. Prosedur Deployment & Rollback Tanpa Kehilangan Data

### 5.1 Alur Deployment Staging (Zero Data Loss)
```bash
#!/usr/bin/env bash
set -e

echo "=== MEMULAI DEPLOYMENT STAGING SIM KOS ==="

# 1. Aktifkan mode pemeliharaan dengan kunci bypass
php artisan down --secret="staging-bypass-token" --render="errors::503"

# 2. Ambil snapshot backup database pra-deploy
mysqldump -u simkos_staging_user -p simkos_staging | gzip -9 > /backup/staging/pre_deploy_$(date +%Y%m%d_%H%M%S).sql.gz

# 3. Sinkronisasi kode dari branch staging
git pull origin feature/booking-end-to-end

# 4. Instal dependensi produksi (tanpa dev packages)
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Eksekusi migrasi skema (bersifat aditif / non-destruktif)
php artisan migrate --force

# 6. Optimasi cache Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Restart daemon queue worker
php artisan queue:restart

# 8. Nonaktifkan mode pemeliharaan
php artisan up

echo "=== DEPLOYMENT STAGING SELESAI ==="
```

### 5.2 Prosedur Rollback Darurat
Jika migrasi atau versi kode baru menimbulkan error pada staging:
1. Aktifkan kembali mode maintenance (`php artisan down`).
2. Rollback migrasi terakhir: `php artisan migrate:rollback --step=1`.
3. Jika struktur database rusak, restore snapshot backup:
   ```bash
   gunzip < /backup/staging/pre_deploy_YYYYMMDD_HHMMSS.sql.gz | mysql -u simkos_staging_user -p simkos_staging
   ```
4. Kembalikan kode ke tag/commit sebelumnya dan bersihkan cache (`php artisan optimize:clear`).
5. Buka kembali sistem (`php artisan up`).
