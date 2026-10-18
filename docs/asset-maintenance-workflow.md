# Dokumentasi Alur Pemeliharaan Aset & Biaya Perawatan

Dokumen ini menjelaskan integrasi antara tiket perbaikan/pemeliharaan operasional (`maintenance_requests`) dengan modul Manajemen Aset Tetap, rincian biaya komponen perawatan, serta mekanisme pemisahan perlakuan akuntansi antara Beban Operasional (Revenue Expenditure) dan Kapitalisasi Nilai Aset (Capital Expenditure).

---

## 1. Integrasi Tiket Pemeliharaan dengan Aset Fisik

Tabel `maintenance_requests` telah diperluas dengan kolom relasional dan komponen biaya:

| Kolom | Tipe | Keterangan |
|---|---|---|
| `asset_id` | BIGINT (FK, Nullable) | Relasi langsung ke entitas aset tetap di tabel `assets` |
| `labor_cost` | DECIMAL(15,2) | Ongkos teknisi / jasa perbaikan luar |
| `material_cost` | DECIMAL(15,2) | Biaya bahan habis pakai (contoh: freon, cat, lem) |
| `spare_parts_cost` | DECIMAL(15,2) | Biaya suku cadang pengganti (contoh: kompresor baru) |
| `total_maintenance_cost`| DECIMAL(15,2) | Total biaya perawatan (`labor + material + spare_parts`) |
| `is_capitalized` | BOOLEAN | Penanda apakah biaya ini menambah nilai perolehan aset |
| `expense_id` | BIGINT (FK, Nullable) | Relasi ke modul pengeluaran kas operasional (`expenses`) |

---

## 2. Rincian Komponen Biaya Perawatan

Untuk menghindari ketidakakuratan kalkulasi biaya operasional properti, setiap perbaikan membagi pengeluaran menjadi 3 komponen transparan:

```
Total Biaya Pemeliharaan = Ongkos Teknisi + Biaya Bahan + Biaya Spare Parts
```

1. **Labor Cost (Jasa)**: Pembayaran untuk jasa tukang harian, servis AC panggilan, atau teknisi vendor.
2. **Material Cost (Bahan Baku)**: Pengadaan semen, pipa PVC, kawat las, cat tembok, dan freon R32.
3. **Spare Parts Cost (Suku Cadang)**: Penggantian motor kipas indoor, motherboard kulkas, kran shower stainless, dll.

---

## 3. Matriks Keputusan: Beban Operasional vs Kapitalisasi

| Kriteria | Beban Operasional (Revenue Expenditure) | Kapitalisasi Aset (Capital Expenditure) |
|---|---|---|
| **Definisi** | Perawatan rutin untuk menjaga aset tetap berfungsi normal | Penggantian komponen mayor yang memperpanjang umur atau kapasitas aset secara signifikan |
| **Contoh Kasus** | Cuci AC rutin 3 bulanan, ganti baterai remote, tambal pipa bocor | Overhaul gedung, ganti kompresor AC berdaya tahan 5 tahun baru, renovasi struktural |
| **Nilai Nominal** | Biasanya bernilai relatif kecil (< Rp 1.000.000) | Biasanya bernilai material (> Rp 1.000.000 atau > 25% nilai aset) |
| **Perlakuan Akuntansi** | Dicatat sebagai beban periode berjalan: Debet Beban Pemeliharaan (5103), Kredit Kas/Bank (1101) via modul `expenses` | Nilai ditambahkan ke `purchase_cost` dan `current_book_value` aset. Jadwal depresiasi disesuaikan |
| **Status di Sistem** | `is_capitalized = false` | `is_capitalized = true` |

### 3.1 Prosedur Eksekusi Kapitalisasi Biaya
Jika `is_capitalized == true`:
```php
$asset = $maintenanceRequest->asset;
if ($asset) {
    $asset->purchase_cost += $maintenanceRequest->total_maintenance_cost;
    $asset->current_book_value += $maintenanceRequest->total_maintenance_cost;
    $asset->save();
}
```
Nilai buku aset meningkat dan penyusutan bulanan berikutnya dihitung berdasarkan nilai buku yang baru.

---

## 4. Pelaporan Terpadu pada Tampilan Dashboard

### 4.1 Tab "Riwayat Pemeliharaan" pada Detail Aset
Pengguna dapat melihat seluruh rekam jejak servis aset yang bersangkutan:
- Nomor tiket pemeliharaan & tanggal pengerjaan.
- Masalah yang dilaporkan oleh penghuni/staf.
- Rincian ongkos jasa, bahan, dan spare parts.
- Total biaya riil serta status apakah biaya tersebut dikapitalisasi atau dibebankan langsung.

### 4.2 Tab "Aset Terkait" pada Detail Kamar
Menampilkan inventaris kamar beserta ringkasan kondisi fisik dan tanggal servis terakhir. Jika penghuni kamar melaporkan kerusakan AC, tiket pemeliharaan secara otomatis mereferensikan aset AC kamar tersebut.

---

## 5. Alur Tiket dari Mobile App (Flutter)

1. Staf atau Penyewa (Tenant) membuka detail aset kamar di aplikasi mobile.
2. Memilih aksi **"Laporkan Masalah Aset"** (`POST /api/v1/assets/{id}/report-issue`).
3. Form mobile meminta judul masalah, deskripsi kerusakan, dan tingkat urgensi (`low`, `medium`, `high`, `urgent`).
4. Backend `AssetApiController` memvalidasi bahwa aset tersebut memang berada di kamar yang disewa penghuni.
5. Tiket `MaintenanceRequest` terbit otomatis dengan status `PENDING` dan tautan ke `asset_id`.
6. Staf operasional menerima notifikasi dan memproses work order.
