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
| **Master Meter Fisik** | CRUD | CRUD (Miliknya)| CRUD (Cabang)| R | - | - |
| **Pengaturan Tarif Utilitas** | CRUD | CRUD (Miliknya)| CRUD (Cabang)| R | - | - |
| **Pencatatan Meteran Listrik/Air** | CRUD | CRUD | CRUD | CR | CR (Pelaksana) | R (Kamar Sendiri)|
| **Review & Koreksi Nilai Meter** | CRUD | CRUD | CRUD | RU (Review) | - | - |
| **Penerbitan Invoice Utilitas** | CRUD | CRUD | CRUD | CR (Kasir) | - | - |
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
7. **Otorisasi & Batasan Utilitas Meteran (Tahap 4)**:
   - **Super Admin**: Akses penuh lintas properti dan audit log koreksi.
   - **Owner & Manager**: Berwenang membuat/mengedit master meter fisik, menetapkan tarif dengan masa berlaku, meninjau pembacaan (`review`), melakukan koreksi teknis (`correct`), dan menerbitkan tagihan invoice.
   - **Receptionist / Kasir**: Berwenang melihat komponen tagihan utilitas pada invoice existing, meninjau pembacaan, dan menerbitkan tagihan invoice utilitas. Kasir *tidak otomatis* memiliki hak untuk merekayasa atau mengubah pembacaan meter fisik di luar alur review sah.
   - **Staff**: Berwenang mencatat nilai meter fisik lapangan (`record`) dan mengunggah foto bukti display. Staf tidak memiliki hak administratif untuk menetapkan tarif atau menerbitkan invoice finansial.
   - **Tenant / Penyewa**: Hanya berhak melihat riwayat pemakaian dan rincian tarif untuk kamar miliknya sendiri (`tenant_consumption`). Penyewa *tidak dapat* mengubah pembacaan meter resmi atau memanipulasi jumlah tagihan final.
8. **Audit Trail Mutlak**:
   Aksi kritis seperti verifikasi pembayaran (`verified_by`, `verified_at`), penolakan bukti (`rejection_reason`), pembatalan booking, perubahan harga kamar, inspeksi (`inspection_logs`), serta seluruh mutasi pembacaan, koreksi, dan penagihan utilitas (`utility_reading_logs`) dicatat secara permanen dengan `user_id`, aksi, data sebelum/sesudah, dan cap waktu.
