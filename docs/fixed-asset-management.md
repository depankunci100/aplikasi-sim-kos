# Dokumentasi Manajemen Aset Tetap & Inventaris Operasional

Dokumen ini menjelaskan rancangan arsitektur, siklus hidup aset, format kode penomoran otomatis, pelacakan mutasi lokasi fisik, serta prosedur pelepasan aset (disposal) pada SIM Kos & Guesthouse.

---

## 1. Skema Basis Data Aset Tetap

### 1.1 Tabel Utama: `assets`
Menyimpan identitas, klasifikasi, nilai perolehan, masa manfaat, dan kondisi aset.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT (PK) | Identifier utama aset |
| `property_id` | BIGINT (FK) | Relasi ke cabang/properti kos |
| `asset_code` | VARCHAR(50) | Nomor register unik aset (`AST-P{id}-YYYYMM-XXXX`) |
| `name` | VARCHAR(255) | Nama barang (contoh: "AC Daikin 1 PK Inverter") |
| `category` | ENUM | Kategori aset (lihat seksi 2) |
| `brand` | VARCHAR(100) | Merek produk |
| `model_number` | VARCHAR(100) | Nomor tipe/model barang |
| `serial_number` | VARCHAR(100) | Nomor seri pabrik (opsional) |
| `room_id` | BIGINT (FK, Nullable) | Lokasi unit kamar jika ditempatkan di kamar |
| `location_area` | VARCHAR(100) | Lokasi area fisik (contoh: "Lobi", "Kamar 102", "Gudang B") |
| `purchase_date` | DATE | Tanggal perolehan aset |
| `purchase_cost` | DECIMAL(15,2) | Biaya perolehan historis (capitalized cost) |
| `salvage_value` | DECIMAL(15,2) | Nilai sisa/residu di akhir masa manfaat |
| `useful_life_years` | INT | Estimasi umur ekonomis aset (tahun) |
| `accumulated_depreciation` | DECIMAL(15,2) | Total penyusutan yang telah diakui |
| `current_book_value` | DECIMAL(15,2) | Nilai buku bersih saat ini (`purchase_cost` - `accumulated_depreciation`) |
| `last_depreciation_date` | DATE | Tanggal pembukuan depresiasi terakhir |
| `status` | ENUM | `in_use`, `in_storage`, `maintenance`, `damaged`, `disposed` |
| `condition` | ENUM | `good`, `fair`, `poor`, `broken` |
| `vendor_name` | VARCHAR(255) | Pemasok/toko pembelian |
| `invoice_number` | VARCHAR(100) | Nomor nota/faktur pembelian |
| `warranty_expiry` | DATE | Tanggal berakhir masa garansi |
| `disposal_date` | DATE | Tanggal pelepasan aset (jika dilepas) |
| `disposal_type` | ENUM | `sale`, `scrap`, `donation`, `lost` |
| `disposal_proceeds` | DECIMAL(15,2) | Nilai penerimaan uang dari penjualan aset |
| `disposal_notes` | TEXT | Keterangan pelepasan |

### 1.2 Tabel Riwayat Mutasi: `asset_location_histories`
Mencatat perpindahan fisik aset antar kamar atau antar area operasional.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT (PK) | Identifier entri riwayat |
| `asset_id` | BIGINT (FK) | Relasi ke aset |
| `from_room_id` | BIGINT (FK, Nullable) | Kamar asal sebelum mutasi |
| `to_room_id` | BIGINT (FK, Nullable) | Kamar tujuan mutasi |
| `from_area` | VARCHAR(100) | Area asal sebelum mutasi |
| `to_area` | VARCHAR(100) | Area tujuan mutasi |
| `transfer_date` | DATE | Tanggal pemindahan fisik |
| `reason` | VARCHAR(255) | Alasan pemindahan |
| `transferred_by` | BIGINT (FK) | Staf/pengguna yang mengeksekusi mutasi |

---

## 2. Klasifikasi & Standar Kode Aset

