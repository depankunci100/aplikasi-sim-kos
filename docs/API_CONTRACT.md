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

---

### I. Inspeksi Kamar, Checklist & Bukti Foto (`/inspections`)

| Method | Endpoint | Deskripsi | Akses |
| :--- | :--- | :--- | :--- |
| `GET` | `/inspections` | Daftar data inspeksi (filter: `status`, `property_id`, `room_id`, `type`) | Authenticated (Tenant dibatasi kamar/booking miliknya) |
| `POST` | `/inspections` | Penjadwalan inspeksi baru (otomatis salin master asset/template checklist) | Owner, Manager, Staff, Super Admin |
| `GET` | `/inspections/{id}` | Detail inspeksi lengkap dengan checklist items, foto bukti, dan audit logs | Authenticated (Otorisasi multi-tenant & hak akses) |
| `POST` | `/inspections/{id}/start` | Mulai pelaksanaan inspeksi (status `scheduled` -> `in_progress`) | Owner, Manager, Staff pelaksana |
| `PUT` | `/inspections/{id}/items/{itemId}` | Perbarui kondisi item checklist, catatan, flag perbaikan, dan estimasi biaya | Owner, Manager, Staff pelaksana |
| `POST` | `/inspections/{id}/media` | Unggah foto bukti kondisi fisik per item/keseluruhan (multipart) | Owner, Manager, Staff pelaksana |
| `GET` | `/inspections/{id}/media/{mediaId}/stream` | Tampilkan/stream file foto bukti secara aman via backend (private disk) | Authenticated (Sesuai hak akses inspeksi) |
| `POST` | `/inspections/{id}/complete` | Selesaikan inspeksi, tetapkan ringkasan kondisi, teruskan ke penyewa | Owner, Manager, Staff pelaksana |
| `POST` | `/inspections/{id}/approve` | Konfirmasi persetujuan kondisi checklist oleh penyewa | Tenant pemilik booking |
| `POST` | `/inspections/{id}/object` | Pengajuan keberatan kondisi kamar beserta catatan spesifik oleh penyewa | Tenant pemilik booking |
| `GET` | `/inspections/{id}/compare/{baselineId}` | Komparasi komprehensif kondisi kamar (e.g. check-out vs check-in) & estimasi biaya kerusakan | Owner, Manager, Staff, Tenant |

#### Contoh Payload Penjadwalan: `POST /api/v1/inspections`
```json
{
  "property_id": 1,
  "room_id": 1,
  "booking_id": 5,
  "type": "check_in",
  "scheduled_date": "2026-10-16",
  "inspector_id": 3
}
```

#### Contoh Payload Update Item Checklist: `PUT /api/v1/inspections/{id}/items/{itemId}`
```json
{
  "condition": "damaged",
  "notes": "Remote AC pecah dan filter udara kotor",
  "repair_required": true,
  "estimated_cost": 150000
}
```

#### Contoh Payload Penyelesaian Inspeksi: `POST /api/v1/inspections/{id}/complete`
```json
{
  "overall_condition": "fair",
  "inspector_notes": "Kamar layak huni, 1 perbaikan remote AC dibutuhkan."
}
```

#### Contoh Payload Persetujuan Penyewa: `POST /api/v1/inspections/{id}/approve`
```json
{
  "notes": "Kondisi kamar sesuai dengan yang diperiksa bersama saat serah terima kunci."
}
```

#### Contoh Payload Keberatan Penyewa: `POST /api/v1/inspections/{id}/object`
```json
{
  "objection_notes": "Kran shower kamar mandi bocor dan belum diganti sesuai janji staf pengelola."
}
```

#### Contoh Response Komparasi Baseline: `GET /api/v1/inspections/2/compare/1`
```json
{
  "success": true,
  "message": "Hasil perbandingan kondisi kamar berhasil dikalkulasi.",
  "data": {
    "current_inspection": { "id": 2, "inspection_code": "INSP-20261015-002", "type": "check_out" },
    "baseline_inspection": { "id": 1, "inspection_code": "INSP-20261015-001", "type": "check_in" },
    "has_degradation": true,
    "total_damage_cost": 250000,
    "differences": [
      {
        "item_name": "Pendingin Udara (AC)",
        "category": "Elektronik",
        "baseline_condition": "good",
        "current_condition": "damaged",
        "is_degraded": true,
        "repair_required": true,
        "estimated_cost": 250000,
        "current_notes": "Tidak dingin dan ada kebocoran freon"
      }
    ]
  }
}
```

