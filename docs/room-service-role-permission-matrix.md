# Matriks Hak Akses & Peran (Role & Permission Matrix)
## Layanan Kamar, Housekeeping & Verifikasi Identitas Penyewa

| Aksi / Fungsi Sistem | Super Admin | Owner | Manager | Staf / Inspektor | Penyewa (Tenant) | Status Kode Jika Ditolak |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **Lihat Profil & Identitas Sendiri** | ✅ | ✅ | ✅ | ✅ | ✅ | 401 Unauthorized |
| **Lihat Masked NIK Penyewa** | ✅ | ✅ | ✅ | ❌ | ✅ (Milik Sendiri) | 403 Forbidden |
| **Update Data Profil & NIK Kependudukan** | ✅ (Override) | ❌ | ❌ | ❌ | ✅ (Milik Sendiri) | 403 Forbidden |
| **Akses Foto Fisik KTP Privat** | ✅ | ✅ | ✅ | ❌ | ✅ (Milik Sendiri) | 403 Forbidden |
| **Ajukan Layanan Kamar (Housekeeping)** | ✅ | ✅ | ✅ | ❌ | ✅ (Kamar Aktif) | 403 / 422 |
| **Ajukan Inspeksi Kamar (Room Inspection)** | ✅ | ✅ | ✅ | ❌ | ✅ (Kamar Aktif) | 403 / 422 |
| **Disposisi Staf Kebersihan (`assign`)** | ✅ | ✅ | ✅ | ❌ | ❌ | 403 Forbidden |
| **Disposisi Petugas Inspektor (`assign`)** | ✅ | ✅ | ✅ | ❌ | ❌ | 403 Forbidden |
| **Reschedule Jadwal Layanan** | ✅ | ✅ | ✅ | ❌ | ❌ | 403 Forbidden |
| **Mulai Pekerjaan (`start`) & Unggah Foto Awal** | ✅ | ✅ | ✅ | ✅ (Ditugaskan) | ❌ | 403 Forbidden |
| **Serahkan Checklist & Foto Selesai (`submit`)** | ✅ | ✅ | ✅ | ✅ (Ditugaskan) | ❌ | 403 Forbidden |
| **Verifikasi Pengawas Lapangan (`verify`)** | ✅ | ✅ | ✅ | ❌ | ❌ | 403 Forbidden |
| **Konfirmasi Selesai Penyewa (`approve`)** | ❌ | ❌ | ❌ | ❌ | ✅ (Kamar Terkait) | 403 Forbidden |
| **Minta Perbaikan Kebersihan (`request-revision`)** | ❌ | ❌ | ❌ | ❌ | ✅ (Kamar Terkait) | 403 Forbidden |
| **Ajukan Keberatan Inspeksi (`object`)** | ❌ | ❌ | ❌ | ❌ | ✅ (Kamar Terkait) | 403 Forbidden |
| **Lihat Seluruh Laporan Properti** | ✅ | ✅ | ✅ | ❌ | ❌ | 403 Forbidden |

---

## Aturan Isolasi & Keamanan Khusus

### 1. Isolasi Unit Hunian Penyewa (`Tenant Isolation`)
- Penyewa hanya dapat mengakses, mengajukan layanan, menyetujui, atau meminta perbaikan pada unit kamar yang memiliki kontrak sewa aktif (`confirmed`, `active`, `checked_in`).
- Setiap request yang mencoba memodifikasi atau mengakses unit kamar lain dicegat di level service layer dengan `validateTenantRoomAccess()` dan melempar `DomainException` (HTTP 403 Forbidden).

### 2. Pencegahan Kebocoran Media Privat (`Private Storage Gate`)
- Foto KTP penyewa, foto kondisi awal kebersihan, dan foto bukti pemeriksaan inspeksi disimpan pada storage driver privat (`local`).
- Aplikasi menolak pembuatan symlink publik untuk direktori identitas.
- Media hanya dapat diunduh/dilihat melalui route backend terproteksi yang memverifikasi relasi kepemilikan user terhadap dokumen tersebut.

### 3. Keunikan NIK Tanpa Pembocoran Plaintext
- Setiap pembaruan NIK divalidasi keunikannya di seluruh database penyewa menggunakan blind index `nik_hash`.
- Jika terdapat penyewa lain yang mencoba mendaftarkan NIK yang sama, sistem mengembalikan respon kesalahan HTTP 422 dengan pesan ramah tanpa membeberkan informasi akun pemilik asli.
