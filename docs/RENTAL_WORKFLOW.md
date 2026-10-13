# Alur Bisnis & Siklus Penyewaan (Rental Workflow) - SIM Kos & Guesthouse

Dokumen ini mendeskripsikan secara komprehensif alur kerja operasional untuk dua model bisnis: **Kos (Jangka Panjang)** dan **Guesthouse (Jangka Pendek)**, termasuk penanganan deposit, meteran utilitas, denda keterlambatan, dan prosedur check-out.

---

## 1. Perbedaan Utama Model Operasional

| Aspek | Kos (Boarding House) | Guesthouse (Penginapan) |
| :--- | :--- | :--- |
| **Satuan Waktu** | Bulanan, 3 Bulanan, 6 Bulanan, Tahunan | Harian, Mingguan |
| **Uang Jaminan (Deposit)** | Wajib (Rp 500.000 - Rp 1.500.000) ditahan selama masa sewa | Opsional / Ringan (Rp 50.000 - Rp 200.000) |
| **Penagihan (Billing)** | Berulang (Recurring Invoice) setiap siklus sewa jatuh tempo | Sekali bayar di depan (Full Payment at Check-In) |
| **Utilitas Listrik/Air** | Meteran per kamar (dibayar sesuai pemakaian kwh bulanan) | Termasuk dalam harga per malam |
| **Status Kamar Pasca Check-Out** | Inspeksi inventaris detail, cat ulang jika perlu | Pembersihan cepat (Housekeeping turnover < 2 jam) |

---

## 2. Siklus Hidup Penyewaan Kos (Jangka Panjang)

```mermaid
sequenceDiagram
    autonumber
    actor T as Calon Penyewa
    actor M as Pengelola Kos
    participant SYS as Sistem (SIM Kos)
    participant DB as Basis Data

    T->>SYS: Pilih Kamar & Ajukan Sewa (Upload KTP, Tentukan Tanggal Masuk)
    SYS->>DB: Buat Booking (Status: PENDING) & Invoice Pertama (Sewa + Deposit)
    SYS-->>T: Tampilkan Rincian Tagihan Awal
    T->>SYS: Bayar & Upload Bukti Pembayaran
    SYS->>M: Notifikasi Pembayaran Baru
    M->>SYS: Verifikasi Pembayaran & Setujui Booking
    SYS->>DB: Ubah Status Booking ke CONFIRMED, Kamar ditandai BOOKED
    Note over T,M: Hari H Masuk (Check-In)
    M->>SYS: Catat Check-in (Catat Meteran Listrik Awal, Serah Terima Kunci)
    SYS->>DB: Booking aktif (ACTIVE), Kamar: OCCUPIED
    Note over T,SYS: Siklus Berjalan Setiap Bulan
    SYS->>SYS: Cron Job Auto-Generate Tagihan Bulanan (H-7 Sebelum Jatuh Tempo)
    SYS-->>T: Notifikasi Tagihan + Biaya Listrik
    T->>SYS: Pembayaran Tagihan Rutin
    M->>SYS: Verifikasi Pembayaran Rutin
    Note over T,M: Masa Sewa Berakhir / Rencana Keluar
    T->>SYS: Ajukan Pemberitahuan Check-Out (H-14)
    M->>SYS: Jadwalkan Inspeksi Kamar
    M->>SYS: Input Hasil Inspeksi (Kondisi Aset & Denda Kerusakan jika ada)
    SYS->>DB: Hitung Potongan Deposit & Sisa Pengembalian
    M->>T: Pengembalian Sisa Deposit & Terima Kunci
    M->>SYS: Konfirmasi Check-Out Selesai
    SYS->>DB: Booking: COMPLETED, Kamar: DIRTY -> MAINTENANCE -> AVAILABLE
```

### Aturan Khusus Kos:
1. **Invoice Terbit Otomatis**:
   Sistem menjalankan cron job setiap pukul 01:00 WIB untuk memeriksa kontrak aktif dan menerbitkan invoice tagihan sewa 7 hari sebelum jatuh tempo (`due_date`).
2. **Pencatatan Meteran Listrik**:
   - Tanggal pembacaan meteran listrik dicatat oleh pengelola antara tanggal 25-28 setiap bulan.
   - Formula:
     $$\text{Tagihan Listrik} = (\text{Meter Akhir} - \text{Meter Awal}) \times \text{Tarif per KWh}$$
   - Nilai tagihan ini otomatis digabungkan ke invoice sewa bulan bersangkutan.
3. **Pengembalian Deposit**:
   - Deposit tidak boleh digunakan untuk membayar sewa reguler tanpa persetujuan tertulis pengelola.
   - Saat check-out, deposit dikurangi biaya perbaikan inventaris rusak dan tunggakan listrik.
   - Sisa saldo ditransfer kembali ke rekening penyewa maksimal 3 hari kerja setelah check-out.

---

## 3. Siklus Hidup Reservasi Guesthouse (Jangka Pendek)

