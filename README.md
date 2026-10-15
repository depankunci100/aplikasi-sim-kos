# SIM Kos & Guesthouse (Sistem Informasi Manajemen Properti Sewa)

[![Laravel](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)
[![Flutter](https://img.shields.io/badge/Flutter-3.24-cyan.svg)](https://flutter.dev)
[![State%20Management](https://img.shields.io/badge/Riverpod-2.6-indigo.svg)](https://riverpod.dev)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Aplikasi manajemen operasional kos dan guesthouse profesional berbasis **Laravel 13 REST API** dan **Flutter Mobile (Android/iOS)** dengan dukungan arsitektur modular, multi-cabang, otentikasi Sanctum, dan state management Riverpod.

Repository: [https://github.com/depankunci100/aplikasi-sim-kos.git](https://github.com/depankunci100/aplikasi-sim-kos.git)

---

## 📁 Struktur Direktori

```
aplikasi-sim-kos/
├── backend/                  # Laravel 13 REST API Core (PHP 8.4)
│   ├── app/                  # Controller, Models, Services, Middleware, Enums
│   ├── config/               # Konfigurasi aplikasi & database
│   ├── database/             # Migrasi, Seeder, dan Factory
│   ├── routes/               # API endpoint (api.php)
│   └── tests/                # Unit test dan Feature test
├── mobile/                   # Flutter Mobile App (Android & iOS)
│   ├── lib/
│   │   ├── core/             # Network (Dio), Storage, Theme, Constants
│   │   ├── features/         # Auth, Properties, Rooms, Bookings, Invoices
│   │   ├── routing/          # GoRouter Navigation Guard
│   │   └── main.dart         # Entrypoint aplikasi
│   └── test/                 # Widget & Unit tests
├── docs/                     # Dokumentasi Teknis Lengkap
│   ├── ARCHITECTURE.md       # Arsitektur sistem & modular pattern
│   ├── ERD.md                # Diagram relasi entitas, kamus data & indeks
│   ├── ROLES_PERMISSIONS.md  # Matriks RBAC (Superadmin, Owner, Staf, Penyewa)
│   ├── API_CONTRACT.md       # Spesifikasi OpenAPI / REST endpoint
│   ├── RENTAL_WORKFLOW.md    # Alur sewa bulanan (Kos) & harian (Guesthouse)
│   └── DECISIONS.md          # Architectural Decision Records (ADR)
├── infra/                    # Infrastruktur & Deployment
│   ├── docker/               # Dockerfile, Docker Compose, Nginx config
│   └── scripts/              # Skrip otomasi instalasi lokal
├── .gitignore                # Konfigurasi Git ignore monorepo
└── README.md                 # Dokumentasi proyek utama
```

---

## 🚀 Fitur Utama

1. **Dual Model Operasional**:
   - **Kos**: Kontrak bulanan/tahunan, penagihan recurring otomatis, uang deposit, dan pencatatan meteran listrik/air per kamar.
   - **Guesthouse**: Reservasi harian/mingguan, kalender ketersediaan dinamis, alur check-in/out cepat, dan otomasi tugas housekeeping.
2. **Keamanan & Autentikasi**:
   - Token-based API Auth dengan Laravel Sanctum.
   - Role-Based Access Control (RBAC) 6 peran: *Super Admin, Owner, Manager, Receptionist, Staff, Tenant*.
   - Isolasi data antar properti/cabang (Branch Isolation).
3. **Finansial & Pembayaran**:
   - Pembuatan tagihan terperinci (Sewa pokok, deposit, denda, pemakaian utilitas).
   - Verifikasi bukti transfer manual atau integrasi payment gateway.
   - Laporan laba/rugi, arus kas, dan tingkat keterisian (occupancy rate).
4. **Inspeksi Kamar, Checklist & Bukti Foto (Tahap 3)**:
   - Alur inspeksi terpadu: Check-in awal, berkala selama masa sewa, dan check-out akhir.
   - Master checklist otomatis dari aset kamar (`room_assets`) atau 8 butir standar fasilitas.
   - Foto bukti visual tersimpan aman di private storage dan disajikan via stream terotentikasi.
   - Hak konfirmasi persetujuan (`approve`) atau pengajuan keberatan (`object`) bagi penyewa dengan batas tenggat waktu.
   - Komparasi cerdas kondisi check-out vs baseline check-in dengan kalkulasi otomatis estimasi biaya kerusakan pemotong deposit.
   - Rekam audit trail lengkap (`inspection_logs`) untuk setiap tahapan aksi.
5. **Mobile Experience (Flutter)**:
   - UI modern berbasis Material Design 3 dengan bahasa Indonesia.
   - State management reaktif menggunakan **Riverpod**.
   - Navigasi deklaratif dengan **GoRouter**.
   - Client API tangguh menggunakan **Dio** dengan auto-retry dan interceptor token.

---

## 🛠️ Persyaratan Lingkungan (Prerequisites)

- **PHP**: ^8.4 (Laravel Herd / PHP CLI)
- **Composer**: ^2.8
- **Flutter SDK**: ^3.24.x
- **Dart SDK**: ^3.5.x
- **MySQL / MariaDB**: ^8.0 / ^10.11
- **Docker & Docker Compose** (Opsional untuk containerization)

---

## ⚡ Panduan Menjalankan Proyek (Quick Start)

### 1. Inisialisasi Otomatis (Rekomendasi)
```bash
./infra/scripts/setup.sh
```

### 2. Menjalankan Backend & Web Dashboard Secara Manual
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```
- **REST API Endpoint**: `http://127.0.0.1:8000/api/v1`
- **Web Dashboard (Bootstrap 5)**: `http://127.0.0.1:8000/dashboard` (Login di `http://127.0.0.1:8000/login`)

#### Akun Demo Default (Seeder):
| Peran | Email | Password | Akses Utama |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin@simkos.id` | `password` | Semua properti & konfigurasi global |
| **Owner** | `owner@simkos.id` | `password` | Dashboard pendapatan, kamar, verifikasi |
| **Manager** | `manager@simkos.id` | `password` | Operasional cabang Graha Simkos Malang |
| **Kasir / Resepsionis**| `receptionist@simkos.id`| `password` | Verifikasi bukti bayar & daftar invoice |
| **Staff** | `staff@simkos.id` | `password` | Status kesiapan kamar & inspeksi |
| **Penyewa (Tenant)** | `tenant@simkos.id` | `password` | Mobile App & Pengajuan bukti transfer |

### 3. Menjalankan Mobile Secara Manual
```bash
cd mobile
flutter pub get
flutter run
```

### 4. Menjalankan Pengujian Otomatis (Test Suites)
- **Backend Test Suite (34 Tests Passing)**:
  ```bash
  cd backend && php artisan test
  ```
- **Flutter Test Suite & Static Analysis (13 Tests Passing, 100% Pass)**:
  ```bash
  cd mobile
  flutter analyze
  flutter test
  ```

### 5. Menjalankan Menggunakan Docker Compose
```bash
cd infra/docker
docker compose up -d --build
```

---

## 📖 Dokumentasi Teknis

Silakan merujuk pada direktori [`docs/`](./docs) untuk rincian mendalam:
- [Arsitektur Sistem](./docs/ARCHITECTURE.md)
- [Diagram ERD & Skema Database](./docs/ERD.md)
- [Matriks Hak Akses & Peran](./docs/ROLES_PERMISSIONS.md)
- [Spesifikasi API Endpoint](./docs/API_CONTRACT.md)
- [Alur Bisnis Penyewaan](./docs/RENTAL_WORKFLOW.md)
- [Catatan Keputusan Teknis (ADR)](./docs/DECISIONS.md)

---

## 🔒 Lisensi
Hak Cipta (c) 2026. Didistribusikan di bawah Lisensi MIT.
