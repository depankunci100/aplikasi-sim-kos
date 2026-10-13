# Kontrak API & Spesifikasi OpenAPI - SIM Kos & Guesthouse

Dokumen ini mendefinisikan kontrak endpoint REST API versi 1 (`/api/v1`), standar payload, format otentikasi Sanctum, dan respons status code untuk **SIM Kos & Guesthouse**.

---

## 1. Konvensi Umum API

- **Base URL**: `https://api.kosku.id/api/v1` (Production) / `http://localhost:8000/api/v1` (Development)
- **Header Standar**:
  ```http
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer <SANCTUM_TOKEN>
  ```
- **Timezone**: Seluruh timestamp dikembalikan dalam format ISO 8601 (`YYYY-MM-DDTHH:mm:ssZ`) atau Asia/Jakarta (`+07:00`).
- **HTTP Status Code**:
  - `200 OK`: Permintaan berhasil dan mengembalikan data.
  - `201 Created`: Entitas baru berhasil dibuat.
  - `400 Bad Request`: Format request tidak valid.
  - `401 Unauthorized`: Token tidak ditemukan, kedaluwarsa, atau tidak valid.
  - `403 Forbidden`: Pengguna tidak memiliki izin role yang memadai.
  - `404 Not Found`: Data tidak ditemukan.
  - `422 Unprocessable Entity`: Validasi input gagal.
  - `500 Internal Server Error`: Kesalahan server internal tak terduga.

---

## 2. Ringkasan Endpoint Utama

### A. Autentikasi (`/auth`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `POST` | `/auth/register` | Pendaftaran akun baru (Penyewa) | Publik |
| `POST` | `/auth/login` | Login akun & penerbitan token Sanctum | Publik |
| `POST` | `/auth/logout` | Revoke token aktif saat ini | Authenticated |
| `GET` | `/auth/profile` | Ambil profil akun & role pengguna saat ini | Authenticated |
| `PUT` | `/auth/profile` | Perbarui profil, no HP, & data diri | Authenticated |
| `PUT` | `/auth/password` | Ganti password akun | Authenticated |

#### Contoh Request: `POST /api/v1/auth/login`
```json
{
  "email": "user@simkos.id",
  "password": "Password123!"
}
```

#### Contoh Response: `200 OK`
```json
{
  "success": true,
  "message": "Autentikasi berhasil.",
  "data": {
    "token": "1|qX8zK9...sanctum_token...",
    "user": {
      "id": 1,
      "name": "Budi Santoso",
      "email": "user@simkos.id",
      "phone": "081234567890",
      "role": "tenant",
      "avatar_url": null
    }
  }
}
```

---

### B. Properti & Cabang (`/properties`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/properties` | Daftar seluruh properti/cabang kos & guesthouse | Publik |
| `GET` | `/properties/{id}` | Detail properti, alamat, fasilitas, dan kontak | Publik |
| `POST` | `/properties` | Tambah cabang baru | Super Admin, Owner |
| `PUT` | `/properties/{id}` | Ubah profil/aturan cabang | Super Admin, Owner, Manager |
| `DELETE`| `/properties/{id}` | Hapus cabang (Soft Delete) | Super Admin |

---

### C. Kamar & Tipe Kamar (`/rooms`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/properties/{property_id}/room-types` | Daftar tipe kamar (VIP, Deluxe, Standard) | Publik |
| `GET` | `/properties/{property_id}/rooms` | Daftar kamar fisik beserta status ketersediaan | Publik / Staff |
| `GET` | `/rooms/{id}` | Detail inventaris dan status spesifik kamar | Authenticated |
| `PUT` | `/rooms/{id}/status` | Ubah status kamar (`available`, `dirty`, `maintenance`) | Staff, Manager |

---

### D. Pemesanan & Sewa (`/bookings`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/bookings` | Riwayat booking (difilter berdasarkan role pengguna)| Authenticated |
| `GET` | `/bookings/{id}` | Rincian lengkap booking, kamar, dan riwayat status | Authenticated |
| `POST` | `/bookings` | Buat pemesanan kos bulanan atau guesthouse harian | Authenticated (Tenant/Kasir) |
| `POST` | `/bookings/{id}/check-in` | Eksekusi check-in & aktivasi sewa | Receptionist, Manager |
| `POST` | `/bookings/{id}/check-out` | Eksekusi check-out, kalkulasi deposit & denda | Receptionist, Manager |
| `POST` | `/bookings/{id}/cancel` | Batalkan pemesanan | Tenant, Manager |

#### Contoh Payload Buat Booking Kos: `POST /api/v1/bookings`
```json
{
  "property_id": 1,
  "room_id": 12,
  "rental_type": "monthly",
  "start_date": "2026-11-01",
  "end_date": "2027-04-30",
  "notes": "Membawa 1 sepeda motor"
}
```

---

### E. Tagihan & Pembayaran (`/invoices` & `/payments`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/invoices` | Daftar tagihan aktif & riwayat | Authenticated |
| `GET` | `/invoices/{id}` | Detail rincian tagihan sewa, listrik, denda | Authenticated |
| `POST` | `/invoices/{id}/pay` | Kirim pembayaran / upload bukti transfer | Tenant, Kasir |
| `POST` | `/payments/{id}/verify`| Verifikasi bukti pembayaran (setujui / tolak) | Manager, Receptionist |

---

### F. Meteran Utilitas Listrik & Air (`/meter-readings`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/meter-readings` | Riwayat pencatatan meteran listrik/air per kamar | Staff, Manager, Tenant |
| `POST` | `/meter-readings` | Input angka meteran baru & upload foto meteran fisik | Staff, Manager |

---

### G. Tiket Komplain & Perbaikan (`/complaints`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/complaints` | Daftar tiket komplain | Authenticated |
| `POST` | `/complaints` | Buat tiket baru dengan foto fasilitas rusak | Tenant |
| `PUT` | `/complaints/{id}/status` | Update progress pengerjaan (`in_progress`, `resolved`) | Staff, Manager |

---

### H. Laporan & Keuangan (`/reports`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/reports/occupancy` | Tingkat keterisian kamar harian/bulanan | Owner, Manager |
| `GET` | `/reports/revenue` | Arus kas masuk (pendapatan sewa & guesthouse) | Owner, Manager |
| `GET` | `/reports/expenses` | Rekap pengeluaran operasional cabang | Owner, Manager |
| `GET` | `/reports/profit-loss`| Ringkasan laba bersih per properti | Owner, Super Admin |
