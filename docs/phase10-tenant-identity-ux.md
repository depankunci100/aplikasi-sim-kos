# SIM Kos & Guesthouse — Tenant Identity & Profile UX Upgrade

## 1. Ringkasan Eksekutif & Prinsip Desain
Dokumen ini menetapkan standar arsitektur dan pengalaman pengguna (UX) untuk pengelolaan data identitas penyewa pada sistem SIM Kos & Guesthouse. Sistem menerapkan prinsip **Privacy by Design**, di mana kerahasiaan Nomor Induk Kependudukan (NIK) dan dokumen foto identitas (KTP) dilindungi secara ketat di seluruh lapisan aplikasi (Database, Backend API, Log, dan Flutter Mobile).

### Prinsip Utama:
1. **Masking NIK Menyeluruh**: Format NIK yang ditampilkan kepada pengguna dan admin selalu disamarkan (`3507********0004`). Tidak ada response JSON profil yang membocorkan plaintext NIK 16 digit.
2. **Blind Indexing (HMAC-SHA256)**: Keunikan NIK antar-penyewa divalidasi melalui blind index hash satu arah yang dihitung menggunakan Application Key rahasia (`APP_KEY`). Database tidak perlu menyimpan atau mencari NIK mentah secara terindeks publik.
3. **OCR Assistive, Bukan Mandatory**: Fitur OCR KTP berbasis on-device text recognition diletakkan sebagai **alat bantu pengisian opsional** di dalam formulir *Edit Identitas*. OCR tidak diletakkan sebagai gerbang wajib pada dashboard utama penyewa. Hasil pemindaian selalu ditampilkan dalam modal preview agar penyewa dapat memeriksa dan menyunting sebelum data diaplikasikan ke form.
4. **Penyimpanan Dokumen Privat**: Foto KTP tersimpan di disk `local` privat non-publik (`storage/app/private/ktp/{user_id}/`) dan hanya dapat diakses melalui endpoint resmi dengan otentikasi Sanctum dan otorisasi kepemilikan.

---

## 2. Struktur Data & Skema Database

Penyimpanan profil identitas penyewa menggunakan tabel `tenant_profiles` tanpa modifikasi destruktif pada struktur tabel existing:

| Kolom | Tipe Data | Keterangan & Proteksi |
|---|---|---|
| `id` | BIGINT PK | Identitas entri profil |
| `user_id` | BIGINT FK | Relasi ke tabel `users` (Unique per penyewa) |
| `ktp_number` | VARCHAR(255) | Tersimpan aman, diakses terbatas oleh service |
| `masked_nik` | VARCHAR(20) | Format disamarkan (`3507********0004`) untuk UI/API |
| `nik_hash` | VARCHAR(64) | Blind index HMAC-SHA256(`APP_KEY`, NIK) untuk pencarian & keunikan |
| `is_nik_verified` | BOOLEAN | Penanda status verifikasi identitas kependudukan |
| `nik_verified_at` | TIMESTAMP | Waktu pencatatan verifikasi NIK |
| `ktp_photo` | VARCHAR(255) | Path privat file foto KTP (`ktp/{user_id}/{filename}`) |
| `emergency_name` | VARCHAR(100) | Nama kontak darurat keluarga/wali |
| `emergency_phone` | VARCHAR(30) | Nomor telepon kontak darurat |
| `occupation` | VARCHAR(100) | Pekerjaan / profesi penyewa |
| `institution_name` | VARCHAR(100) | Nama instansi kantor / universitas |
| `vehicle_type` | ENUM | Jenis kendaraan (`motor`, `mobil`, `none`) |
| `vehicle_plate` | VARCHAR(20) | Nomor plat kendaraan bermotor |
| `created_at` / `updated_at` | TIMESTAMP | Audit timestamp rekaman profil |

---

## 3. Alur API Backend

