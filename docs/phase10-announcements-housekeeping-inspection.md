# DOKUMENTASI TEKNIS & INTEGRASI: PENGUMUMAN, HOUSEKEEPING, & INSPEKSI KAMAR
## SIM Kos & Guesthouse — Phase 10 Addendum

Dokumen ini menjelaskan arsitektur, integrasi modul, skema database, endpoint API, state machine, dan kontrol keamanan untuk Fitur A (Pengumuman), Fitur B (Pengajuan Pembersihan Kamar), dan Fitur C (Pengajuan Inspeksi Kamar).

---

## 1. FITUR A — MODUL PENGUMUMAN (ANNOUNCEMENTS)

### 1.1. Arsitektur & Isolasi Data
Modul pengumuman dirancang dengan pemisahan cakupan (property scoping) dan isolasi multi-tenant:
1. **Cakupan Pengumuman (`property_id`)**:
   - `property_id = NULL`: Pengumuman global yang berlaku untuk seluruh properti kos/guesthouse.
   - `property_id = <id>`: Pengumuman spesifik untuk properti tertentu. Hanya penyewa dengan booking aktif pada properti tersebut (atau staf/admin properti) yang dapat membaca pengumuman ini.
2. **Target Role (`target_role`)**:
   - `'all'`: Ditujukan untuk seluruh pengguna dalam cakupan.
   - `'tenant'`: Ditujukan khusus untuk penyewa/penghuni.
   - `'staff'`: Pengumuman operasional internal staf (tidak akan pernah dikembalikan ke endpoint penyewa).
3. **Penyewa Tenant Isolation Query**:
   Query penyewa menggunakan Eloquent Scope `forUser($user)`:
   ```php
   // Announcement.php
   public function scopeForUser(Builder $query, User $user): Builder
   {
       if ($user->hasAnyRole(['admin', 'superadmin', 'owner'])) {
           return $query;
       }
       if ($user->hasRole('tenant')) {
           $userPropertyIds = Booking::where('user_id', $user->id)
               ->whereIn('status', ['confirmed', 'active', 'checked_in'])
               ->pluck('property_id')
               ->unique()
               ->toArray();

           return $query->where(function ($q) use ($userPropertyIds) {
               $q->whereNull('property_id')
                 ->orWhereIn('property_id', $userPropertyIds);
           })->whereIn('target_role', ['all', 'tenant']);
       }
       // Staf isolasi berdasarkan properti yang ditugaskan
       ...
   }
   ```

### 1.2. Skema Database Pembacaan (`announcement_reads`)
Untuk melacak status pembacaan secara efisien tanpa duplikasi:
```sql
CREATE TABLE announcement_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uniq_announcement_user (announcement_id, user_id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```
Pencatatan pembacaan dilakukan secara idempoten via `AnnouncementRead::firstOrCreate(['announcement_id' => $id, 'user_id' => $user->id])`.

### 1.3. Endpoint API
| Metode | Endpoint | Deskripsi | Otorisasi |
|---|---|---|---|
| `GET` | `/api/v1/announcements` | Daftar pengumuman sesuai hak akses user (filter `is_read`, paginasi) | Sanctum Auth (`tenant`, `staff`, `admin`) |
| `GET` | `/api/v1/announcements/{id}` | Detail pengumuman & lampiran | Sanctum Auth (dengan verifikasi cakupan) |
| `POST` | `/api/v1/announcements/{id}/read` | Menandai pengumuman telah dibaca | Sanctum Auth |
| `GET` | `/api/v1/announcements/unread-count` | Jumlah pengumuman belum dibaca untuk badge navigasi | Sanctum Auth |

### 1.4. Website Dashboard Pengumuman (Blade + Bootstrap 5)
- Menu navigasi sidebar: `Pengumuman` (`/dashboard/announcements`).
- Manajemen status (`draft` / `published`).
- Pilihan target properti (Semua Properti atau dropdown properti terdaftar).
- Target audiens (`Semua`, `Penyewa Saja`, `Staf Saja`).
- Upload lampiran privat secara aman ke `storage/app/private/announcements`.

---

## 2. FITUR B — PENGAJUAN PEMBERSIHAN KAMAR (HOUSEKEEPING)

### 2.1. Integrasi dengan Modul Housekeeping Phase 5
Sistem memanfaatkan model dan tabel `housekeeping_tasks` yang telah ada tanpa membuat modul kedua.
- **Validasi Akses Kamar**:
  Penyewa hanya dapat mengajukan pembersihan untuk kamar yang sedang dihuni secara sah (`bookings.status IN ('confirmed', 'active', 'checked_in')` dan tanggal saat ini berada dalam rentang `start_date` sampai `end_date`).
- **Pencegahan Duplikasi**:
  Sistem memeriksa apakah ada task pembersihan kamar yang berstatus `pending` atau `scheduled` pada tanggal pembersihan yang sama untuk kamar tersebut. Jika ada, sistem menolak pengajuan baru dengan pesan validasi yang jelas.
- **Kebijakan Finansial & Billing**:
  Sesuai mandat tata kelola, tidak ada penagihan otomatis atau biaya tersembunyi yang ditambahkan tanpa aturan billing resmi.

