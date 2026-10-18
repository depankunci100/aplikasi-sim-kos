# PANDUAN PENGUJIAN & CHECKLIST UAT: TENANT EXPERIENCE ADDENDUM
## SIM Kos & Guesthouse — Phase 10 Addendum

Dokumen checklist ini memandu pengujian penerimaan pengguna (User Acceptance Testing / UAT) untuk memastikan seluruh fitur Phase 10 berjalan sesuai spesifikasi di Web Dashboard (Blade) dan Mobile App (Flutter).

---

## 1. CHECKLIST UAT FITUR A: PENGUMUMAN (ANNOUNCEMENTS)

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| A.1 | Web: Buat Pengumuman Global | 1. Buka Web Dashboard `/dashboard/announcements`.<br>2. Klik "Buat Pengumuman Baru".<br>3. Pilih Cakupan: "Semua Properti", Target: "Semua".<br>4. Masukkan Judul & Isi, simpan dengan status "Published". | Pengumuman tersimpan dan berstatus `published`. | [ ] |
| A.2 | Web: Buat Pengumuman Spesifik Properti | 1. Buat pengumuman dengan memilih properti "Kos Soehat".<br>2. Target: "Penyewa Saja". Status: "Published". | Pengumuman hanya terasosiasi dengan Kos Soehat. | [ ] |
| A.3 | Web: Pengumuman Draft | 1. Buat pengumuman dengan status "Draft". | Pengumuman tersimpan tetapi TIDAK muncul di daftar penyewa. | [ ] |
| A.4 | Flutter: Daftar Pengumuman Penyewa | 1. Login aplikasi Flutter sebagai Penyewa Kos Soehat.<br>2. Buka menu Pengumuman. | Pengumuman Global dan Pengumuman Kos Soehat tampil. Pengumuman properti lain TIDAK bocor. | [ ] |
| A.5 | Flutter: Detail Pengumuman & Mark as Read | 1. Klik salah satu pengumuman yang belum dibaca.<br>2. Lihat detail judul, isi, tanggal, dan pengirim.<br>3. Kembali ke daftar. | Pengumuman otomatis ditandai sebagai telah dibaca. Badge belum dibaca berkurang 1. | [ ] |
| A.6 | Flutter: Badge Belum Dibaca | 1. Periksa ikon lonceng/pengumuman di HomeScreen. | Menampilkan angka/badge pengumuman yang belum dibaca secara real-time. | [ ] |

---

## 2. CHECKLIST UAT FITUR B: PENGAJUAN PEMBERSIHAN KAMAR (HOUSEKEEPING)

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| B.1 | Flutter: Deteksi Kamar Aktif | 1. Login sebagai penyewa dengan booking aktif.<br>2. Buka menu Layanan Kamar / Housekeeping. | Kamar aktif penyewa (nomor kamar & nama properti) terdeteksi otomatis. | [ ] |
| B.2 | Flutter: Kirim Pengajuan Pembersihan | 1. Pilih tanggal besok dan slot waktu (misal: "Pagi (08:00 - 11:00)").<br>2. Tulis catatan: "Mohon ganti sprei".<br>3. Tekan "Kirim Pengajuan". | Pengajuan berhasil dibuat dengan status `pending`. Muncul di riwayat pengajuan. | [ ] |
| B.3 | Flutter: Pencegahan Duplikasi Pengajuan | 1. Coba ajukan pembersihan lagi untuk tanggal yang sama pada kamar tersebut. | Sistem menolak dengan pesan validasi bahwa pengajuan untuk tanggal tersebut sudah ada. | [ ] |
| B.4 | Flutter: Batalkan Pengajuan Pending | 1. Pada kartu riwayat pengajuan berstatus `pending`, tekan "Batalkan Permintaan".<br>2. Konfirmasi pembatalan dialog. | Status berubah menjadi `cancelled`. | [ ] |
| B.5 | Web/Staf: Update Status Operasional | 1. Login sebagai staf di Web/Mobile.<br>2. Ubah status task housekeeping dari `pending` ke `scheduled` lalu `completed`. | Status terbarui. Penyewa dapat melihat status terkini tanpa bisa mengubahnya sendiri. | [ ] |

---

## 3. CHECKLIST UAT FITUR C: PENGAJUAN INSPEKSI KAMAR (ROOM INSPECTION)

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| C.1 | Flutter: Ajukan Inspeksi Kerusakan/Kondisi | 1. Buka menu Inspeksi Kamar.<br>2. Pilih tipe inspeksi: "Keluhan / Kerusakan Kamar".<br>3. Tulis deskripsi keluhan dan lampirkan foto.<br>4. Tekan "Kirim Pengajuan". | Pengajuan tersimpan di backend dengan status `pending` dan foto tersimpan di private storage. | [ ] |
| C.2 | Proteksi Duplikasi Inspeksi | 1. Kirim ulang pengajuan dengan tipe yang sama saat inspeksi sebelumnya masih pending. | Sistem menolak pengajuan ganda untuk menghindari penumpukan tiket aktif. | [ ] |
| C.3 | Proteksi Checklist Petugas | 1. Verifikasi dari sisi penyewa apakah ada opsi mengubah checklist resmi staf. | Penyewa TIDAK memiliki akses mengubah item checklist pemeriksaan petugas. | [ ] |
| C.4 | Flutter: Tenant Approval Hasil Inspeksi | 1. Staf menyelesaikan inspeksi kamar (`completed`).<br>2. Penyewa membuka detail inspeksi.<br>3. Penyewa menyetujui hasil dengan tanda tangan/konfirmasi. | Status approval tercatat dengan timestamp dan audit log. | [ ] |