### 3.1. Pengambilan Data Profil (`GET /api/v1/tenant/identity`)
- **Autentikasi**: Bearer Token Sanctum (Role: `tenant`, `manager`, `owner`, `super_admin`).
- **Response Format**:
```json
{
  "success": true,
  "message": "Data identitas penyewa berhasil dimuat.",
  "data": {
    "id": 1,
    "user_id": 7,
    "name": "Rizky Pratama",
    "email": "rizky@example.com",
    "phone": "08123456789",
    "masked_nik": "3507********0004",
    "is_nik_verified": true,
    "verification_status": "verified",
    "nik_verified_at": "2026-10-18T10:15:00Z",
    "updated_at": "2026-10-18T10:15:00Z",
    "has_ktp_photo": true,
    "emergency_name": "Bapak Pratama",
    "emergency_phone": "081234567890",
    "occupation": "Software Engineer",
    "institution_name": "PT Inovasi Digital",
    "vehicle_type": "motor",
    "vehicle_plate": "N 1234 ABC"
  }
}
```

### 3.2. Pembaruan Profil & Identitas (`PUT /api/v1/tenant/identity`)
- **Endpoint**: `PUT /api/v1/tenant/identity`
- **Payload Request**:
```json
{
  "name": "Rizky Pratama",
  "nik": "3507123456780004",
  "emergency_name": "Bapak Pratama",
  "emergency_phone": "081234567890",
  "occupation": "Software Engineer",
  "institution_name": "PT Inovasi Digital",
  "vehicle_type": "motor",
  "vehicle_plate": "N 1234 ABC"
}
```
- **Prosedur Validasi & Keamanan Backend**:
  1. Validasi 16 digit angka regex: `/^[0-9]{16}$/`.
  2. Perhitungan `nik_hash = hash_hmac('sha256', $cleanNik, config('app.key'))`.
  3. Cek duplikasi dengan row-locking: `TenantProfile::where('nik_hash', $nikHash)->lockForUpdate()->first()`. Jika ditemukan pada akun user lain, melempar `DomainException` (HTTP 422).
  4. Penyimpanan NIK ter-masking: `substr($cleanNik, 0, 4) . '********' . substr($cleanNik, -4)`.
  5. Audit logging: Mencatat event update dengan `masked_nik` saja, tanpa pernah mencatat raw NIK ke file log.

---

## 4. Pengalaman Pengguna (UX) Flutter Mobile

### 4.1. Kartu Identitas Kependudukan di Layar Profil (`ProfileScreen`)
- **Tampilan Elegan**: Diberi aksen container modern dengan status badge:
  - Hijau `Terverifikasi` (jika NIK sudah valid tercatat).
  - Amber `Belum Diverifikasi` (jika penyewa baru belum melengkapi identitas).
- **Rincian Data yang Ditampilkan**:
  - Nama Sesuai KTP
  - NIK (Disamarkan demi privasi pengguna: `3507********0004`)
  - Terakhir Diperbarui (Format human-readable: `18 Okt 2026`)
  - Kontak Darurat (Nama & Nomor telepon)
  - Pekerjaan / Instansi
  - Kendaraan & Nomor Plat
- **Tombol Aksi**: `Edit Data Identitas` mengarahkan penyewa ke `IdentityVerificationScreen`.

### 4.2. Layar Penyuntingan Identitas (`IdentityVerificationScreen`)
- **Dual Mode Input**:
  1. **Input Manual Langsung**: Penyewa dapat mengetikkan Nama, 16 digit NIK, Kontak Darurat, Pekerjaan, dan Plat Kendaraan secara langsung tanpa hambatan.
  2. **Bantuan Pindai OCR (Opsional)**: Tombol `Pindai Foto KTP (Bantuan Otomatis)` memanfaatkan kamera/galeri untuk mengekstrak teks NIK dan Nama.
- **Modal Konfirmasi Preview OCR**:
  - Hasil pemindaian OCR tidak pernah langsung menimpa form tanpa persetujuan.
  - Dialog pop-up menampilkan hasil bacaan (NIK yang dideteksi, Nama, dan Tingkat Keyakinan).
  - Penyewa memilih `Terapkan ke Formulir` atau `Batal/Ulangi`.
- **Anti-Duplicate Submission**:
  - Tombol simpan dinonaktifkan (`_isSubmitting = true`) saat proses pengiriman ke server sedang berlangsung untuk mencegah *double submit*.
  - Feedback visual melalui `CircularProgressIndicator` dan SnackBar interaktif.