### 2.1 Format Nomor Registrasi Otomatis
Penomoran aset dibuat otomatis oleh `AssetService::generateAssetCode()` dengan format:
$$\mathbf{AST-P\{property\_id\}-\{YYYYMM\}-\{XXXX\}}$$
- Contoh: `AST-P1-202610-0001`
- `XXXX` merupakan nomor urut 4 digit yang terisolasi per properti dan bulan perolehan. Format ini memudahkan pelacakan fisik, label stiker inventaris, dan pencetakan barcode/QR code.

### 2.2 Kategori Aset
1. **`building_infrastructure`**: Bangunan, renovasi struktural, instalasi pipa & listrik gedung.
2. **`electrical_electronic`**: AC, Televisi, Kulkas, Water Heater, Mesin Cuci, Dispenser.
3. **`furniture_fixture`**: Springbed, Lemari Pakaian, Meja Kerja, Kursi, Sofa Tamu.
4. **`vehicle`**: Sepeda motor atau mobil operasional kos.
5. **`operational_equipment`**: Alat kebersihan komersial, tangga lipat, pompa air, genset.
6. **`linen_bedding`**: Sprei, bed cover, bantal cadangan bermutu tinggi.

---

## 3. Siklus Hidup & Status Kondisi Aset

```
                  ┌──────────────┐
                  │  PENGADAAN   │
                  └──────┬───────┘
                         │
                         ▼
                  ┌──────────────┐
       ┌─────────►│  IN STORAGE  │◄─────────┐
       │          └──────┬───────┘          │
       │                 │                  │
       │                 ▼                  │
       │          ┌──────────────┐          │
       │          │    IN USE    │──────────┤
       │          └──────┬───────┘          │
       │                 │                  │
(Perbaikan selesai)      ▼                  │ (Servis rutin)
       │          ┌──────────────┐          │
       └──────────│ MAINTENANCE  │──────────┘
                  └──────┬───────┘
                         │ (Kerusakan berat)
                         ▼
                  ┌──────────────┐
                  │   DAMAGED    │
                  └──────┬───────┘
                         │
                         ▼
                  ┌──────────────┐
                  │   DISPOSED   │
                  └──────────────┘
```

- **Kondisi Fisik**:
  - `good`: Kondisi prima, berfungsi normal 100%.
  - `fair`: Ada penurunan estetika minor namun berfungsi baik.
  - `poor`: Perlu perbaikan ringan/servis segera.
  - `broken`: Rusak parah, tidak dapat digunakan sementara waktu.

---

## 4. Mekanisme Mutasi Lokasi Fisik

Ketika barang dipindahkan (misal: AC dipindah dari Kamar 101 ke Gudang atau Kamar 203):
1. Pengguna membuka modal **"Pindahkan Lokasi Aset"** di Web Dashboard atau memanggil `AssetService::transferLocation()`.
2. Sistem memvalidasi bahwa kamar tujuan berada di properti yang sama.
3. Sistem memperbarui kolom `room_id` dan `location_area` pada tabel `assets`.
4. Sistem secara otomatis membuat rekaman baru pada tabel `asset_location_histories` dengan mencatat waktu, staf pelaksana, dan alasan pemindahan.

---

## 5. Prosedur Pelepasan Aset (Disposal)

Aset yang telah habis masa pakainya, rusak permanen, atau dijual kepada pihak ketiga dilepaskan melalui metode `AssetService::disposeAsset()`:
- Status aset diubah menjadi `disposed`.
- Kolom lokasi (`room_id`) dikosongkan.
- Sistem menghitung Keuntungan atau Kerugian Pelepasan:
$$\text{Laba/Rugi Pelepasan} = \text{Nilai Penjualan} - \text{Nilai Buku Saat Pelepasan}$$
- Otomatis memicu pembukuan double-entry balanced journal ke Buku Besar Akuntansi (lihat dokumentasi Akuntansi Depresiasi).
