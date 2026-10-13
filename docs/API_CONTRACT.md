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

### E. Tagihan & Pembayaran (`/invoices` & `/payments`) - Phase 2 Terimplementasi

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/invoices` | Daftar tagihan (Tenant: tagihan miliknya; Kasir/Owner: tagihan properti kelolaannya) | Authenticated |
| `GET` | `/invoices/{id}` | Detail rincian tagihan sewa, breakdown, sisa saldo, dan riwayat pembayaran | Authenticated (Tenant pemilik / Staff properti) |
| `POST` | `/invoices/{id}/payments` | Unggah bukti transfer manual (`multipart/form-data`) | Tenant pemilik invoice |
| `GET` | `/payments` | Daftar pengajuan pembayaran (filter `status=pending` untuk verifikasi) | Kasir, Manager, Owner |
| `GET` | `/payments/{id}` | Detail pengajuan pembayaran dan catatan audit | Kasir, Manager, Owner, Tenant pemilik |
| `GET` | `/payments/{id}/proof` | Unduh/preview file bukti transfer terotentikasi (Storage privat aman) | Kasir, Manager, Owner, Tenant pemilik |
| `POST` | `/payments/{id}/verify` | Menyetujui bukti transfer, mengurangi sisa saldo invoice secara atomik | Kasir, Manager, Owner (Dilarang verifikasi diri sendiri) |
| `POST` | `/payments/{id}/reject` | Menolak bukti transfer dengan alasan penolakan wajib | Kasir, Manager, Owner |

#### Contoh Payload Pengajuan Pembayaran: `POST /api/v1/invoices/{id}/payments`
Header: `Content-Type: multipart/form-data`
```http
amount: 1500000
payment_method: manual_transfer
payment_provider: manual_bca
sender_name: Rizky Pratama
sender_bank: BCA
proof_file: [FILE BINER JPG/PNG/PDF, Max 5MB]
```

#### Contoh Response Pengajuan: `201 Created`
```json
{
  "success": true,
  "message": "Bukti transfer berhasil diajukan dan sedang menunggu verifikasi kasir.",
  "data": {
    "id": 14,
    "payment_code": "PAY-20261013-0001",
    "invoice_id": 2,
    "amount": "1500000.00",
    "payment_method": "manual_transfer",
    "payment_provider": "manual_bca",
    "sender_name": "Rizky Pratama",
    "sender_bank": "BCA",
    "proof_url": "/api/v1/payments/14/proof",
    "status": "pending",
    "created_at": "2026-10-13T22:00:00.000000Z"
  }
}
```

#### Contoh Payload Verifikasi: `POST /api/v1/payments/{id}/verify`
```json
{
  "notes": "Mutasi rekening koran BCA 13/10/2026 klir sesuai nominal."
}
```

#### Contoh Payload Penolakan: `POST /api/v1/payments/{id}/reject`
```json
{
  "rejection_reason": "Nominal transfer pada slip mutasi kurang dari Rp 1.500.000"
}
```

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
