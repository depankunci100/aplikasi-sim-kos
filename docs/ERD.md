# Desain ERD & Skema Database - SIM Kos & Guesthouse

Dokumen ini berisi rancangan relasi basis data (Entity Relationship Diagram), kamus data lengkap, indexing, dan aturan integritas relasional untuk **SIM Kos & Guesthouse**.

---

## 1. Diagram Relasi Entitas (Mermaid ERD)

```mermaid
erDiagram
    USERS ||--o{ PROPERTY_USERS : "di-assign ke"
    USERS ||--o{ BOOKINGS : "menyewa / memesan"
    USERS ||--o{ INVOICES : "menerima tagihan"
    USERS ||--o{ PAYMENTS : "melakukan pembayaran"
    USERS ||--o{ COMPLAINTS : "mengajukan komplain"
    USERS ||--o| TENANT_PROFILES : "memiliki profil"
    
    PROPERTIES ||--o{ PROPERTY_USERS : "dikelola oleh"
    PROPERTIES ||--o{ ROOM_TYPES : "memiliki tipe kamar"
    PROPERTIES ||--o{ ROOMS : "memiliki kamar fisik"
    PROPERTIES ||--o{ EXPENSES : "memiliki pengeluaran"
    PROPERTIES ||--o{ ANNOUNCEMENTS : "memiliki pengumuman"

    ROOM_TYPES ||--o{ ROOMS : "menjadi template untuk"

    ROOMS ||--o{ ROOM_ASSETS : "memiliki inventaris"
    ROOMS ||--o{ BOOKINGS : "ditempati dalam"
    ROOMS ||--o{ UTILITY_READINGS : "memiliki riwayat meteran"
    ROOMS ||--o{ COMPLAINTS : "lokasi komplain"

    BOOKINGS ||--o{ INVOICES : "menghasilkan tagihan"
    BOOKINGS ||--o{ UTILITY_READINGS : "beban meteran"

    INVOICES ||--o{ PAYMENTS : "dilunasi dengan"

    USERS {
        bigint id PK
        string name
        string email UK
        string phone UK
        string password
        enum role "super_admin, owner, manager, receptionist, staff, tenant"
        string avatar_url
        boolean is_active
        timestamp email_verified_at
        timestamps created_at_updated_at
    }

    TENANT_PROFILES {
        bigint id PK
        bigint user_id FK
        string ktp_number UK
        string ktp_photo
        string emergency_name
        string emergency_phone
        string occupation
        string institution_name
        string vehicle_type
        string vehicle_plate
        timestamps created_at_updated_at
    }

    PROPERTIES {
        bigint id PK
        string code UK
        string name
        enum type "kos, guesthouse, hybrid"
        text address
        string city
        string province
        string postal_code
        decimal latitude
        decimal longitude
        string phone
        text rules
        json facilities
        timestamps created_at_updated_at
    }

    PROPERTY_USERS {
        bigint id PK
        bigint property_id FK
        bigint user_id FK
        enum role "owner, manager, receptionist, staff"
        timestamps created_at_updated_at
    }

    ROOM_TYPES {
        bigint id PK
        bigint property_id FK
        string name
        text description
        decimal base_price_daily
        decimal base_price_monthly
        decimal base_price_yearly
        integer max_occupants
        decimal size_m2
        json facilities
        json photos
        timestamps created_at_updated_at
    }

    ROOMS {
        bigint id PK
        bigint property_id FK
        bigint room_type_id FK
        string room_number
        integer floor
        enum status "available, occupied, maintenance, dirty"
        boolean is_active
        timestamps created_at_updated_at
    }

    ROOM_ASSETS {
        bigint id PK
        bigint room_id FK
        string item_name
        integer quantity
        enum condition "good, slightly_damaged, broken, missing"
        text notes
        timestamps created_at_updated_at
    }

    BOOKINGS {
        bigint id PK
        string booking_code UK
        bigint property_id FK
        bigint room_id FK
        bigint user_id FK
        enum rental_type "daily, weekly, monthly, quarterly, yearly"
        date start_date
        date end_date
        timestamp actual_check_in
        timestamp actual_check_out
        decimal rate_amount
        decimal deposit_amount
        decimal total_amount
        enum status "pending, confirmed, active, completed, cancelled, overstay"
        text notes
        timestamps created_at_updated_at
    }

    INVOICES {
        bigint id PK
        string invoice_number UK
        bigint booking_id FK
        bigint user_id FK
        enum invoice_type "rent, deposit, electricity, water, penalty, service"
        date period_start
        date period_end
        bigint property_id FK
        decimal subtotal
        decimal discount
        decimal penalty
        decimal total_amount
        decimal paid_amount
        enum status "unpaid, partially_paid, paid, overdue, cancelled"
        date due_date
        timestamp paid_at
        timestamps created_at_updated_at
    }

    PAYMENTS {
        bigint id PK
        string payment_code UK
        bigint invoice_id FK
        bigint user_id FK
        enum payment_method "cash, manual_transfer, qris, payment_gateway"
        string payment_provider "manual_bca, manual_mandiri, manual_bri, manual_gopay, midtrans, xendit"
        string sender_name
        string sender_bank
        decimal amount
        string proof_photo
        enum status "pending, verified, rejected"
        bigint verified_by FK
        timestamp verified_at
        text verification_notes
        text rejection_reason
        timestamps created_at_updated_at
    }

    UTILITY_READINGS {
        bigint id PK
        bigint room_id FK
        bigint booking_id FK
        bigint invoice_id FK
        enum utility_type "electricity, water"
        decimal previous_meter
        decimal current_meter
        decimal usage
        decimal rate_per_unit
        decimal total_cost
        date reading_date
        string proof_photo
        timestamps created_at_updated_at
    }

    EXPENSES {
        bigint id PK
        bigint property_id FK
        bigint user_id FK
        string title
        enum category "utility, maintenance, salary, supplies, tax, other"
        decimal amount
        date expense_date
        string receipt_photo
        text notes
        timestamps created_at_updated_at
    }

    COMPLAINTS {
        bigint id PK
        string ticket_number UK
        bigint property_id FK
        bigint room_id FK
        bigint user_id FK
        string title
        text description
        json photos
        enum priority "low, medium, high, emergency"
        enum status "submitted, in_progress, resolved, closed"
        bigint assigned_to FK
        text resolution_notes
        timestamp resolved_at
        timestamps created_at_updated_at
    }

    ANNOUNCEMENTS {
        bigint id PK
        bigint property_id FK
        bigint author_id FK
        string title
        text content
        enum target_role "all, tenant, staff"
        boolean is_published
        timestamp published_at
        timestamps created_at_updated_at
    }
```

