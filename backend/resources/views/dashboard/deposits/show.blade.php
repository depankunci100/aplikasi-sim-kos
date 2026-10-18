@extends('layouts.app')

@section('title', 'Detail Subledger Deposit ' . $ledger->deposit_code)

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.deposits.index') }}" class="text-teal text-decoration-none">Uang Jaminan</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $ledger->deposit_code }}</li>
            </ol>
        </nav>
        <a href="{{ route('dashboard.deposits.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    <!-- Header Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden">
        <div class="card-body p-4 bg-white border-start border-{{ $ledger->status->badgeClass() }} border-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0">{{ $ledger->deposit_code }}</h4>
                        <span class="badge bg-{{ $ledger->status->badgeClass() }} fs-6 px-3 py-1">
                            {{ $ledger->status->label() }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">
                        Pemesanan: <strong class="text-dark">{{ $ledger->booking?->booking_code ?? '-' }}</strong> &bull;
                        Properti: <strong>{{ $ledger->property?->name ?? '-' }}</strong>
                    </p>
                </div>

                <div class="d-flex gap-2">
                    @if(auth()->user()->canManageFinances())
                    <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#adjustmentDrawer" aria-controls="adjustmentDrawer">
                        <i class="bi bi-pencil-square"></i>
                        <span>Koreksi Audit</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Grid Rincian Saldo & Info Penyewa -->
    <div class="row g-4 mb-4">
        <!-- Kolom Kiri: Ringkasan Saldo Subledger -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-wallet2 text-teal me-2"></i> Rekonsiliasi Saldo Uang Jaminan</h6>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted" style="width: 50%;">Nominal Deposit Disyaratkan</td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($ledger->initial_deposit_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-success-subtle">
                                    <td class="text-success fw-semibold">Penerimaan Terverifikasi (Kas Masuk)</td>
                                    <td class="text-end fw-bold text-success">+Rp {{ number_format($ledger->total_received, 0, ',', '.') }}</td>
                                </tr>
                                @if((float)$ledger->total_deductions > 0)
                                <tr class="table-danger-subtle">
                                    <td class="text-danger fw-semibold">Potongan Kerusakan/Denda Disahkan</td>
                                    <td class="text-end fw-bold text-danger">-Rp {{ number_format($ledger->total_deductions, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                @if((float)$ledger->total_refunded > 0)
                                <tr class="table-primary-subtle">
                                    <td class="text-primary fw-semibold">Pengembalian yang Telah Dibayar (Refund)</td>
                                    <td class="text-end fw-bold text-primary">-Rp {{ number_format($ledger->total_refunded, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                <tr class="table-light">
                                    <td class="fw-bold fs-6">Saldo Tersedia Saat Ini (Net Liability)</td>
                                    <td class="text-end fw-bold fs-6 text-{{ (float)$ledger->current_balance > 0 ? 'success' : 'secondary' }}">
                                        Rp {{ number_format($ledger->current_balance, 0, ',', '.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size: 0.75rem;">
                        *Uang jaminan disimpan terpisah dari pendapatan operasional sewa kos dan hanya dipotong setelah denda disetujui atau dikembalikan saat check-out.
                    </small>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Info Penghuni & Kamar -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person text-teal me-2"></i> Calon / Penghuni Terkait</h6>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted small" style="width: 35%;">Nama Penyewa</td>
                            <td class="fw-semibold text-dark">{{ $ledger->tenant?->name ?? $ledger->booking?->customer_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">No. HP / WA</td>
                            <td class="text-dark">{{ $ledger->tenant?->phone ?? $ledger->booking?->customer_phone ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Email</td>
                            <td class="text-dark">{{ $ledger->tenant?->email ?? $ledger->booking?->customer_email ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Kamar Ditempati</td>
                            <td class="fw-bold text-dark">Kamar {{ $ledger->booking?->room?->room_number ?? '-' }} ({{ $ledger->booking?->room?->roomType?->name ?? '-' }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Status Sewa</td>
                            <td>
                                <span class="badge bg-{{ $ledger->booking?->status?->badgeClass() ?? 'secondary' }}">
                                    {{ $ledger->booking?->status?->label() ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Mutasi (Subledger Mutations Table) -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-teal me-2"></i> Buku Besar Mutasi (Audit Trail)</h6>
            <span class="badge bg-secondary-subtle text-secondary">{{ $ledger->mutations->count() }} Transaksi</span>
        </div>
        <div class="card-body p-0 mt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small">
                        <tr>
                            <th class="ps-3 py-3">Waktu</th>
                            <th>Kode Mutasi</th>
                            <th>Tipe Transaksi</th>
                            <th>Nominal</th>
                            <th>Saldo Sebelum</th>
                            <th>Saldo Sesudah</th>
                            <th>Metode & Referensi</th>
                            <th class="pe-3">Pelaku / Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledger->mutations as $m)
                        <tr>
                            <td class="ps-3 text-muted small">
                                {{ $m->created_at->isoFormat('D MMM Y, HH:mm') }}
                            </td>
                            <td class="fw-semibold text-dark">{{ $m->mutation_code }}</td>
                            <td>
                                <span class="badge bg-{{ $m->direction === 'in' ? 'success' : 'danger' }}-subtle text-{{ $m->direction === 'in' ? 'success' : 'danger' }} border">
                                    {{ $m->transaction_type->label() }}
                                </span>
                            </td>
                            <td class="fw-bold text-{{ $m->direction === 'in' ? 'success' : 'danger' }}">
                                {{ $m->direction === 'in' ? '+' : '-' }}Rp {{ number_format($m->amount, 0, ',', '.') }}
                            </td>
                            <td class="text-muted small">Rp {{ number_format($m->balance_before, 0, ',', '.') }}</td>
                            <td class="fw-semibold text-dark">Rp {{ number_format($m->balance_after, 0, ',', '.') }}</td>
                            <td class="small">
                                <span class="text-capitalize">{{ $m->payment_method ?: 'Internal' }}</span>
                                @if($m->payment_reference)
                                    <small class="text-muted d-block">{{ $m->payment_reference }}</small>
                                @endif
                            </td>
                            <td class="pe-3 small">
                                <span class="fw-semibold">{{ $m->performer?->name ?? 'Sistem' }}</span>
                                @if($m->notes)
                                    <small class="text-muted d-block">{{ $m->notes }}</small>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                Belum ada riwayat mutasi untuk deposit ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Right-Side Drawer: Koreksi Audit Manual -->
<div class="offcanvas offcanvas-end drawer-md" tabindex="-1" id="adjustmentDrawer" aria-labelledby="adjustmentDrawerLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="adjustmentDrawerLabel">
            <i class="bi bi-pencil-square text-teal me-2"></i> Koreksi Audit Deposit
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <form action="{{ route('dashboard.deposits.adjustment', $ledger->id) }}" method="POST">
        @csrf
        <div class="offcanvas-body">
            <p class="text-muted small mb-3">
                Koreksi audit hanya boleh dilakukan untuk memperbaiki selisih pencatatan atau pengalihan dana dengan pencatatan jejak audit yang sah.
            </p>

            <div class="mb-3">
                <label class="form-label small fw-semibold text-muted">Tipe Penyesuaian <span class="text-danger">*</span></label>
                <select name="type" class="form-select form-select-sm" required>
                    <option value="adjustment_credit">Koreksi Kredit (+ Penambahan Saldo)</option>
                    <option value="adjustment_debit">Koreksi Debit (- Pengurangan Saldo)</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="adj_amount" class="form-label small fw-semibold text-muted">Nominal Koreksi (Rp) <span class="text-danger">*</span></label>
                <input type="number" step="1000" min="1" name="amount" id="adj_amount" class="form-control form-control-sm" placeholder="Contoh: 50000" required>
            </div>

            <div class="mb-3">
                <label for="adj_reason" class="form-label small fw-semibold text-muted">Alasan / Dasar Otorisasi <span class="text-danger">*</span></label>
                <textarea name="reason" id="adj_reason" rows="3" class="form-control form-control-sm" placeholder="Keterangan audit trail wajib diisi..." required></textarea>
            </div>
        </div>

        <div class="offcanvas-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-sm btn-teal px-3">
                <i class="bi bi-check-lg me-1"></i> Simpan Koreksi
            </button>
        </div>
    </form>
</div>
@endsection
