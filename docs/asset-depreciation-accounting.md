# Dokumentasi Akuntansi Penyusutan Aset & Double-Entry Journaling

Dokumen ini menjelaskan formula matematis penyusutan garis lurus, pencegahan penyusutan di bawah nilai sisa (salvage value guard), skedul depresiasi terinci, kunci idempotensi penjurnalan otomatis, pemetaan akun Buku Besar (COA), serta perlakuan jurnal pelepasan aset (disposal gain/loss).

---

## 1. Kebijakan & Formula Depresiasi Garis Lurus (Straight-Line)

Sesuai standar akuntansi keuangan (PSAK/SAK ETAP), penyusutan dialokasikan secara sistematis selama masa manfaat ekonomis aset:

$$\text{Penyusutan Tahunan} = \frac{\text{Biaya Perolehan} - \text{Nilai Residu}}{\text{Masa Manfaat (Tahun)}}$$

$$\text{Penyusutan Bulanan} = \frac{\text{Penyusutan Tahunan}}{12}$$

### 1.1 Batasan Nilai Sisa (Salvage Value Guard)
Nilai buku bersih aset tidak boleh menyusut melampaui nilai residu yang ditetapkan:
$$\text{Nilai Buku Minimum} = \text{Nilai Residu}$$
Jika pada bulan terakhir penyusutan sisa depresiasi akan menyebabkan nilai buku jatuh di bawah nilai residu:
$$\text{Beban Depresiasi Bulan Berjalan} = \max\left(0, \text{Nilai Buku Saat Ini} - \text{Nilai Residu}\right)$$
Aset yang telah fully depreciated (`current_book_value <= salvage_value`) tidak akan disusutkan kembali.

---

## 2. Struktur Tabel Skedul Depresiasi: `asset_depreciation_schedules`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT (PK) | Identifier baris skedul |
| `asset_id` | BIGINT (FK) | Relasi ke aset bersangkutan |
| `period_year` | INT | Tahun fiskal (contoh: 2026) |
| `period_month` | INT | Bulan fiskal (1 - 12) |
| `depreciation_amount` | DECIMAL(15,2) | Beban penyusutan periode tersebut |
| `accumulated_depreciation` | DECIMAL(15,2) | Akumulasi penyusutan setelah periode tersebut |
| `book_value` | DECIMAL(15,2) | Nilai buku bersih di akhir periode |
| `journal_entry_id` | BIGINT (FK, Nullable) | Relasi ke entri jurnal akuntansi yang terbentuk |
| `is_posted` | BOOLEAN | Status apakah sudah dibukukan ke Buku Besar |
| `posted_at` | TIMESTAMP (Nullable) | Waktu pembukuan jurnal |

---

## 3. Bagan Akun Standar (COA) Terintegrasi

| Kode Akun | Nama Akun | Kategori | Saldo Normal | Keterangan |
|---|---|---|---|---|
| `1301` | Aset Tetap - Bangunan & Prasarana | Aset Tetap | Debit | Gedung, paviliun, instalasi permanen |
| `1302` | Aset Tetap - Peralatan & Elektronik | Aset Tetap | Debit | AC, Kulkas, TV, Pompa Air |
| `1303` | Aset Tetap - Furnitur & Mebel | Aset Tetap | Debit | Kasur, Ranjang, Lemari, Meja |
| `1304` | Aset Tetap - Kendaraan Operasional | Aset Tetap | Debit | Sepeda motor atau mobil inventaris |
| `1391` | Akumulasi Penyusutan Aset Tetap | Kontra Aset | Kredit | Akumulasi pengurang nilai aset |
| `4201` | Keuntungan Pelepasan Aset Tetap | Pendapatan Lainnya | Kredit | Laba jika harga jual aset > nilai buku |
| `5201` | Beban Penyusutan Aset Tetap | Beban Operasional | Debit | Beban penyusutan periodik |
| `5301` | Kerugian Pelepasan Aset Tetap | Beban Lainnya | Debit | Rugi jika harga jual aset < nilai buku |

---

## 4. Mekanisme Double-Entry Auto-Journaling

### 4.1 Jurnal Penyusutan Bulanan Rutin
Ketika depresiasi bulanan dibukukan (baik via tombol individual di halaman aset atau batch posting akhir bulan):

```
[DEBIT]   5201 - Beban Penyusutan Aset Tetap          Rp XXX.XXX
    [KREDIT]  1391 - Akumulasi Penyusutan Aset Tetap         Rp XXX.XXX
```
- **Kunci Idempotensi (Idempotency Key)**:
  `asset_depreciation_{asset_id}_{year}_{month}`
  Kunci ini menjamin bahwa tombol posting yang ditekan berulang kali atau proses batch yang berjalan ulang tidak akan menghasilkan jurnal ganda pada periode bulan yang sama.

### 4.2 Jurnal Pelepasan Aset (Disposal)

#### Kasus A: Pelepasan dengan Keuntungan (Gain on Sale)
AC perolehan Rp 4.000.000, akumulasi susut Rp 3.000.000 (Nilai buku Rp 1.000.000), dijual seharga Rp 1.500.000:
```
[DEBIT]   1101 - Kas & Bank                           Rp 1.500.000
[DEBIT]   1391 - Akumulasi Penyusutan Aset Tetap      Rp 3.000.000
    [KREDIT]  1302 - Aset Tetap - Peralatan Elektronik   Rp 4.000.000
    [KREDIT]  4201 - Keuntungan Pelepasan Aset Tetap     Rp   500.000
```
Total Debit = Rp 4.500.000 | Total Kredit = Rp 4.500.000 (Seimbang / Balanced)

#### Kasus B: Pelepasan dengan Kerugian atau Rusak/Scrap (Loss on Disposal)
Kasur perolehan Rp 2.500.000, akumulasi susut Rp 1.000.000 (Nilai buku Rp 1.500.000), dibuang/rusak tanpa penerimaan (proceeds = Rp 0):
```
[DEBIT]   1391 - Akumulasi Penyusutan Aset Tetap      Rp 1.000.000
[DEBIT]   5301 - Kerugian Pelepasan Aset Tetap        Rp 1.500.000
    [KREDIT]  1303 - Aset Tetap - Furnitur & Mebel       Rp 2.500.000
```
Total Debit = Rp 2.500.000 | Total Kredit = Rp 2.500.000 (Seimbang / Balanced)

---

## 5. Fitur Dry-Run Preview vs Real Execution

Untuk memberikan kendali penuh kepada bagian Akuntansi & Keuangan sebelum membukukan angka ke Buku Besar, sistem menyediakan dua mode:

1. **Dry-Run Preview (`dry_run = true`)**:
   - Menghitung simulasi penyusutan untuk seluruh aset yang aktif pada properti dan bulan yang dipilih.
   - Mengembalikan daftar rincian aset, nilai depresiasi yang dihitung, dan total nominal yang akan dijurnal.
   - **Tanpa** mengubah status database atau membuat rekaman entri jurnal.
2. **Real Execution (`dry_run = false`)**:
   - Memeriksa kelayakan setiap aset (belum diposting di bulan tersebut, belum fully depreciated).
   - Memperbarui kolom `accumulated_depreciation`, `current_book_value`, dan `last_depreciation_date` pada tabel `assets`.
   - Membuat rekaman pada `asset_depreciation_schedules` bertanda `is_posted = true`.
   - Mengenerate `JournalEntry` dan `JournalLine` yang seimbang secara otomatis via `JournalPostingService`.
