# CHECKLIST PENGUJIAN PENERIMAAN PENGGUNA (USER ACCEPTANCE TESTING - UAT)
**Sistem:** SIM Kos & Guesthouse (Laravel 13 Web Dashboard + Flutter Mobile)  
**Fase:** Phase 10 — UAT Staging & Operational Readiness  
**Dokumen:** `docs/phase10-uat-checklist.md`  
**Status Evaluasi:** Instrumen Pengujian Siap Diberikan kepada Pengguna/Pemilik Sistem  

---

## 1. Panduan dan Kategori Status UAT

> [!IMPORTANT]
> Tim pengembang **dilarang menandatangani atau mengesahkan form UAT atas nama pengguna atau pemilik properti**. Dokumen ini berfungsi sebagai instrumen pengujian terstruktur yang wajib diuji dan divalidasi langsung oleh perwakilan masing-masing peran pengguna di server staging.

Setiap butir pengujian dikategorikan ke dalam salah satu status berikut:
- **[LULUS OTOMATIS]**: Telah diverifikasi 100% lulus melalui pengujian unit/feature/widget test otomatis.
- **[LULUS MANUAL]**: Telah diverifikasi alur visual dan fungsinya oleh tim pengembang di lingkungan lokal.
- **[BELUM DIUJI]**: Menunggu sesi uji langsung oleh pengguna riil di server staging / perangkat fisik.
- **[GAGAL]**: Skenario tidak menghasilkan hasil yang diharapkan dan memerlukan perbaikan kode.
- **[TERBLOKIR]**: Tidak dapat diuji karena fasilitas eksternal (misalnya payment gateway / SMS gateway) belum aktif.

---

## 2. Skenario UAT Peran 1: Pemilik Kos & Manajer Bisnis (Owner / Manager)

**Tujuan:** Memvalidasi pengawasan finansial eksekutif, persetujuan pengeluaran, integritas buku besar, dan laporan laba rugi / neraca.

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status Saat Ini | Catatan Penguji (Owner) |
| :---: | :--- | :--- | :--- | :---: | :--- |
| **O-01** | Login Dashboard Finansial Web | Masuk menggunakan akun Owner di `/dashboard/finance` | Menampilkan KPI Pendapatan, Okupansi Kamar, Pengeluaran, dan Grafik Tren Bulanan. | **[LULUS MANUAL]** | |
| **O-02** | Persetujuan Pengeluaran Operasional (Expense Approval) | Buka menu Pengeluaran, pilih pengeluaran pending, klik tombol Approve/Reject dengan catatan | Status berubah menjadi Disetujui / Ditolak; tidak dapat diubah lagi setelah disetujui. | **[LULUS OTOMATIS]** | |
| **O-03** | Rekonsiliasi Kas & Bank | Buka menu Laporan Rekonsiliasi di `/dashboard/accounting/reports/reconciliation` | Membandingkan subledger pembayaran terverifikasi dengan mutasi rekening kas; varians ditampilkan jelas. | **[LULUS OTOMATIS]** | |
| **O-04** | Evaluasi Neraca Keuangan (Balance Sheet) | Buka laporan Neraca di `/dashboard/accounting/reports/balance-sheet` | Total Aset seimbang dengan Total Kewajiban + Ekuitas (`Total Aset == Total Liabilitas & Ekuitas`). | **[LULUS OTOMATIS]** | |
| **O-05** | Akses Aplikasi Mobile Owner (Flutter) | Buka aplikasi mobile, login role Owner, buka menu Finance Hub & Accounting | Layar memuat tab Ringkasan, Kategori Biaya, Arus Kas, dan Neraca Saldo secara responsif. | **[LULUS OTOMATIS]** | |
| **O-06** | Pengesahan Saldo Awal (Opening Balance) | Masukkan saldo awal kas, piutang, dan deposit sebelum sistem live | Akun kas dan ekuitas bertambah secara berpasangan dan seimbang. | **[BELUM DIUJI]** | Memerlukan data riil pemilik. |

---

## 3. Skenario UAT Peran 2: Resepsionis & Kasir (Front Desk / Receptionist)

**Tujuan:** Memvalidasi alur pemesanan kamar, verifikasi pembayaran manual, check-in, dan pencatatan deposit.

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status Saat Ini | Catatan Penguji (Staf) |
| :---: | :--- | :--- | :--- | :---: | :--- |
| **R-01** | Entri Pemesanan Kamar (Booking Baru) | Buka menu Booking, klik Tambah Booking, pilih kamar dan durasi | Sistem menghitung tarif sewa dan uang jaminan secara otomatis; kamar bertabrakan ditolak. | **[LULUS OTOMATIS]** | |
| **R-02** | Verifikasi Pembayaran Transfer Bank | Buka Right-Side Drawer verifikasi, periksa foto bukti bayar, klik Verifikasi | Status invoice menjadi PAID, status booking aktif, dan kamar menjadi terisi (occupied). | **[LULUS OTOMATIS]** | |
| **R-03** | Penolakan Bukti Pembayaran Tidak Valid | Buka bukti bayar palsu/buram, klik Tolak dengan alasan | Status pembayaran menjadi Ditolak, invoice tetap UNPAID, penyewa menerima pemberitahuan. | **[LULUS OTOMATIS]** | |
| **R-04** | Penerimaan Uang Jaminan (Deposit) | Periksa mutasi deposit pada subledger setelah verifikasi pembayaran sewa | Saldo deposit bertambah sesuai porsi jaminan; tercatat di mutasi DEPOSIT_RECEIVED. | **[LULUS OTOMATIS]** | |
| **R-05** | Cetak Kuitansi / Bukti Penerimaan | Klik unduh kuitansi pembayaran di dashboard web | Kuitansi terunduh dengan rincian nomor invoice, nama penyewa, dan cap digital resmi. | **[LULUS MANUAL]** | |