---

## 2. Kamus Data & Indexing

### Tabel: `users`
- **Fungsi**: Menyimpan akun pengguna semua peran.
- **Index**:
  - `PRIMARY KEY (id)`
  - `UNIQUE (email)`
  - `UNIQUE (phone)`
  - `INDEX (role, is_active)`

### Tabel: `properties`
- **Fungsi**: Menyimpan unit properti (Kos Putri / Kos Putra / Guesthouse).
- **Index**:
  - `PRIMARY KEY (id)`
  - `UNIQUE (code)`
  - `INDEX (city, type)`

### Tabel: `rooms`
- **Fungsi**: Menyimpan kamar fisik spesifik pada properti.
- **Index**:
  - `PRIMARY KEY (id)`
  - `UNIQUE (property_id, room_number)`
  - `INDEX (status, is_active)`
  - `FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE`
  - `FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE RESTRICT`

### Tabel: `bookings`
- **Fungsi**: Transaksi penyewaan jangka panjang (Kos) maupun reservasi jangka pendek (Guesthouse).
- **Index**:
  - `PRIMARY KEY (id)`
  - `UNIQUE (booking_code)`
  - `INDEX (room_id, start_date, end_date, status)`
  - `INDEX (user_id, status)`
  - `FOREIGN KEY (property_id) REFERENCES properties(id)`
  - `FOREIGN KEY (room_id) REFERENCES rooms(id)`
  - `FOREIGN KEY (user_id) REFERENCES users(id)`

### Tabel: `invoices`
- **Fungsi**: Catatan kewajiban bayar penyewa (biaya sewa, deposit, listrik, denda overstay).
- **Index**:
  - `PRIMARY KEY (id)`
  - `UNIQUE (invoice_number)`
  - `INDEX (booking_id, status, due_date)`
  - `INDEX (user_id, status)`
  - `FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE`
  - `FOREIGN KEY (user_id) REFERENCES users(id)`

### Tabel: `payments`
- **Fungsi**: Rekam jejak transaksi pembayaran masuk.
- **Index**:
  - `PRIMARY KEY (id)`
  - `UNIQUE (payment_code)`
  - `INDEX (invoice_id, status)`
  - `FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE`
  - `FOREIGN KEY (user_id) REFERENCES users(id)`
  - `FOREIGN KEY (verified_by) REFERENCES users(id)`

---

## 3. Aturan Integritas & Anti-Double Booking

1. **Pencegahan Overlapping Booking**:
   Sebelum status booking diubah menjadi `confirmed` atau `active`, sistem mengecek apakah ada booking lain pada `room_id` yang sama dengan rentang waktu beririsan:
   $$\text{Existing.start\_date} < \text{New.end\_date} \quad \text{AND} \quad \text{Existing.end\_date} > \text{New.start\_date}$$
   dengan status `IN ('confirmed', 'active')`.
2. **Kunci Kamar Saat Maintenance**:
   Kamar berstatus `maintenance` atau `dirty` tidak dapat dipilih untuk booking instan sebelum statusnya dikembalikan ke `available` oleh staf housekeeping/pengelola.
3. **Penyimpanan Nilai Uang**:
   Seluruh nilai uang menggunakan tipe data `DECIMAL(14, 2)` guna mencegah kehilangan presisi akibat floating point rounding.
