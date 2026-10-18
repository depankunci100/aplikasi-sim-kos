# Daftar Periksa Pengujian Penerimaan Pengguna (UAT Checklist)
## Modul Master Kamar, Aset Tetap, Biaya Pemeliharaan & Depresiasi Akuntansi

Dokumen ini berisi panduan skenario pengujian User Acceptance Testing (UAT) untuk memastikan seluruh fitur berfungsi sesuai spesifikasi teknis dan kebutuhan operasional.

---

## 1. Modul Master Kamar & Right-Side Drawer

| ID Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|:---:|
| **UAT-ROOM-01** | Buka halaman Master Kamar, klik tombol "Tambah Kamar Baru". | Right-Side Drawer `#roomDrawer` muncul mulus dari sisi kanan tanpa reload halaman. | PASS |
| **UAT-ROOM-02** | Input nomor kamar yang sama dalam properti yang sama. | Validasi form menolak dengan pesan *"Nomor kamar sudah digunakan pada properti ini"*. | PASS |
| **UAT-ROOM-03** | Simpan kamar baru dengan tarif kustom bulanan dan fasilitas spesifik. | Kamar tersimpan, muncul di tabel daftar dengan badge status ketersediaan. | PASS |
| **UAT-ROOM-04** | Ubah status kamar menjadi `MAINTENANCE` via modal status cepat. | Status kamar langsung terupdate dan tercatat di riwayat perubahan. | PASS |
| **UAT-ROOM-05** | Buka detail kamar (`show`), periksa 5 tab. | Tab Spesifikasi, Fasilitas, Riwayat Perubahan, Riwayat Booking, dan Aset Terkait menampilkan data akurat. | PASS |
| **UAT-ROOM-06** | Coba nonaktifkan (`toggleActive`) atau hapus kamar yang memiliki booking aktif (`ACTIVE`). | Sistem memblokir aksi dan menampilkan pesan peringatan proteksi booking aktif. | PASS |
| **UAT-ROOM-07** | Periksa tab "Riwayat Perubahan" setelah melakukan update tarif. | Terlihat baris histori dengan field `price_monthly`, nilai lama, nilai baru, dan nama staf pengubah. | PASS |

---

## 2. Modul Fixed Asset Management & Inventaris

| ID Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|:---:|
| **UAT-ASSET-01** | Buka halaman Aset, klik "Tambah Aset Baru". | Drawer input aset terbuka dengan pilihan kategori lengkap dan kamar terkait. | PASS |
| **UAT-ASSET-02** | Simpan aset baru tanpa mengisi kode aset. | Kode unik terisi otomatis dengan format `AST-P{id}-YYYYMM-0001`. | PASS |
| **UAT-ASSET-03** | Pindahkan aset dari Kamar 101 ke Kamar 102 via tombol "Pindahkan Lokasi". | Kolom lokasi aset terupdate ke Kamar 102 dan mutasi tercatat di `asset_location_histories`. | PASS |
| **UAT-ASSET-04** | Ubah kondisi fisik aset dari `good` menjadi `damaged`. | Kondisi aset terupdate dan status siklus hidup menyesuaikan. | PASS |
| **UAT-ASSET-05** | Buka detail aset (`show`), periksa skedul depresiasi terhitung. | Skedul depresiasi bulanan tertampil rapi hingga akhir masa manfaat. | PASS |

---

## 3. Integrasi Tiket Pemeliharaan & Biaya Perawatan

| ID Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|:---:|
| **UAT-MAINT-01** | Buat tiket pemeliharaan dengan mengaitkan `asset_id`. | Tiket tersimpan dan nama aset terpaut pada tiket perbaikan. | PASS |
| **UAT-MAINT-02** | Input rincian biaya: Jasa Rp 100.000, Material Rp 50.000, Sparepart Rp 150.000. | Total biaya terhitung otomatis sebesar Rp 300.000. | PASS |
| **UAT-MAINT-03** | Pilih opsi Beban Operasional Langsung (`is_capitalized = false`). | Terbentuk transaksi pengeluaran operasional di modul `expenses`. Nilai buku aset tidak berubah. | PASS |
| **UAT-MAINT-04** | Pilih opsi Kapitalisasi Nilai Aset (`is_capitalized = true`). | Nilai perolehan dan nilai buku aset bertambah sebesar Rp 300.000. | PASS |

---

## 4. Akuntansi Depresiasi & Double-Entry Journaling

| ID Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|:---:|
| **UAT-DEP-01** | Jalankan Depresiasi Bulanan dengan opsi "Dry-Run Preview". | Menampilkan ringkasan total nominal depresiasi tanpa membuat entri jurnal akuntansi. | PASS |
| **UAT-DEP-02** | Jalankan Depresiasi Bulanan secara nyata ("Posting Jurnal"). | Terbentuk entri jurnal: Debet Beban Depresiasi (5201) & Kredit Akumulasi Depresiasi (1391). Nilai Debit == Kredit. | PASS |
| **UAT-DEP-03** | Ulangi posting depresiasi untuk periode yang sama pada aset yang sama. | Sistem mendeteksi idempotensi dan tidak membuat jurnal ganda. | PASS |
| **UAT-DEP-04** | Depresiasi aset yang sudah mencapai nilai residu (fully depreciated). | Sistem menjaga nilai buku tidak turun di bawah nilai residu; beban dihitung Rp 0. | PASS |

---

## 5. Pelepasan Aset (Disposal) & Jurnal Laba/Rugi

| ID Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|:---:|
| **UAT-DISP-01** | Lakukan pelepasan aset dengan penjualan di atas nilai buku (Gain). | Terbentuk jurnal seimbang: Debet Kas (1101), Debet Akumulasi Susut (1391), Kredit Aset Asal (130x), Kredit Keuntungan Pelepasan (4201). | PASS |
| **UAT-DISP-02** | Lakukan pelepasan aset karena rusak total tanpa penerimaan uang (Loss/Scrap). | Terbentuk jurnal seimbang: Debet Akumulasi Susut (1391), Debet Kerugian Pelepasan (5301), Kredit Aset Asal (130x). | PASS |

---

## 6. Hak Akses & Keamanan Tenant (Mobile API)

| ID Skenario | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|:---:|
| **UAT-SEC-01** | Staf Properti A mencoba mengakses data aset milik Properti B. | Akses ditolak dengan kode respon 403 / 404 (Isolasi Properti). | PASS |
| **UAT-SEC-02** | Tenant memanggil API detail aset di kamar aktif sewaannya. | API mengembalikan data nama, merek, kondisi, dan nomor seri barang. | PASS |
| **UAT-SEC-03** | Periksa payload API yang diterima oleh Tenant. | Kolom `purchase_cost`, `salvage_value`, `accumulated_depreciation`, dan `current_book_value` disanitasi (tidak ada dalam JSON). | PASS |
| **UAT-SEC-04** | Tenant melaporkan masalah aset melalui aplikasi Flutter. | Tiket pemeliharaan berhasil terbuat dengan kaitan ke aset kamar tersebut. | PASS |
