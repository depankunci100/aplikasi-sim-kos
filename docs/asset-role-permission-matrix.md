# Matriks Peran & Hak Akses (Role-Based Access Control)

Dokumen ini menjabarkan pemetaan hak akses (RBAC), pembatasan multi-tenancy properti, serta protokol perlindungan kerahasiaan data finansial aset terhadap peran penyewa (tenant).

---

## 1. Matriks Hak Akses Modul Kamar & Aset

| Fitur / Aksi | Owner | Manager | Finance | Staff / Housekeeper | Tenant (Penyewa) |
|---|:---:|:---:|:---:|:---:|:---:|
| **Master Kamar** | | | | | |
| Melihat Daftar Kamar & Status | Ya | Ya | Ya | Ya | Hanya kamar tersedia / miliknya |
| Tambah / Edit Spesifikasi Kamar | Ya | Ya | Tidak | Tidak | Tidak |
| Ubah Status Operasional Kamar | Ya | Ya | Tidak | Ya | Tidak |
| Toggle Aktif / Non-aktif Kamar | Ya | Ya | Tidak | Tidak | Tidak |
| Hapus Kamar (Tanpa Booking Aktif) | Ya | Tidak | Tidak | Tidak | Tidak |
| Melihat Histori Perubahan Kamar | Ya | Ya | Ya | Tidak | Tidak |
| **Manajemen Aset Tetap** | | | | | |
| Melihat Daftar Aset & Inventaris | Ya | Ya | Ya | Ya | Hanya di kamar sewa aktif |
| Tambah Aset Baru & Auto-code | Ya | Ya | Ya | Tidak | Tidak |
| Edit Spesifikasi Fisik Aset | Ya | Ya | Ya | Tidak | Tidak |
| Mutasi Lokasi Fisik Aset | Ya | Ya | Tidak | Ya | Tidak |
| Pelepasan Aset (Disposal) | Ya | Ya | Ya | Tidak | Tidak |
| Melihat Riwayat Mutasi Lokasi | Ya | Ya | Ya | Ya | Tidak |
| **Akuntansi & Depresiasi** | | | | | |
| Melihat Harga Beli & Nilai Buku | Ya | Ya | Ya | Tidak | **DILARANG** |
| Melihat Skedul Depresiasi Aset | Ya | Ya | Ya | Tidak | **DILARANG** |
| Menjalankan Batch Depresiasi Bulanan | Ya | Tidak | Ya | Tidak | **DILARANG** |
| Meninjau Jurnal Otomatis Depresiasi | Ya | Tidak | Ya | Tidak | **DILARANG** |
| **Pemeliharaan & Work Order** | | | | | |
| Melaporkan Kerusakan Aset | Ya | Ya | Ya | Ya | Ya (kamar sendiri) |
| Menyetujui Tiket Pemeliharaan | Ya | Ya | Ya | Tidak | Tidak |
| Menginput Komponen Biaya Servis | Ya | Ya | Ya | Tidak | Tidak |
| Otorisasi Kapitalisasi Biaya Servis | Ya | Tidak | Ya | Tidak | Tidak |

---

## 2. Isolasi Batas Properti (Multi-Tenancy Property Isolation)

Seluruh query database pada `RoomService`, `AssetService`, dan `AssetDepreciationService` wajib mengikat batasan `property_id`:
```php
$userPropertyIds = $user->properties()->pluck('properties.id')->toArray();
```
- Seorang Pengelola/Staf di Properti A tidak memiliki akses membaca atau memanipulasi data kamar maupun aset di Properti B.
- Validasi keunikan nomor kamar (`room_number`) dan nomor registrasi aset (`asset_code`) diisolasi dalam cakupan `property_id`.

---

## 3. Protokol Kerahasiaan Data Tenant (Tenant Privacy Protection)

Penyewa kamar hanya diperbolehkan mengetahui inventaris penunjang yang disediakan di kamar tempat mereka menginap:

1. **Penyaringan Berdasarkan Booking Aktif**:
   Penyewa hanya dapat memanggil detail aset kamar via API bila mereka memiliki booking dengan status `ACTIVE` atau `OVERSTAY` di kamar tersebut.
2. **Sanitasi Angka Finansial (Financial Data Sanitization)**:
   Pada endpoint `GET /api/v1/assets/{id}`, bila pemanggil adalah pengguna dengan role `tenant`, sistem menerapkan filter sanitasi ketat:
   ```php
   if ($user->hasRole('tenant')) {
       unset($data['purchase_cost']);
       unset($data['salvage_value']);
       unset($data['accumulated_depreciation']);
       unset($data['current_book_value']);
       unset($data['vendor_name']);
       unset($data['invoice_number']);
       unset($data['disposal_proceeds']);
   }
   ```
   Hal ini menjamin data harga beli, vendor, laba rugi, dan nilai buku aset kos terlindungi sebagai rahasia internal manajemen.
