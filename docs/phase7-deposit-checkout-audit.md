# AUDIT & IMPLEMENTATION PLAN PHASE 7: SECURITY DEPOSIT LEDGER & CHECK-OUT SETTLEMENT
## SIM Kos & Guesthouse (Laravel 13 • Blade + Bootstrap 5 • Flutter Mobile)

---

## 1. Baseline Git & Working Tree
- **Branch Aktif**: `feature/booking-end-to-end`
- **HEAD Commit**: `0aa84952cd0e4f26be1d7ca407ee9ab19eb1905e`
- **Perubahan Lokal Sebelumnya**:
  - 27 file dimodifikasi (sidebar vertikal collapsible, drawer right-side di modul finansial & operasional, modal konfirmasi, safe error handling).
  - 15 file untracked (modul booking end-to-end web & mobile, regression tests).
  - Seluruh perubahan dipertahankan utuh tanpa git reset atau pembersihan working tree.
- **Batasan Keamanan**:
  - Tidak ada commit otomatis, push, merge, force push, atau perubahan remote.
  - Tidak ada operasi database destruktif (`migrate:fresh`, `db:wipe`, truncate).
  - Database pengujian menggunakan SQLite terisolasi in-memory (`:memory:`).

---

## 2. Matriks Fitur Existing vs. Kebutuhan Phase 7

| Modul / Domain | Implementasi Existing (Phase 1–6 & Booking) | Kebutuhan Baru Phase 7 | Risiko Kompatibilitas & Solusi |
|---|---|---|---|
| **Deposit Booking** | Field `deposit_amount` pada `bookings` dan `properties`. Invoice awal menyertakan deposit. | Subledger terpisah: `deposit_ledgers` dan `deposit_mutations` dengan saldo real-time (`held`, `partially_refunded`, `refunded`, `settled`). | Jangan anggap invoice terbit = deposit diterima. Saldo deposit hanya bertambah jika pembayaran deposit terverifikasi. |
| **Pemisahan Finansial** | Laporan Phase 6 menghitung verified payment invoice sebagai pendapatan operasional sewa. | Pisahkan deposit penerimaan dari pendapatan sewa operasional (`revenue`). Penerimaan deposit adalah *liability* (titipan uang jaminan), bukan laba bersih. | Laporan pendapatan Phase 6 menyaring `invoice_type != 'deposit'` atau mencatat subledger deposit terpisah; cash flow mencatat mutasi kas riil. |
| **Room Inspection** | Model `Inspection` Phase 3 sudah mendukung `type: check_out`, `baseline_inspection_id`, `InspectionItem` dengan estimasi biaya & status tenant decision. | Hubungkan inspeksi check-out dengan `check_out_settlements` untuk mengimpor temuan kerusakan menjadi usulan denda/potongan deposit. | **Reuse 100% Phase 3!** Jangan buat tabel/sistem inspeksi baru; gunakan relasi `inspection_id` pada settlement. |
| **Denda & Sengketa (Dispute)** | Tenant approval pada inspeksi (`APPROVED_BY_TENANT`, `OBJECTED_BY_TENANT`). | Rincian potongan per item (`settlement_deductions`). Jika tenant keberatan, status menjadi `disputed` dan ditahan dari eksekusi refund otomatis hingga diputuskan manajer/owner. | Mencegah pemotongan sepihak tanpa otorisasi atau persetujuan yang sah. |
| **Check-Out & Status Kamar** | Field `actual_check_out` pada `bookings`. | Alur check-out terintegrasi: verifikasi tagihan lunas -> inspeksi check-out -> settlement denda -> pencatatan transfer refund -> update status booking `COMPLETED` dan kamar `DIRTY` (bukan langsung `AVAILABLE`). | Menjamin kamar tidak langsung disewakan sebelum dibersihkan/diinspeksi ulang oleh housekeeping. |
| **Web Dashboard** | Sidebar layout vertikal, right-side drawer, modal konfirmasi. | Menu sidebar baru: "Uang Jaminan & Check-Out", halaman daftar deposit subledger, drawer mutasi manual, wizard settlement check-out, modal konfirmasi refund. | Mengikuti pola Bootstrap 5 + Blade existing (`@extends('layouts.app')`). |
| **Mobile Flutter** | Modul Booking, Inspections, Maintenance, Keuangan. | Provider & Screen deposit penyewa & staf (`DepositListScreen`, `DepositDetailScreen`, `CheckOutSettlementScreen`), aksi persetujuan denda/keberatan. | Menggunakan Riverpod, Dio API client, dan GoRouter existing. |

---

## 3. Skema Data Additive (Database Migrations)

1. `deposit_ledgers`:
   - `id`, `deposit_code` (unique), `booking_id` (unique), `property_id`, `tenant_id` (nullable), `initial_deposit_amount`, `total_received`, `total_deductions`, `total_refunded`, `current_balance`, `status` (`pending`, `held`, `partially_refunded`, `refunded`, `settled`, `forfeited`), `notes`, `timestamps`.
2. `deposit_mutations`:
   - `id`, `deposit_ledger_id`, `mutation_code` (unique), `transaction_type` (`deposit_received`, `damage_deduction`, `unpaid_bill_deduction`, `refund_payout`, `adjustment_credit`, `adjustment_debit`), `direction` (`in`, `out`), `amount`, `balance_before`, `balance_after`, `payment_method`, `payment_reference`, `inspection_id` (nullable), `invoice_id` (nullable), `payment_id` (nullable), `performed_by`, `notes`, `created_at`.
3. `check_out_settlements`:
   - `id`, `settlement_code` (unique), `booking_id`, `deposit_ledger_id`, `inspection_id` (nullable), `property_id`, `tenant_id` (nullable), `check_out_date`, `status` (`draft`, `pending_tenant_approval`, `disputed`, `approved`, `settled`, `cancelled`), `deposit_balance_before`, `total_deductions`, `net_refund_amount`, `tenant_objection_notes`, `tenant_approved_at`, `resolved_by`, `resolved_at`, `resolution_notes`, `refund_method`, `refund_destination`, `refund_payment_reference`, `refund_receipt_proof`, `refunded_by`, `refunded_at`, `created_by`, `timestamps`.
4. `settlement_deductions`:
   - `id`, `settlement_id`, `inspection_item_id` (nullable), `title`, `description`, `proposed_amount`, `approved_amount`, `is_approved`, `is_disputed`, `dispute_reason`, `timestamps`.

---

## 4. Rencana Eksekusi Bertahap

- **Tahap 1**: Audit tuntas (dokumen ini).
- **Tahap 2**: Backend, Enums, Models, Service (`DepositService`), integrasi Payment verifikasi -> deposit subledger, dan migrasi additive.
- **Tahap 3**: Integrasi Check-Out & Settlement Service dengan Room Inspection Phase 3 dan transisi status kamar.
- **Tahap 4**: Web Dashboard Blade + Bootstrap 5 (menu sidebar, views subledger, drawers, modals, routes).
- **Tahap 5**: REST API endpoints & Flutter screens/providers.
- **Tahap 6**: Automated testing (Backend Feature tests + Flutter widget/unit tests + analyze).
- **Tahap 7**: Laporan akhir komprehensif.
