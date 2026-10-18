# Kontrak API Pemesanan Kamar (Booking REST API Contract)
## SIM Kos & Guesthouse (API v1)

Dokumen ini mendokumentasikan endpoint REST API publik dan terautentikasi untuk pemesanan kamar yang digunakan secara bersama oleh Website Blade dan Aplikasi Mobile Flutter.

---

## 1. Ringkasan Endpoint

| Method | Endpoint | Akses | Deskripsi & Rate Limit |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/public/properties` | Publik | Katalog properti aktif dan ringkasan kamar. |
| `GET` | `/api/v1/public/rooms/availability` | Publik | Pencarian ketersediaan kamar berdasarkan rentang tanggal. |
| `POST` | `/api/v1/public/bookings/calculate-price` | Publik | Kalkulasi estimasi biaya sewa, DP, dan deposit (Server-side). |
| `POST` | `/api/v1/public/bookings` | Publik | Pengajuan booking oleh tamu publik (`throttle:15,1`). |
| `GET` | `/api/v1/public/bookings/track/{token}` | Publik | Pelacakan status pemesanan via secure access token (`throttle:15,1`). |
| `GET` | `/api/v1/bookings` | Auth | Daftar pemesanan (terisolasi per role & properti). |
| `POST` | `/api/v1/bookings` | Auth | Pembuatan booking internal oleh staf atau tenant. |
| `GET` | `/api/v1/bookings/{id}` | Auth | Detail pemesanan lengkap beserta tagihan dan pembayaran. |
| `POST` | `/api/v1/bookings/{id}/confirm` | Staf | Konfirmasi pemesanan (mengunci status jadi `confirmed`). |
| `POST` | `/api/v1/bookings/{id}/reject` | Staf | Menolak pemesanan dengan alasan wajib. |
| `POST` | `/api/v1/bookings/{id}/cancel` | Auth | Membatalkan pemesanan dengan alasan. |
| `POST` | `/api/v1/bookings/{id}/check-in` | Staf | Memproses check-in (mengubah status ke `active` & kamar `occupied`). |

---

## 2. Rincian Endpoint Publik

### A. Cari Ketersediaan Kamar
- **Endpoint**: `GET /api/v1/public/rooms/availability`
- **Query Parameters**:
  - `start_date` (string date, required, min: today)
  - `end_date` (string date, required, after: start_date)
  - `property_id` (integer, optional)
  - `room_type_id` (integer, optional)
  - `rental_type` (string, optional: `daily`, `weekly`, `monthly`, `quarterly`, `yearly`)
- **Contoh Response 200 OK**:
```json
{
  "success": true,
  "message": "Ketersediaan kamar berhasil dicek.",
  "data": {
    "total_available": 3,
    "start_date": "2026-11-01",
    "end_date": "2026-12-01",
    "rental_type": "monthly",
    "rooms": [
      {
        "id": 1,
        "room_number": "101",
        "floor": 1,
        "property": {
          "id": 1,
          "name": "Kos Griya Sejahtera",
          "type": "kos",
          "city": "Yogyakarta",
          "address": "Jl. Kaliurang KM 5",
          "dp_required": true
        },
        "room_type": {
          "id": 1,
          "name": "Deluxe Room",
          "description": "Kamar mandi dalam, AC, WiFi",
          "facilities": ["AC", "WiFi", "Kamar Mandi Dalam"],
          "max_occupants": 2,
          "size_m2": 16.0
        },
        "pricing": {
          "days": 30,
          "rental_type": "monthly",
          "rate_amount": 1500000,
          "deposit_amount": 500000,
          "down_payment_amount": 400000,
          "total_amount": 2000000,
          "dp_required": true,
          "payment_deadline_hours": 24
        }
      }
    ]
  }
}
```

---

### B. Submit Pemesanan Guest Publik
- **Endpoint**: `POST /api/v1/public/bookings`
- **Headers**: `Content-Type: application/json`, `Accept: application/json`
- **Payload Request**:
```json
{
  "room_id": 1,
  "rental_type": "monthly",
  "start_date": "2026-11-01",
  "end_date": "2026-12-01",
  "guest_name": "Rian Pratama",
  "guest_email": "rian@example.com",
  "guest_phone": "081234567890",
  "guest_id_card": "3201234567890001",
  "notes": "Rencana tiba sore hari",
  "agree_terms": 1
}
```
- **Contoh Response 201 Created**:
```json
{
  "success": true,
  "message": "Pemesanan Anda berhasil diajukan. Simpan nomor booking dan tautan pelacakan Anda.",
  "data": {
    "booking_code": "BK-20261016-A1B2C3",
    "access_token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2",
    "status": "pending",
    "status_label": "Menunggu Pembayaran / Review",
    "expires_at": "2026-10-17T00:00:00.000000Z",
    "rate_amount": 1500000,
    "deposit_amount": 500000,
    "down_payment_amount": 400000,
    "total_amount": 2000000,
    "property": {
      "name": "Kos Griya Sejahtera",
      "phone": "081299998888",
      "address": "Jl. Kaliurang KM 5"
    },
    "room": {
      "room_number": "101",
      "type": "Deluxe Room"
    },
    "invoice": {
      "invoice_number": "INV-20261016-X9Y8Z",
      "total_amount": 2000000,
      "status": "unpaid",
      "due_date": "2026-10-17"
    },
    "tracking_url": "http://127.0.0.1:8000/booking/track/a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2"
  }
}
```

---

### C. Lacak Status Pemesanan Publik (Secure Access Token)
- **Endpoint**: `GET /api/v1/public/bookings/track/{token}`
- **Contoh Response 200 OK**:
```json
{
  "success": true,
  "message": "Status pemesanan berhasil diambil.",
  "data": {
    "booking_code": "BK-20261016-A1B2C3",
    "status": "pending",
    "status_label": "Menunggu Pembayaran / Review",
    "badge_class": "warning",
    "start_date": "2026-11-01",
    "end_date": "2026-12-01",
    "rental_type": "monthly",
    "guest_name": "Rian Pratama",
    "rate_amount": 1500000,
    "deposit_amount": 500000,
    "down_payment_amount": 400000,
    "total_amount": 2000000,
    "expires_at": "2026-10-17T00:00:00.000000Z",
    "is_expired": false,
    "property": {
      "name": "Kos Griya Sejahtera",
      "address": "Jl. Kaliurang KM 5",
      "city": "Yogyakarta",
      "phone": "081299998888"
    },
    "room": {
      "room_number": "101",
      "room_type": "Deluxe Room"
    },
    "invoice": {
      "invoice_number": "INV-20261016-X9Y8Z",
      "total_amount": 2000000,
      "paid_amount": 0,
      "status": "unpaid",
      "status_label": "Belum Bayar",
      "due_date": "2026-10-17"
    }
  }
}
```

---

## 3. Rincian Endpoint Terautentikasi (Sanctum)

### A. Konfirmasi Pemesanan
- **Endpoint**: `POST /api/v1/bookings/{id}/confirm`
- **Role**: `super_admin`, `owner`, `manager`, `receptionist`
- **Payload**: `{"notes": "DP disetor tunai di kantor"}` (opsional)

### B. Tolak Pemesanan
- **Endpoint**: `POST /api/v1/bookings/{id}/reject`
- **Role**: `super_admin`, `owner`, `manager`, `receptionist`
- **Payload**: `{"rejection_reason": "Kamar dialokasikan untuk renovasi"}` (wajib)

### C. Batalkan Pemesanan
- **Endpoint**: `POST /api/v1/bookings/{id}/cancel`
- **Payload**: `{"cancellation_reason": "Permintaan pembatalan dari pemesan"}` (wajib)

### D. Proses Check-In
- **Endpoint**: `POST /api/v1/bookings/{id}/check-in`
- **Role**: `super_admin`, `owner`, `manager`, `receptionist`, `staff`
- **Prasyarat**: Status booking harus `confirmed`.
- **Dampak**: Mengubah status booking ke `active` dan status kamar ke `occupied`.
