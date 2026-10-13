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
4. **Mobile Experience (Flutter)**:
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

### 2. Menjalankan Backend Secara Manual
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```
Backend API berjalan di `http://127.0.0.1:8000/api/v1`.

### 3. Menjalankan Mobile Secara Manual
```bash
cd mobile
flutter pub get
flutter run
```

### 4. Menjalankan Menggunakan Docker Compose
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
