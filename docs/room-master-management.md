# Dokumentasi Master Kamar SIM Kos & Guesthouse

Dokumen ini menjelaskan arsitektur teknis, skema basis data, business rules, proteksi data operasional, audit trail perubahan, serta rancangan UI/UX Right-Side Drawer untuk pengelolaan Master Kamar pada SIM Kos & Guesthouse.

---

## 1. Arsitektur Data & Model Relasi

### 1.1 Skema Data Ruang & Tipe Kamar
Master kamar dirancang dengan memadukan standarisasi tipe kamar (`room_types`) dan fleksibilitas spesifikasi individual kamar (`rooms`).

- **`properties`**: Induk multi-tenancy properti/cabang kos.
- **`room_types`**: Klasifikasi umum kamar (Deluxe, Standard, VIP) yang menyediakan nilai default fasilitas dan harga dasar.
- **`rooms`**: Entitas unit kamar fisik spesifik yang mendukung kustomisasi harga, kapasitas, ukuran, dan fasilitas khusus.
- **`room_change_histories`**: Catatan riwayat audit setiap mutasi data pada unit kamar.
- **`assets`**: Hubungan 1-to-many ke inventaris/aset tetap yang ditempatkan di kamar fisik tersebut.
- **`bookings`**: Hubungan 1-to-many ke transaksi sewa/reservasi tamu.

### 1.2 Struktur Kolom Tabel `rooms` (Pasca Migrasi)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT (PK) | Identifier unit kamar |
| `property_id` | BIGINT (FK) | Relasi ke properti/cabang kos |
| `room_type_id` | BIGINT (FK) | Relasi ke tipe kamar induk |
| `room_number` | VARCHAR(50) | Nomor/label unik kamar per properti |
| `floor` | INT | Lantai fisik kamar |
| `building` | VARCHAR(100) | Gedung/sayap/blok (contoh: "Gedung Utama", "Paviliun B") |
| `size_m2` | DECIMAL(8,2) | Luas kamar dalam meter persegi |
| `capacity` | INT | Daya tampung maksimal penghuni |
| `status` | ENUM | `AVAILABLE`, `OCCUPIED`, `MAINTENANCE`, `DIRTY`, `RESERVED` |
| `is_active` | BOOLEAN | Status aktifitas kamar di sistem (default: `true`) |
| `price_daily` | DECIMAL(15,2) | Tarif harian kustom (nullable, fallback ke `room_type`) |
| `price_monthly` | DECIMAL(15,2) | Tarif bulanan kustom (nullable, fallback ke `room_type`) |
| `price_yearly` | DECIMAL(15,2) | Tarif tahunan kustom (nullable) |
| `deposit_amount` | DECIMAL(15,2) | Deposit jaminan sewa (nullable) |
| `down_payment_amount` | DECIMAL(15,2) | Standar DP pemesanan (nullable) |
| `facilities` | JSON | Daftar fasilitas spesifik unit kamar (array of strings) |
| `photos` | JSON | Daftar URL/path foto fisik unit kamar (array of strings) |
| `description` | TEXT | Deskripsi detail kelebihan/kondisi kamar |
| `internal_notes` | TEXT | Catatan internal khusus staf/manajemen |

---

## 2. Skema Audit Trail: `room_change_histories`

Setiap perubahan informasi strategis kamar dicatat secara otomatis melalui `RoomService` ke dalam tabel `room_change_histories` untuk menjaga akuntabilitas audit operasional:

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT (PK) | Identifier entri riwayat |
| `room_id` | BIGINT (FK) | Relasi ke kamar yang diubah |
| `user_id` | BIGINT (FK) | Akun staf/manajer/owner yang melakukan perubahan |
| `field_changed` | VARCHAR(50) | Nama atribut yang berubah (`room_number`, `price_monthly`, `status`, dll) |
| `old_value` | TEXT | Nilai sebelum modifikasi |
| `new_value` | TEXT | Nilai sesudah modifikasi |
| `change_reason` | VARCHAR(255) | Alasan pembaruan data |
| `metadata` | JSON | Konteks tambahan (IP address, user agent, snapshot) |
| `created_at` | TIMESTAMP | Waktu mutasi data |