### 2.2. State Machine & Transisi Status
```
[Diajukan oleh Penyewa]
          │
          ▼
       pending ◄──────────────────┐
          │                       │ (Dapat dibatalkan oleh penyewa)
          ├───────────────────────┴──► cancelled
          ▼
      scheduled (Ditugaskan staf/petugas)
          │
          ▼
     in_progress (Pengerjaan checklist kebersihan)
          │
          ▼
      completed (Selesai dengan verifikasi checklist & catatan)
```
- **Aturan Otorisasi Status**:
  Penyewa HANYA memiliki izin untuk membuat pengajuan (`pending`) dan membatalkan pengajuan miliknya yang MASIH dalam status `pending`. Perubahan status operasional ke `scheduled`, `in_progress`, atau `completed` hanya dapat dilakukan oleh staf atau supervisor berwenang.

### 2.3. Endpoint API
| Metode | Endpoint | Deskripsi | Otorisasi |
|---|---|---|---|
| `GET` | `/api/v1/housekeeping/active-room` | Mengambil data kamar aktif penyewa yang berhak diajukan | Sanctum (`role:tenant`) |
| `POST` | `/api/v1/housekeeping/request` | Mengajukan permintaan pembersihan kamar | Sanctum (`role:tenant`) |
| `POST` | `/api/v1/housekeeping/{id}/cancel` | Membatalkan pengajuan (hanya jika masih pending) | Sanctum (`role:tenant`, pemilik task) |
| `GET` | `/api/v1/housekeeping/my-requests` | Riwayat pengajuan pembersihan kamar penyewa | Sanctum (`role:tenant`) |

---

## 3. FITUR C — PENGAJUAN INSPEKSI KAMAR (ROOM INSPECTION)

### 3.1. Integrasi dengan Modul Room Inspection Phase 3
Sistem menggunakan modul inspeksi kamar Phase 3 (`room_inspections`, `inspection_items`, `inspection_media`, `inspection_logs`).
- **Alur Pengajuan**:
  1. Penyewa memilih kamar yang dihuni (divalidasi kepemilikannya).
  2. Memilih tipe inspeksi (`routine`, `complaint`, atau `move_out`).
  3. Menuliskan catatan/alasan inspeksi.
  4. Melampirkan foto pendukung (disimpan di private storage disk).
  5. Sistem membuat record `RoomInspection` dengan status `pending` dan otomatis menginisialisasi checklist standar ruangan dari database.
- **Pencegahan Duplikasi**:
  Sistem menolak pengajuan jika kamar telah memiliki inspeksi berstatus `pending`, `scheduled`, atau `in_progress` dengan tipe yang sama.

### 3.2. Batasan Hak Akses & Persetujuan Penyewa (Tenant Approval)
- Penyewa **dilarang** mengubah checklist resmi hasil pemeriksaan staf.
- Penyewa hanya dapat melihat item checklist, catatan, dan foto setelah inspeksi selesai (`completed`).
- Fitur persetujuan penyewa (`tenant_approval`) tetap didukung: penyewa dapat menandatangani/menyetujui berita acara inspeksi (misalnya saat move-in atau move-out).
- **Proteksi Finansial**: Inspeksi kamar tidak otomatis memotong deposit atau mengubah status tagihan. Pemotongan deposit tetap mengikuti modul settlement checkout Phase 7 dengan otorisasi akuntansi berjenjang.

### 3.3. Endpoint API
| Metode | Endpoint | Deskripsi | Otorisasi |
|---|---|---|---|
| `POST` | `/api/v1/inspections/request` | Pengajuan inspeksi kamar oleh penyewa | Sanctum (`role:tenant`) |
| `GET` | `/api/v1/inspections` | Daftar inspeksi (terisolasi untuk kamar penyewa) | Sanctum Auth |
| `GET` | `/api/v1/inspections/{id}` | Detail inspeksi, checklist publik, foto privat | Sanctum Auth |
| `POST` | `/api/v1/inspections/{id}/tenant-approval` | Persetujuan hasil inspeksi oleh penyewa | Sanctum (`role:tenant`) |

---

## 4. SISTEM NOTIFIKASI IN-APP & STATUS SYNC

- Notifikasi in-app dicatat ke tabel `notifications` Laravel standar (`type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`).
- Peristiwa yang men-trigger notifikasi:
  1. Pengumuman baru dipublikasikan -> notifikasi ke seluruh penyewa di properti terkait.
  2. Status permohonan housekeeping berubah -> notifikasi ke penyewa pemilik kamar.
  3. Status permohonan inspeksi kamar disetujui/dijadwalkan -> notifikasi ke penyewa.
  4. Hasil inspeksi selesai dan membutuhkan tenant approval -> notifikasi persetujuan ke penyewa.
- Aplikasi Flutter menyediakan `NotificationsScreen` (`/notifications`) untuk membaca riwayat dan menandai notifikasi telah dibaca.
- Tidak ada klaim push notification FCM/APNs aktif sebelum sertifikat APNs dan server key FCM dikonfigurasikan di environment hosting.
