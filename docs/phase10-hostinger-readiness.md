# LAPORAN KESIAPAN DEPLOYMENT HOSTINGER SHARED HOSTING
## SIM Kos & Guesthouse — Laravel 13, Blade + Bootstrap 5, Flutter Mobile

Dokumen ini menyajikan audit menyeluruh mengenai kesiapan infrastruktur hosting, dependensi lingkungan, tata kelola database, hak akses file, dan prosedur operasional untuk deployment aplikasi pada **Hostinger Web Hosting / Shared Hosting**.

---

## 1. PROFIL ARSITEKTUR & BATASAN SHARED HOSTING

Layanan Hostinger Web Hosting (Paket Premium / Business / Cloud Startup) beroperasi dalam lingkungan multi-tenant berbasis CloudLinux dengan pembatasan sumber daya (*resource limits*):
1. **Tidak Ada Hak Akses Root**:
   Tidak dapat menginstal paket sistem operasi mandiri via `apt-get` atau memodifikasi konfigurasi Apache/Nginx global (`/etc/apache2/`).
2. **Tidak Ada Daemon Background Persisten**:
   Service supervisor seperti `supervisord`, `systemd`, atau worker antrean terus-menerus (`php artisan queue:work`) dibatasi oleh timeout proses Linux. Antrean harus dikonfigurasi menggunakan driver `sync` atau diproses via scheduled task (`queue:work --stop-when-empty`).
3. **Batas Eksekusi & Memori**:
   - `max_execution_time`: Umumnya 60 - 120 detik.
   - `memory_limit`: 512 MB - 1024 MB.
   - `upload_max_filesize`: Wajib dinaikkan minimal 20 MB pada PHP Configuration hPanel untuk mengakomodasi upload foto inspeksi dan KTP.

---

## 2. PERSYARATAN LINGKUNGAN RUNTIME (PHP & EXTENSIONS)

Sistem Laravel 13 dan seluruh dependensi modul Phase 1–10 membutuhkan spesifikasi PHP:

| Komponen | Spesifikasi Wajib | Ketersediaan di Hostinger hPanel | Rekomendasi Konfigurasi |
|---|---|---|---|
| **Versi PHP** | **PHP 8.3** (Web & CLI) | Tersedia di menu *PHP Configuration* | Set default PHP ke versi **8.3** |
| `pdo_mysql` | Wajib (Koneksi Database) | Tersedia & Aktif | Wajib dicentang |
| `openssl` | Wajib (Enkripsi AES-256) | Tersedia & Aktif | Wajib untuk enkripsi NIK |
| `mbstring` | Wajib (String manipulation) | Tersedia & Aktif | Wajib untuk validasi |
| `tokenizer` | Wajib (Blade compilation) | Tersedia & Aktif | Wajib |
| `xml` / `ctype` / `json` | Wajib (REST API Engine) | Tersedia & Aktif | Wajib |
| `bcmath` | Wajib (Perhitungan Finansial) | Tersedia di PHP Extensions | Wajib dicentang |
| `fileinfo` | Wajib (Validasi MIME Upload) | Tersedia di PHP Extensions | Wajib untuk upload foto |
| `gd` atau `imagick` | Wajib (Manipulasi Foto) | Tersedia di PHP Extensions | Wajib untuk kompresi foto |
| `zip` | Wajib (Ekspor Laporan & Composer) | Tersedia di PHP Extensions | Wajib |

---

## 3. TATA KELOLA DATABASE MYSQL 8 & STAGING

### 3.1. Versi Database & Collation
- **Versi**: Hostinger menyediakan MySQL 8.0.x atau MariaDB 10.11+. Keduanya kompatibel penuh dengan ANSI SQL migration SIM Kos.
- **Karakter & Collation**: Wajib menggunakan `utf8mb4` dan `utf8mb4_unicode_ci` untuk mendukung karakter lengkap dan emojis.
- **SQL Mode**: Pastikan `STRICT_TRANS_TABLES` dan `NO_ENGINE_SUBSTITUTION` aktif.

### 3.2. Pembuatan Database Staging Terpisah
Untuk memenuhi aturan tata kelola tanpa mencemari produksi:
1. Buat database staging terpisah di hPanel:
   - Nama Database: `uXXXXXX_simkos_staging`
   - User Database: `uXXXXXX_stag_user`
   - Hak Akses: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER`, `LOCK TABLES`.
2. **Karantina Produksi**: Kredensial database staging **DILARANG** memiliki akses ke database produksi.
3. **Status Audit Saat Ini**:
   Karena environment staging MySQL 8 belum dibuat di Hostinger oleh tim operasional, pengujian konkurensi live di server Hostinger berstatus **BLOCKED**.

---

## 4. TATA KELOLA STRUKTUR DIREKTORI & DOCUMENT ROOT

Secara default, web root Hostinger mengarah ke folder `/public_html`. Laravel menuntut agar document root mengarah ke folder `/public` demi keamanan berkas `.env` dan kode sumber.

### Strategi Deployment yang Direkomendasikan:

```text
/home/uXXXXXX/
├── public_html/              <-- Subdomain atau Document Root diarahkan ke sini
│   ├── .htaccess             <-- URL Rewrite Engine ke folder public
│   ├── index.php             <-- Bootstrap loader Laravel
│   ├── assets/               <-- Berkas CSS, JS, Gambar publik
│   └── storage/              <-- Symlink ke ../kos_project/storage/app/public
│
└── kos_project/              <-- Core Laravel diletakkan di LUAR public_html
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── routes/
    ├── storage/              <-- Berkas privat (KTP, inspeksi) aman dari akses web
    ├── vendor/
    └── .env                  <-- TERISOLASI dari jangkauan publik browser
