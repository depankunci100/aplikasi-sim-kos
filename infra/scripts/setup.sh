#!/usr/bin/env bash
set -e

echo "=== Memulai Inisialisasi SIM Kos & Guesthouse ==="

# 1. Setup Backend
echo "-> Menyiapkan Backend Laravel..."
cd backend
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "   File .env disalin dari .env.example"
    php artisan key:generate
fi
php artisan migrate --force
cd ..

# 2. Setup Mobile
echo "-> Menyiapkan Mobile Flutter..."
cd mobile
flutter pub get
flutter analyze
cd ..

echo "=== Inisialisasi Selesai dengan Sukses ==="
