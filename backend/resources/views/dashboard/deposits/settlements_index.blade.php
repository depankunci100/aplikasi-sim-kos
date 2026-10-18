@extends('layouts.app')

@section('title', 'Penyelesaian Check-Out & Refund')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header Page -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">Penyelesaian Check-Out & Uang Jaminan</h4>
            <p class="text-muted small mb-0">Inspeksi akhir kamar, perhitungan potongan kerusakan, dan otorisasi pengembalian deposit.</p>
        </div>
        <div>
            @if(auth()->user()->canManageFinances())
            <button type="button" class="btn btn-teal shadow-sm d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#initiateSettlementDrawer" aria-controls="initiateSettlementDrawer">
                <i class="bi bi-box-arrow-right"></i>
                <span>Ajukan Check-Out Baru</span>
            </button>
            @endif
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('dashboard.deposits.settlements.index') }}" method="GET">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-semibold mb-1">Status Settlement</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua Status</option>
                            @foreach(\App\Enums\SettlementStatus::cases() as $st)
                                <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                                    {{ $st->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold mb-1">Pencarian</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Kode settlement, nomor booking, nama penghuni..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-teal w-100" title="Terapkan Filter">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="{{ route('dashboard.deposits.settlements.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small">
                        <tr>
                            <th class="ps-3 py-3">Kode Settlement</th>
                            <th>Penyewa & Kamar</th>
                            <th>Tgl Check-Out</th>
                            <th>Saldo Deposit</th>
                            <th>Potongan Kerusakan</th>
                            <th>Sisa Pengembalian (Refund)</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlements as $s)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('dashboard.deposits.settlements.show', $s->id) }}" class="fw-bold text-teal text-decoration-none">
                                    {{ $s->settlement_code }}
                                </a>
                                <small class="text-muted d-block">Booking: {{ $s->booking?->booking_code ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $s->tenant?->name ?? $s->booking?->customer_name ?? '-' }}</span>
                                <div class="text-muted small">
                                    Kamar {{ $s->booking?->room?->room_number ?? '-' }} &bull; {{ $s->property?->name ?? '-' }}
                                </div>
                            </td>
                            <td class="small">{{ $s->check_out_date?->isoFormat('D MMM Y') }}</td>
                            <td class="small fw-semibold">Rp {{ number_format($s->deposit_balance_before, 0, ',', '.') }}</td>
                            <td>
                                @if((float)$s->total_deductions > 0)
                                    <span class="text-danger fw-semibold">-Rp {{ number_format($s->total_deductions, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-muted">Rp 0 (Tanpa Denda)</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-teal fs-6">
                                    Rp {{ number_format($s->net_refund_amount, 0, ',', '.') }}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $s->status->badgeClass() }} px-2 py-1">
                                    {{ $s->status->label() }}
                                </span>
                            </td>
                            <td class="pe-3 text-end">
                                <a href="{{ route('dashboard.deposits.settlements.show', $s->id) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye me-1"></i> Rincian
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-box-arrow-right fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada penyelesaian check-out tercatat.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($settlements->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $settlements->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Right-Side Drawer: Ajukan Check-Out Baru -->
<div class="offcanvas offcanvas-end drawer-lg" tabindex="-1" id="initiateSettlementDrawer" aria-labelledby="initiateSettlementDrawerLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="initiateSettlementDrawerLabel">
            <i class="bi bi-box-arrow-right text-teal me-2"></i> Ajukan Check-Out Kamar
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <form action="{{ route('dashboard.deposits.settlements.initiate') }}" method="POST">
        @csrf
        <div class="offcanvas-body">
            <div class="mb-3">
                <label for="drawer_booking_id" class="form-label small fw-semibold text-muted">Pilih Hunian Aktif <span class="text-danger">*</span></label>
                <select name="booking_id" id="drawer_booking_id" class="form-select form-select-sm" required>
                    <option value="">-- Pilih Kamar / Penyewa --</option>
                    @foreach($activeBookings as $b)
                        <option value="{{ $b->id }}" {{ (string)request('booking_id') === (string)$b->id ? 'selected' : '' }}>
                            Kamar {{ $b->room?->room_number }} &bull; {{ $b->customer_name }} ({{ $b->property?->name }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="drawer_checkout_date" class="form-label small fw-semibold text-muted">Tanggal Efektif Check-Out <span class="text-danger">*</span></label>
                <input type="date" name="check_out_date" id="drawer_checkout_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="mb-3">
                <label for="drawer_inspection_id" class="form-label small fw-semibold text-muted">Hubungkan Inspeksi Check-Out (Phase 3)</label>
                <select name="inspection_id" id="drawer_inspection_id" class="form-select form-select-sm">
                    <option value="">-- Tanpa Inspeksi (Otomatis Bersih) --</option>
                    @foreach($checkOutInspections as $insp)
                        <option value="{{ $insp->id }}">
                            {{ $insp->inspection_code }} &bull; Kamar {{ $insp->room?->room_number }} ({{ $insp->completed_at?->format('d/m/Y') }})
                        </option>
                    @endforeach
                </select>
                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                    Temuan kerusakan atau barang hilang dari inspeksi akan otomatis diimpor sebagai usulan denda.
                </small>
            </div>

            <div class="mb-3">
                <label for="drawer_refund_dest" class="form-label small fw-semibold text-muted">Rekening / Tujuan Pengembalian Deposit</label>
                <input type="text" name="refund_destination" id="drawer_refund_dest" class="form-control form-control-sm" placeholder="Contoh: BCA 123456789 a/n Budi">
            </div>

            <hr class="my-3">
            <h6 class="fw-bold small text-dark mb-2">Potongan Tambahan (Opsional)</h6>
            <div class="mb-2">
                <input type="text" name="custom_deduction_title" class="form-control form-control-sm mb-2" placeholder="Judul denda (misal: Kehilangan Kunci / Kartu Akses)">
                <input type="number" name="custom_deduction_amount" class="form-control form-control-sm mb-2" placeholder="Nominal denda (Rp)">
                <input type="text" name="custom_deduction_desc" class="form-control form-control-sm" placeholder="Keterangan singkat...">
            </div>
        </div>

        <div class="offcanvas-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-sm btn-teal px-3">
                <i class="bi bi-check-lg me-1"></i> Mulai Proses Settlement
            </button>
        </div>
    </form>
</div>

@if(request('booking_id'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var el = document.getElementById('initiateSettlementDrawer');
        if (el && typeof bootstrap !== 'undefined') {
            var drawer = new bootstrap.Offcanvas(el);
            drawer.show();
        }
    });
</script>
@endif
@endsection