```

#### Pengaturan `.htaccess` Keamanan di Web Root:
Pastikan berkas sensitif diblokir secara eksplisit:
```apache
<Files .env>
    Order allow,deny
    Deny from all
</Files>
Options -Indexes
```

### Izin Berkas (File Permissions):
- Direktori umum: `755`
- Berkas PHP/skrip: `644`
- Folder yang membutuhkan izin tulis (`chmod -R 775`):
  - `storage/`
  - `storage/app/private/`
  - `storage/framework/`
  - `storage/logs/`
  - `bootstrap/cache/`

---

## 5. PENJADWALAN TUGAS (CRON JOB / SCHEDULER)

Laravel Task Scheduling (`schedule:run`) menangani kadaluarsa pemesanan, pengecekan jatuh tempo invoice, dan rotasi log.

### Konfigurasi Cron Job di Hostinger hPanel:
1. Masuk ke **hPanel > Advanced > Cron Jobs**.
2. Pilih tipe: **Custom**.
3. Waktu eksekusi: Setiap Menit (`* * * * *`).
4. Perintah (Command):
   ```bash
   /usr/bin/php8.3 /home/uXXXXXX/kos_project/artisan schedule:run >> /dev/null 2>&1
   ```

---

## 6. SISTEM PENGIRIMAN EMAIL (RESOLUSI BLOCKER PASSWORD RESET)

Pada Phase 10 Post-Implementation Audit, pengiriman tautan reset password dinyatakan sebagai **BLOCKER** karena server SMTP belum terhubung.

### Solusi Email Hostinger:
Hostinger Shared Hosting menyediakan **Hostinger Business Email** gratis per domain. Setelah domain utama aktif, konfigurasikan `.env` Laravel:
```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@namadomainkos.com
MAIL_PASSWORD=********
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@namadomainkos.com"
MAIL_FROM_NAME="SIM Kos & Guesthouse"
```
Setelah parameter ini dipasang di Hostinger, blocker pemulihan kata sandi otomatis tuntas dan link reset dapat diterima penyewa tanpa campur tangan admin.

---

## 7. MATRIKS KESIAPAN & DAFTAR BLOCKER (GO-LIVE PREREQUISITES)

| Item Verifikasi | Status | Tindakan yang Dibutuhkan |
|---|:---:|---|
| **Kode Sumber Backend (Laravel 13)** | **READY (100%)** | 193 unit/feature tests lulus tanpa kegagalan |
| **Kode Sumber Mobile (Flutter)** | **READY (100%)** | 59 tests lulus, APK berhasil dikompilasi |
| **Enkripsi NIK & Blind Index** | **READY (100%)** | Schema `CHAR(64) UNIQUE` & cast `encrypted` terverifikasi |
| **Route Security & whereNumber** | **READY (100%)** | Bebas benturan segment statis vs dinamis |
| **PHP 8.3 & Ekstensi Hostinger** | **READY (100%)** | Didukung penuh oleh hPanel Hostinger |
| **Domain Resmi** | **PENDING** | Belum dibeli/diarahkan ke nameserver Hostinger |
| **Staging MySQL 8 Database** | **BLOCKER** | Belum dibuat di hPanel Hostinger |
| **Kredensial SMTP Hostinger** | **BLOCKER** | Menunggu tersedianya domain untuk aktivasi email |
| **Sertifikat SSL / HTTPS** | **PENDING** | Otomatis aktif setelah domain terhubung ke Hostinger |
| **Pengujian Hardware Kamera Fisik** | **PENDING** | Siap diuji menggunakan `sim-kos-debug.apk` di HP fisik |

---

## 8. REKOMENDASI TAHAPAN OPERASIONAL BERIKUTNYA

1. **Tahap 1: Registrasi Domain & Penyiapan Hosting**:
   Beli dan arahkan domain utama ke nameserver Hostinger. Buat subdomain `staging.namadomain.com` untuk uji coba pra-rilis.
2. **Tahap 2: Provisi Database Staging di hPanel**:
   Buat database `simkos_staging` terisolasi di hPanel.
3. **Tahap 3: Migrasi Staging & Eksekusi Smoke Test**:
   Jalankan migration `php artisan migrate` pada database staging Hostinger dan verifikasi seluruh modul Phase 1–10.
4. **Tahap 4: Aktivasi SMTP Email**:
   Buat akun email `noreply@namadomain.com` di hPanel dan isi kredensial SMTP di `.env` staging untuk membuka blokir fitur pemulihan kata sandi.
5. **Tahap 5: Pengujian UAT HP Fisik**:
   Bagikan file `sim-kos-debug.apk` ke staf penguji untuk validasi pemindaian kamera KTP dan alur penyewa.