---

## 3. Business Rules & Perlindungan Integritas

### 3.1 Pencegahan Deaktivasi Kamar Aktif (Booking Safety Guard)
Kamar yang sedang dihuni atau memiliki reservasi yang terkonfirmasi tidak boleh dinonaktifkan (`is_active = false`) atau dihapus:
- Metode `hasActiveBooking()` pada model `Room` memeriksa apakah ada catatan booking dengan status:
  - `BookingStatus::ACTIVE` (Tamu sedang menghuni kamar)
  - `BookingStatus::OVERSTAY` (Tamu melewati masa sewa dan masih menempati)
  - `BookingStatus::CONFIRMED` (Reservasi telah disetujui dan menunggu check-in)
- Jika terdeteksi booking aktif, sistem melempar `ValidationException` dengan pesan:
  *"Kamar tidak dapat dinonaktifkan atau dihapus karena sedang memiliki booking aktif atau terkonfirmasi."*

### 3.2 Keunikan Nomor Kamar Terisolasi Properti
Nomor kamar bersifat unik per properti:
```php
Rule::unique('rooms', 'room_number')
    ->where(fn($q) => $q->where('property_id', $propertyId))
    ->ignore($room->id ?? null);
```
Kamar "101" dapat hadir di Properti Melati dan Properti Mawar secara berdampingan tanpa konflik data.

### 3.3 Dynamic Fallback Atribut Harga & Fasilitas
Untuk mempermudah manajemen properti besar, kamar mewarisi tarif dan fasilitas dari `room_type` jika kolom di tingkat `room` bernilai null:
- `$room->effective_price_monthly`: mengambil `price_monthly` kamar fisik; jika null, otomatis menggunakan `roomType->base_price`.
- `$room->effective_facilities`: menggabungkan fasilitas standar tipe kamar dengan fasilitas tambahan unik kamar fisik.

---

## 4. UI/UX: Right-Side Drawer & Tab Detail

Antarmuka web dashboard mengadopsi standar modern desktop & tablet:

### 4.1 Right-Side Drawer Form (`#roomDrawer`)
- Menggunakan Bootstrap 5 Offcanvas berposisi kanan (`offcanvas-end`) dengan lebar responsif (650px pada layar desktop).
- Memungkinkan penambahan atau pengubahan spesifikasi kamar tanpa kehilangan konteks daftar kamar di layar utama.
- Dilengkapi preview fasilitas dinamis (tag badges) dan pemisah tab formulir (Spesifikasi Dasar, Penentuan Tarif, Fasilitas & Media).

### 4.2 Multi-Tab Room Detail View (`dashboard/rooms/show.blade.php`)
Halaman detail unit kamar menyajikan 5 tab terstruktur:
1. **Spesifikasi & Detail Fisik**: Nomor kamar, gedung, lantai, dimensi (m²), kapasitas, status operasional, dan catatan internal.
2. **Fasilitas & Galeri Foto**: Tampilan grid visual fasilitas bawaan serta galeri foto kamar dengan badge indikator.
3. **Riwayat Perubahan (Audit Trail)**: Tabel kronologis mutasi nomor kamar, tarif, status ketersediaan, waktu kejadian, dan identitas staf pengubah.
4. **Riwayat Booking & Tamu**: Daftar riwayat penghuni sebelumnya, booking berjalan, tanggal mulai-selesai, dan status pembayaran/reservasi.
5. **Aset & Inventaris Terkait**: Daftar seluruh barang inventaris (AC, Kasur, TV, Meja) yang terdaftar di kamar tersebut lengkap dengan kondisi fisik dan tautan detail aset.