```mermaid
sequenceDiagram
    autonumber
    actor G as Tamu Guesthouse
    actor R as Resepsionis
    participant SYS as Sistem (SIM Kos)
    participant DB as Basis Data

    G->>SYS: Pilih Kamar, Tanggal Check-in & Check-out (Harian)
    SYS->>SYS: Cek Ketersediaan Kamar (Cegah Overlap)
    SYS->>DB: Buat Reservasi & Tagihan
    G->>SYS: Bayar Penuh / DP (Transfer / QRIS / Cash via Frontdesk)
    R->>SYS: Konfirmasi Pembayaran
    Note over G,R: Tamu Tiba di Properti
    R->>SYS: Proses Check-In (Verifikasi ID, Berikan Kunci Kamar)
    SYS->>DB: Status: ACTIVE, Kamar: OCCUPIED
    Note over G,R: Waktu Check-Out (Standar: 12:00 WIB)
    alt Check-out Tepat Waktu
        R->>SYS: Proses Check-Out & Terima Kunci
    else Terlambat Check-out (Overstay)
        SYS->>SYS: Deteksi Lewat Pukul 13:00 WIB
        SYS->>DB: Terbitkan Biaya Late Check-Out (Denda per jam / 50% tarif harian)
        R->>G: Tagih Biaya Overstay
        R->>SYS: Proses Check-Out Selesai
    end
    SYS->>DB: Booking: COMPLETED, Kamar: DIRTY
    Note over R,DB: Housekeeping Workflow
    actor HK as Staf Housekeeping
    HK->>SYS: Ambil Tugas Pembersihan Kamar (Status: CLEANING)
    HK->>SYS: Selesai Bersih & Cek Fasilitas (Status: AVAILABLE)
```

---

## 4. Alur Penanganan Komplain & Pemeliharaan (Maintenance)

1. **Pengajuan Tiket**:
   Penyewa membuat tiket via aplikasi mobile (Kategori: AC, Pipa/Air, Kelistrikan, Fasilitas Rusak, Kebersihan) dengan melampirkan foto dan tingkat urgensi (`low`, `medium`, `high`, `emergency`).
2. **Disposisi Staf**:
   Pengelola atau sistem menugaskan tiket kepada staf maintenance/housekeeping.
3. **Pengerjaan & Bukti**:
   Staf mengubah status menjadi `in_progress`, melakukan perbaikan fisik di kamar, dan mengunggah foto bukti perbaikan.
4. **Verifikasi & Penyelesaian**:
   Tiket ditandai `resolved`. Penyewa menerima notifikasi dan dapat memberikan konfirmasi serta ulasan kepuasan layanan.

---

## 5. Detail Alur Pembayaran Manual & Transisi Status Booking (Phase 2 Terimplementasi)

```mermaid
stateDiagram-v2
    [*] --> BookingPending: Penyewa buat booking
    BookingPending --> InvoiceIssued: Sistem terbitkan invoice sewa
    InvoiceIssued --> PaymentUploaded: Penyewa transfer & upload bukti
    
    state PaymentVerification {
        PaymentUploaded --> Verified: Kasir setujui (Cek mutasi rekening)
        PaymentUploaded --> Rejected: Kasir tolak (Alasan wajib diisi)
    }

    Rejected --> PaymentUploaded: Penyewa revisi & upload ulang bukti
    Verified --> InvoiceUpdated: Sisa saldo dikurangi secara atomik
    
    state InvoiceStatusCheck {
        InvoiceUpdated --> PartiallyPaid: Pembayaran < Total Tagihan
        InvoiceUpdated --> FullyPaid: Pembayaran >= Total Tagihan
    }

    PartiallyPaid --> BookingConfirmed: Minimal DP terpenuhi
    FullyPaid --> BookingConfirmed: Booking sah dikonfirmasi
    BookingConfirmed --> CheckIn: Tamu tiba di properti
    CheckIn --> BookingActive: Serah terima kunci & kamar OCCUPIED
```

### Kebijakan Bisnis Pembayaran & Keuangan:
1. **Formula Saldo Tersisa**:
   $$\text{Sisa Saldo} = \text{Total Tagihan} - \sum(\text{Pembayaran Berstatus VERIFIED})$$
2. **Pencegahan Overpayment (Kelebihan Bayar)**:
   - Server menolak pengajuan nominal yang melebihi sisa saldo invoice (`amount > remaining_balance`).
   - Apabila penyewa tidak sengaja mentransfer lebih banyak di bank, kasir mencatat catatan rekonsiliasi dan sisa dana dialokasikan ke deposit atau dikembalikan manual via kas operasional.
3. **Pencegahan Double Processing (Race Condition)**:
   - Proses verifikasi menggunakan transaksi database atomik dengan `DB::raw` / pessimistic row locking `lockForUpdate()`.
   - Percobaan verifikasi kedua pada pembayaran yang sama akan ditolak dengan eksepsi `Payment status is not pending`.
4. **Larangan Verifikasi Sendiri**:
   - Penyewa tidak memiliki akses otorisasi untuk memverifikasi pembayarannya sendiri, bahkan jika memanipulasi request API. Petugas verifikasi wajib berwenang pada cabang properti tersebut.