---

## 4. Skenario UAT Peran 3: Staf Operasional, Kebersihan & Perawatan (Staff / Housekeeping / Maintenance)

**Tujuan:** Memvalidasi inspeksi kamar fisik, pencatatan meteran utilitas, tiket perbaikan, dan check-out settlement.

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status Saat Ini | Catatan Penguji (Staf) |
| :---: | :--- | :--- | :--- | :---: | :--- |
| **S-01** | Inspeksi Masuk Kamar (Check-In Inspection) | Buka checklist inspeksi di mobile/web, centang kondisi aset, unggah foto bukti | Checklist tersimpan di storage privat; inspeksi tidak otomatis mengaktifkan sewa tanpa pembayaran. | **[LULUS OTOMATIS]** | |
| **S-02** | Pencatatan Angka Meteran Listrik/Air | Masukkan angka meteran baru pada menu Utilitas | Sistem menghitung pemakaian kWh/m3; menolak pembacaan mundur (*backward reading*). | **[LULUS OTOMATIS]** | |
| **S-03** | Penanganan Tiket Kerusakan (Maintenance) | Buka tiket kerusakan fasilitas, ubah status ke Dalam Pengerjaan, selesaikan tiket | Riwayat pengerjaan tercatat; foto perbaikan tersimpan di storage privat. | **[LULUS OTOMATIS]** | |
| **S-04** | Inspeksi Keluar & Klaim Kerusakan (Check-Out) | Saat penyewa check-out, catat kerusakan aset (misal: kran patah Rp 200.000) | Potongan otomatis diajukan ke settlement check-out didukung foto bukti fisik. | **[LULUS OTOMATIS]** | |
| **S-05** | Finalisasi Refund Deposit Akhir Sewa | Klik finalisasi refund sisa deposit (misal Rp 800.000) setelah dikurangi potongan | Saldo deposit menjadi 0, status kamar menjadi Kotor (DIRTY) untuk tugas Housekeeping. | **[LULUS OTOMATIS]** | |

---

## 5. Skenario UAT Peran 4: Penyewa Kamar (Tenant)

**Tujuan:** Memvalidasi pengalaman pengguna calon penyewa dan penyewa aktif pada Web Publik dan Aplikasi Mobile.

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status Saat Ini | Catatan Penguji (Penyewa) |
| :---: | :--- | :--- | :--- | :---: | :--- |
| **T-01** | Pemesanan Kamar Mandiri (Web / Mobile) | Pilih properti, pilih kamar kosong, isi formulir sewa, kirim | Pemesanan terbuat dengan status PENDING; nomor invoice dan instruksi transfer muncul. | **[LULUS OTOMATIS]** | |
| **T-02** | Unggah Bukti Transfer Pembayaran | Unggah foto struk transfer ATM / m-Banking dari aplikasi mobile | Berkas terunggah aman ke server privat; status pembayaran berubah menjadi Menunggu Verifikasi. | **[LULUS OTOMATIS]** | |
| **T-03** | Pemantauan Saldo Uang Jaminan (Deposit) | Buka menu Deposit di aplikasi mobile penyewa | Menampilkan saldo uang jaminan aktif dan riwayat mutasi penerimaan. | **[LULUS OTOMATIS]** | |
| **T-04** | Pengajuan Komplain / Keluhan Fasilitas | Buka menu Komplain, tulis kendala (misal: AC kurang dingin), lampirkan foto | Tiket masuk ke antrian triase staf kos; penyewa dapat memantau respon petugas. | **[LULUS OTOMATIS]** | |
| **T-05** | Persetujuan Settlement Check-Out | Buka rincian penyelesaian check-out di aplikasi, tinjau potongan kerusakan | Penyewa dapat menyetujui rincian potongan dan memasukkan rekening bank pengembalian refund. | **[LULUS OTOMATIS]** | |
| **T-06** | Percobaan Akses Menu Finansial Internal | Penyewa mencoba membuka URL `/dashboard/accounting/coa` atau `/api/v1/expenses` | Sistem secara tegas memblokir akses dengan pesan `403 Forbidden`. | **[LULUS OTOMATIS]** | |

---

## 6. Format Rekapitulasi & Tanda Tangan Hasil UAT (Untuk Dilengkapi di Staging)

| Peran Penguji | Nama Perwakilan Penguji | Tanggal Uji | Hasil Keseluruhan (Diterima / Perlu Revisi) | Tanda Tangan Pengesahan |
| :--- | :--- | :---: | :---: | :---: |
| **Owner / Pemilik Kos** | `..............................` | `... / ... / 2026` | `[   ] Diterima   [   ] Perlu Revisi` | `.........................` |
| **Manajer Operasional** | `..............................` | `... / ... / 2026` | `[   ] Diterima   [   ] Perlu Revisi` | `.........................` |
| **Resepsionis / Kasir** | `..............................` | `... / ... / 2026` | `[   ] Diterima   [   ] Perlu Revisi` | `.........................` |
| **Staf Pemeliharaan** | `..............................` | `... / ... / 2026` | `[   ] Diterima   [   ] Perlu Revisi` | `.........................` |
| **Perwakilan Penyewa** | `..............................` | `... / ... / 2026` | `[   ] Diterima   [   ] Perlu Revisi` | `.........................` |