---

## 4. CHECKLIST UAT FITUR D: NOTIFIKASI IN-APP

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| D.1 | Notifikasi Pengumuman Baru | 1. Admin mempublikasikan pengumuman baru.<br>2. Penyewa membuka layar Notifikasi (`/notifications`). | Notifikasi pengumuman baru muncul di daftar. | [ ] |
| D.2 | Notifikasi Perubahan Status Housekeeping | 1. Staf menjadwalkan task pembersihan.<br>2. Penyewa memeriksa notifikasi. | Notifikasi "Layanan Kamar Dijadwalkan" diterima penyewa. | [ ] |
| D.3 | Notifikasi Penyelesaian Inspeksi | 1. Staf menyelesaikan inspeksi kamar.<br>2. Penyewa memeriksa notifikasi. | Notifikasi hasil inspeksi siap ditinjau diterima. | [ ] |

---

## 5. CHECKLIST UAT FITUR E: OCR KTP & SATU NIK PER AKUN

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| E.1 | Flutter: Pindai KTP On-Device | 1. Buka menu Verifikasi Identitas & KTP.<br>2. Tekan tombol "Pindai KTP (OCR On-Device)".<br>3. Sistem mengekstraksi teks gambar KTP. | NIK 16 digit dan nama otomatis terisi di form tanpa mengirim gambar ke pihak ketiga. | [ ] |
| E.2 | Flutter: Toleransi Karakter Ambigu (O -> 0) | 1. Uji pemindaian teks KTP dengan karakter 'O' atau 'I'. | Algoritma kompensasi cerdas mengonversi karakter menjadi angka yang benar. | [ ] |
| E.3 | Flutter: Review & Koreksi Manual | 1. Coba ubah nomor NIK atau nama di field teks sebelum menyimpan. | Pengguna dapat mengoreksi dan mengonfirmasi hasil OCR secara manual. | [ ] |
| E.4 | Backend: Validasi 16 Digit NIK | 1. Kirim NIK kurang dari 16 digit (misal: 15 digit). | Validasi HTTP 422: Format NIK wajib 16 digit angka. | [ ] |
| E.5 | Backend: Pencegahan Duplikasi NIK Global | 1. Daftarkan NIK `3573011508980004` pada Akun A.<br>2. Coba simpan NIK yang sama pada Akun B. | Backend menolak pendaftaran Akun B dengan pesan "NIK sudah terdaftar pada akun lain". | [ ] |
| E.6 | Keamanan: Masking NIK di Layar & API | 1. Periksa respons API dan tampilan profil. | NIK disamarkan menjadi format `357301******0004`. Tidak ada NIK plaintext di layar umum. | [ ] |

---

## 6. CHECKLIST UAT FITUR F: PEMULIHAN PASSWORD PENYEWA (WEB)

| No | Skenario UAT | Langkah Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| F.1 | Web: Admin Inisiasi Reset Password | 1. Buka Web Dashboard pemulihan password.<br>2. Masukkan email penyewa terdaftar.<br>3. Tekan "Kirim Tautan Pemulihan". | Token unik dibuat, dicatat ke `password_reset_logs`, password plaintext TIDAK ditampilkan ke admin. | [ ] |
| F.2 | Web/API: Masa Berlaku Token (60 Menit) | 1. Gunakan token setelah 61 menit atau ubah created_at di test. | Sistem menolak token dengan pesan "Token pemulihan telah kedaluwarsa". | [ ] |
| F.3 | Rate Limiting Reset Password | 1. Kirim permintaan reset untuk email yang sama 4 kali dalam kurun waktu 1 jam. | Permintaan ke-4 diblokir oleh rate limiter: "Terlalu banyak permintaan reset". | [ ] |
| F.4 | Pencabutan Sesi Aktif (Sanctum Revocation) | 1. Login di aplikasi Flutter (mendapatkan auth token).<br>2. Selesaikan reset password dari web/link.<br>3. Coba lakukan aksi di aplikasi mobile dengan token lama. | Token lama otomatis dicabut (HTTP 401 Unauthorized). Pengguna wajib login dengan password baru. | [ ] |
| F.5 | Audit Trail Pemulihan Akun | 1. Periksa tabel `password_reset_logs`. | Tercatat aktor pemohon, target penyewa, alamat IP, dan status tanpa menyimpan token/password. | [ ] |

---

## 7. CATATAN HASIL & KESIMPULAN UAT
- **Penguji**: Senior QA / Lead Engineer
- **Lingkungan Uji**: Backend Laravel 13 Test Suite (SQLite isolated DB) & Flutter Debug APK v1.0.0.
- **Kondisi Khusus**: Pengujian staging MySQL 8 pada hosting Hostinger ditunda hingga kredensial server dan database staging MySQL 8 disediakan oleh tim operasional hosting.
