@extends('layouts.app')

@section('title', 'Subledger Uang Jaminan (Deposit)')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header Page -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">Subledger Uang Jaminan (Security Deposit)</h4>
            <p class="text-muted small mb-0">Kelola kewajiban titipan deposit, rekonsiliasi saldo penyewa, dan riwayat mutasi.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('dashboard.deposits.settlements.index') }}" class="btn btn-outline-teal d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-box-arrow-right"></i>
                <span>Penyelesaian Check-Out</span>
            </a>
        </div>
    </div>

    <!-- 5 KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-success border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-success">Total Deposit Ditahan (Aktif)</span>
                    <h4 class="fw-bold text-dark mb-0 mt-1">Rp {{ number_format($kpis['total_held_amount'], 0, ',', '.') }}</h4>
                    <small class="text-muted">{{ number_format($kpis['count_held']) }} penyewa aktif</small>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-warning border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-warning">Menunggu Pembayaran</span>
                    <h4 class="fw-bold text-warning mb-0 mt-1">{{ number_format($kpis['count_pending']) }}</h4>
                    <small class="text-muted">Belum terverifikasi</small>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-primary border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-primary">Total Telah Dikembalikan</span>
                    <h4 class="fw-bold text-primary mb-0 mt-1">Rp {{ number_format($kpis['total_refunded'], 0, ',', '.') }}</h4>
                    <small class="text-muted">Refund payout ke rekening penyewa</small>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-secondary border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-secondary">Telah Diselesaikan (Settled)</span>
                    <h4 class="fw-bold text-dark mb-0 mt-1">{{ number_format($kpis['count_settled']) }}</h4>
                    <small class="text-muted">Check-out selesai tuntas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('dashboard.deposits.index') }}" method="GET">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-semibold mb-1">Status Deposit</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua Status</option>
                            @foreach(\App\Enums\DepositStatus::cases() as $st)
                                <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                                    {{ $st->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-semibold mb-1">Properti</label>
                        <select name="property_id" class="form-select form-select-sm">
                            <option value="">Semua Properti</option>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}" {{ request('property_id') == $prop->id ? 'selected' : '' }}>
                                    {{ $prop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted small fw-semibold mb-1">Pencarian</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Kode deposit, booking, nama penyewa..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-teal w-100" title="Terapkan Filter">
                            <i class="bi bi-funnel"></i>
                        </button>
                        <a href="{{ route('dashboard.deposits.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset">
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
                            <th class="ps-3 py-3">Kode Deposit</th>
                            <th>Penyewa & Kamar</th>
                            <th>Properti</th>
                            <th>Saldo Awal Ditargetkan</th>
                            <th>Saldo Deposit Saat Ini</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgers as $item)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('dashboard.deposits.show', $item->id) }}" class="fw-bold text-teal text-decoration-none">
                                    {{ $item->deposit_code }}
                                </a>
                                <small class="text-muted d-block">Booking: {{ $item->booking?->booking_code ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $item->tenant?->name ?? $item->booking?->customer_name ?? 'Tamu Publik' }}</span>
                                <div class="text-muted small">
                                    Kamar {{ $item->booking?->room?->room_number ?? '-' }} ({{ $item->booking?->room?->roomType?->name ?? '-' }})
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $item->property?->name ?? '-' }}</div>
                                <small class="text-muted">{{ $item->property?->city ?? '-' }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">Rp {{ number_format($item->initial_deposit_amount, 0, ',', '.') }}</div>
                                <small class="text-muted">Masuk: Rp {{ number_format($item->total_received, 0, ',', '.') }}</small>
                            </td>
                            <td>
                                <div class="fw-bold text-{{ (float)$item->current_balance > 0 ? 'success' : 'secondary' }} fs-6">
                                    Rp {{ number_format($item->current_balance, 0, ',', '.') }}
                                </div>
                                @if((float)$item->total_deductions > 0)
                                    <small class="text-danger d-block">Denda: -Rp {{ number_format($item->total_deductions, 0, ',', '.') }}</small>
                                @endif
                                @if((float)$item->total_refunded > 0)
                                    <small class="text-primary d-block">Refund: Rp {{ number_format($item->total_refunded, 0, ',', '.') }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $item->status->badgeClass() }} px-2 py-1">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <td class="pe-3 text-end">
                                <a href="{{ route('dashboard.deposits.show', $item->id) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-clock-history me-1"></i> Mutasi
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-x fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada data uang jaminan (deposit) yang tercatat.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($ledgers->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $ledgers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