---

## 4. Utilitas, Meteran & Integrasi Invoice (Phase 4)

Semua endpoint utilitas membutuhkan token `Bearer` via Sanctum (`auth:sanctum`).

| Endpoint | Method | Role yang Diizinkan | Deskripsi |
|---|---|---|---|
| `/api/v1/utilities/meters` | GET | Semua (Terfilter) | Daftar master meter utilitas fisik |
| `/api/v1/utilities/meters` | POST | Owner, Manager, Super Admin | Daftarkan meter fisik baru |
| `/api/v1/utilities/meters/{id}` | GET | Owner, Manager, Staff, Super Admin | Detail meter & riwayat pembacaan |
| `/api/v1/utilities/meters/{id}` | PUT | Owner, Manager, Super Admin | Perbarui konfigurasi meter |
| `/api/v1/utilities/tariffs` | GET | Owner, Manager, Kasir, Super Admin | Daftar tarif utilitas per properti |
| `/api/v1/utilities/tariffs` | POST | Owner, Manager, Super Admin | Konfigurasi tarif baru dengan masa berlaku |
| `/api/v1/utilities/tariffs/effective` | GET | Semua Terotentikasi | Dapatkan tarif aktif pada tanggal tertentu |
| `/api/v1/utilities/readings` | GET | Semua (Terfilter) | Riwayat pencatatan pembacaan meter |
| `/api/v1/utilities/readings` | POST | Staff, Manager, Owner, Super Admin | Catat pembacaan meter baru (upload foto privat) |
| `/api/v1/utilities/readings/{id}` | GET | Semua yang Terotorisasi | Detail pembacaan & kalkulasi pemakaian |
| `/api/v1/utilities/readings/{id}/review` | POST | Owner, Manager, Kasir, Super Admin | Setujui atau tolak pembacaan meter |
| `/api/v1/utilities/readings/{id}/correct` | POST | Owner, Manager, Super Admin | Koreksi nilai meter dengan audit trail ketat |
| `/api/v1/utilities/readings/{id}/preview-bill` | GET | Owner, Manager, Kasir, Super Admin | Pratinjau perhitungan tagihan utilitas |
| `/api/v1/utilities/readings/{id}/generate-invoice` | POST | Owner, Manager, Kasir, Super Admin | Terbitkan tagihan ke sistem invoice existing (Idempoten) |
| `/api/v1/utilities/readings/{id}/proof-photo` | GET | Staff, Owner, Tenant Terkait | Streaming foto bukti fisik privat |
| `/api/v1/utilities/tenant/consumption` | GET | Tenant | Riwayat pemakaian utilitas kamar sendiri |

#### Contoh Payload Pendaftaran Meter: `POST /api/v1/utilities/meters`
```json
{
  "property_id": 1,
  "room_id": 10,
  "utility_type": "electricity",
  "meter_number": "PLN-101-A",
  "meter_type": "individual",
  "unit": "kWh",
  "initial_reading": 1250.00,
  "installed_at": "2026-10-15"
}
```

#### Contoh Payload Pencatatan Meter: `POST /api/v1/utilities/readings`
```http
POST /api/v1/utilities/readings HTTP/1.1
Content-Type: multipart/form-data

meter_id=1
current_meter=1330.00
reading_date=2026-10-15
proof_photo=@/path/to/meter_display.jpg
notes=Segel fisik aman, display jernih
status=pending_review
```

#### Contoh Response Detail & Kalkulasi: `GET /api/v1/utilities/readings/{id}`
```json
{
  "success": true,
  "message": "Detail pembacaan meter berhasil diambil.",
  "data": {
    "id": 12,
    "meter_id": 1,
    "previous_meter": "1250.00",
    "current_meter": "1330.00",
    "usage": "80.00",
    "rate_per_unit": "1500.00",
    "total_cost": "120000.00",
    "reading_date": "2026-10-15",
    "status": "approved",
    "is_billed": false
  }
}
```

#### Contoh Payload Penerbitan Tagihan Utilitas: `POST /api/v1/utilities/readings/{id}/generate-invoice`
```json
{
  "target_invoice_id": null
}
```
*Keterangan:* Jika `target_invoice_id` disetel `null`, sistem otomatis menerbitkan invoice utilitas mandiri (`INV-UTL-...`). Jika `target_invoice_id` diisi, line item utilitas akan dilampirkan ke invoice sewa yang sedang aktif. Upaya penagihan kedua otomatis ditolak dengan pesan error dan status 422 (Anti-Double Billing).

