# Matriks Peran & Hak Akses (Role & Permissions) - SIM Kos & Guesthouse

Dokumen ini mendefinisikan struktur Role-Based Access Control (RBAC), batas kewenangan, dan izin aksi pada setiap modul **SIM Kos & Guesthouse**.

---

## 1. Daftar Peran (Roles)

| Kode Peran | Nama Peran | Deskripsi |
| :--- | :--- | :--- |
| `super_admin` | Super Administrator | Akses penuh seluruh sistem, manajemen multi-cabang, konfigurasi global, audit log. |
| `owner` | Pemilik Properti | Pemilik bisnis kos/guesthouse. Akses laporan keuangan, bagi hasil, tren okupansi, dan tarif. |
| `manager` | Pengelola Cabang | Bertanggung jawab penuh operasional harian cabang, data penghuni, kontrak, dan verifikasi bayar. |
| `receptionist` | Resepsionis / Kasir | Front desk untuk reservasi guesthouse, check-in, check-out, penerimaan uang tunai/QRIS. |
| `staff` | Housekeeping & Maintenance | Menangani kebersihan kamar, inventaris, dan perbaikan fasilitas berdasarkan tiket komplain. |
| `tenant` | Penyewa / Tamu | Penghuni kamar kos atau tamu guesthouse yang menggunakan aplikasi mobile. |

---

## 2. Matriks Hak Akses (Permission Matrix)

Keterangan:
- **C** = Create (Membuat)
- **R** = Read (Melihat)
- **U** = Update (Mengubah)
- **D** = Delete (Menghapus)
- **-** = Tidak Ada Akses

| Modul / Fitur | Super Admin | Owner | Manager | Receptionist | Staff | Tenant |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Manajemen Properti & Cabang** | CRUD | R (Miliknya) | R (Cabangnya) | R | - | R (Publik) |
| **Tipe & Tarif Kamar** | CRUD | CRUD (Miliknya)| CRUD (Cabang)| R | - | R (Publik) |
| **Kamar Fisik (Room Master)** | CRUD | R (Miliknya) | CRUD (Cabang)| RU (Status) | RU (Status) | - |
| **Inventaris & Aset Kamar** | CRUD | R | CRUD | R | RU (Kondisi) | R (Kamar sendiri) |
| **Data Penghuni (Tenant Profile)** | CRUD | R | CRUD | RU | - | RU (Profil sendiri) |
| **Pemesanan Kos (Bulanan)** | CRUD | R | CRUD | CRU | - | CR (Milik sendiri) |
| **Reservasi Guesthouse (Harian)** | CRUD | R | CRUD | CRUD | - | CR (Milik sendiri) |
| **Proses Check-In & Check-Out** | CRUD | R | CRU | CRU | U (Kesiapan) | R (Status sendiri) |
| **Pencatatan Meteran Listrik/Air** | CRUD | R | CRUD | CR | CR | R (Riwayat sendiri)|
| **Tagihan & Invoice** | CRUD | R | CRUD | CRU | - | R (Tagihan sendiri)|
| **Pembayaran & Verifikasi** | CRUD | R | CRUD | CRU (Kasir) | - | CR (Upload Bukti) |
| **Pengeluaran Operasional (Expense)**| CRUD | CRUD | CRUD | C (Kasir) | - | - |
| **Tiket Komplain & Perbaikan** | CRUD | R | CRUD | CRU | RU (Tugasnya)| CR (Komplain sendiri)|
| **Inspeksi & Checklist Kamar** | CRUD | CRUD (Miliknya) | CRUD (Cabang) | R (Operasional) | CRU (Pelaksana) | RU (Setujui / Keberatan) |
| **Pengumuman Kos** | CRUD | CRUD | CRUD | R | R | R |
| **Laporan Finansial & Okupansi** | CRUD | R (Lengkap) | R (Cabang) | R (Shift kasir)| - | - |

---

## 3. Aturan Keamanan Multi-Tenant (Branch Isolation)

1. **Owner Isolation**:
   Akun bertipe `owner` hanya dapat mengakses data properti yang terdaftar atas kepemilikannya melalui tabel pivot `property_users` atau foreign key `properties.owner_id`.
2. **Staff Branch Isolation**:
   Manager, Resepsionis, dan Staff hanya memiliki akses mutasi data pada cabang properti tempat mereka ditugaskan.
3. **Tenant Data Boundary**:
   Penyewa (`tenant`) divalidasi secara ketat oleh Middleware bahwa `booking.user_id == auth()->id()`, sehingga tidak dimungkinkan melihat invoice, bukti pembayaran, maupun profil KTP penghuni lain.
4. **Larangan Verifikasi Finansial Mandiri (No Self-Verification)**:
   Penyewa tidak dapat memverifikasi pembayarannya sendiri meskipun mengirim payload verifikasi langsung ke server. Sistem melempar `403 Forbidden` (`Anda tidak dapat memverifikasi pembayaran Anda sendiri`).
5. **Staff Operational Boundary**:
   Staf operasional (housekeeping/maintenance) tidak memiliki wewenang finansial untuk melihat saldo kasir atau memverifikasi pembayaran bukti transfer.
6. **Otorisasi & Integritas Inspeksi Kamar (Tahap 3)**:
   - **Super Admin**: Memiliki hak administratif global namun tidak boleh melewati audit trail atau validasi bisnis.
   - **Owner & Manager**: Berhak menjadwalkan inspeksi, menugaskan staf pemeriksa, meninjau komparasi baseline, dan menangani catatan keberatan penyewa.
   - **Staff**: Melaksanakan inspeksi yang ditugaskan, mengisi kondisi checklist, catatan, dan mengunggah foto bukti fisik. Staf pelaksana tidak otomatis berhak mengubah invoice atau memverifikasi pembayaran.
   - **Receptionist / Kasir**: Hanya melihat ringkasan status inspeksi untuk operasional serah terima kunci. Tidak dapat mengedit checklist atau menyetujui laporan atas nama penyewa.
   - **Tenant / Penyewa**: Hanya melihat inspeksi kamar miliknya. Berhak menyetujui (`approve`) atau mengajukan keberatan (`object`) sebelum tenggat `tenant_due_at`. Penyewa tidak dapat mengubah checklist, foto, atau laporan yang dibuat petugas.
7. **Audit Trail**:
   Aksi kritis seperti verifikasi pembayaran (`verified_by`, `verified_at`, `verification_notes`), penolakan bukti (`rejection_reason`), pembatalan booking, perubahan harga kamar, persetujuan pengeluaran, serta setiap tahapan inspeksi (`inspection_logs`) dicatat dengan cap `user_id` dan `timestamp`.
