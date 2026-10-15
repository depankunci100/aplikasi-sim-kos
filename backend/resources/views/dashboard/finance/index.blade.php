@extends('layouts.app')

@section('title', 'Dashboard Keuangan & Analitik Bisnis - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-pie-chart-fill text-primary-teal me-2"></i>Dashboard Keuangan & Analitik</h3>
        <p class="text-muted mb-0">Pemantauan arus kas, kinerja okupansi, piutang sewa, dan pengeluaran operasional.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.reports.operating-summary') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-calculator"></i> Ringkasan Hasil Usaha
        </a>
        <a href="{{ route('dashboard.expenses.create') }}" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-plus-circle"></i> Catat Pengeluaran
        </a>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('dashboard.finance.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Cabang Properti</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">Semua Cabang Properti</option>
                    @foreach($properties as $prop)
                        <option value="{{ $prop->id }}" {{ ($filters['property_id'] ?? '') == $prop->id ? 'selected' : '' }}>
                            {{ $prop->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary-teal btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('dashboard.finance.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Primary Financial KPIs -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold">Penerimaan Kas (Masuk)</span>
                    <span class="badge bg-success bg-opacity-10 text-success p-2 rounded-circle"><i class="bi bi-arrow-down-left fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-dark">Rp {{ number_format($analytics['kpis']['total_verified_receipts'], 0, ',', '.') }}</div>
                <div class="text-muted small mt-1"><i class="bi bi-check-circle-fill text-success me-1"></i>Penerimaan terverifikasi</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-danger border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold">Pengeluaran Kas (Keluar)</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger p-2 rounded-circle"><i class="bi bi-arrow-up-right fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-dark">Rp {{ number_format($analytics['kpis']['total_paid_expenses'], 0, ',', '.') }}</div>
                <div class="text-muted small mt-1"><i class="bi bi-wallet2 text-danger me-1"></i>Realisasi pengeluaran dibayar</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-primary border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold">Arus Kas Bersih</span>
                    <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-circle"><i class="bi bi-cash-stack fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold {{ $analytics['kpis']['net_cash_flow'] >= 0 ? 'text-primary-teal' : 'text-danger' }}">
                    Rp {{ number_format($analytics['kpis']['net_cash_flow'], 0, ',', '.') }}
                </div>
                <div class="text-muted small mt-1"><i class="bi bi-journal-check me-1"></i>Selisih transaksi tercatat</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-warning border-4">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small fw-semibold">Sisa Piutang Belum Lunas</span>
                    <span class="badge bg-warning bg-opacity-10 text-warning p-2 rounded-circle"><i class="bi bi-clock-history fs-6"></i></span>
                </div>
                <div class="fs-4 fw-bold text-dark">Rp {{ number_format($analytics['kpis']['total_receivables'], 0, ',', '.') }}</div>
                <div class="text-muted small mt-1">
                    <a href="{{ route('dashboard.reports.receivables') }}" class="text-decoration-none text-warning fw-semibold">
                        {{ $analytics['kpis']['unpaid_invoices_count'] }} tagihan belum lunas &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Operational Metrics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Approval Menunggu</div>
                    <div class="fs-5 fw-bold text-dark">{{ $analytics['kpis']['pending_expenses_count'] }} Pengajuan</div>
                    <small class="text-muted">Rp {{ number_format($analytics['kpis']['pending_expenses_amount'], 0, ',', '.') }}</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 text-info">
                    <i class="bi bi-door-open fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Tingkat Okupansi</div>
                    <div class="fs-5 fw-bold text-dark">{{ $analytics['kpis']['occupancy_rate'] }}%</div>
                    <small class="text-muted">{{ $analytics['kpis']['occupied_rooms'] }} dari {{ $analytics['kpis']['total_rooms'] }} kamar</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 text-success">
                    <i class="bi bi-person-check fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Sewa Aktif</div>
                    <div class="fs-5 fw-bold text-dark">{{ $analytics['kpis']['active_bookings_count'] }} Kontrak</div>
                    <small class="text-muted">Bulanan & Guesthouse</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-tools fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Perbaikan Berjalan</div>
                    <div class="fs-5 fw-bold text-dark">{{ $analytics['kpis']['incomplete_maintenance'] }} Tiket</div>
                    <small class="text-muted">Belum terverifikasi</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Trend Table & Breakdown Row -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-graph-up text-primary-teal me-2"></i>Tren Kas Bulanan (6 Bulan Terakhir)</h6>
                <a href="{{ route('dashboard.reports.cash-flow') }}" class="btn btn-sm btn-outline-primary-teal">Lihat Detail Kas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Periode Bulan</th>
                                <th>Penerimaan (Kas Masuk)</th>
                                <th>Pengeluaran (Kas Keluar)</th>
                                <th class="text-end pe-3">Perubahan Bersih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($analytics['chart_trend'] as $trend)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $trend['label'] }}</td>
                                <td>
                                    <span class="text-success fw-bold">Rp {{ number_format($trend['income'], 0, ',', '.') }}</span>
                                </td>
                                <td>
                                    <span class="text-danger fw-bold">Rp {{ number_format($trend['expense'], 0, ',', '.') }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    @php $diff = $trend['income'] - $trend['expense']; @endphp
                                    <span class="badge {{ $diff >= 0 ? 'bg-success' : 'bg-danger' }} px-2 py-1">
                                        {{ $diff >= 0 ? '+' : '' }}Rp {{ number_format($diff, 0, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Belum ada histori transaksi.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-tag text-primary-teal me-2"></i>Komposisi Pengeluaran Operasional</h6>
            </div>
            <div class="card-body">
                @if(count($analytics['expense_categories']) > 0)
                    <div class="d-flex flex-column gap-3">
                        @foreach($analytics['expense_categories'] as $cat)
                        <div>
                            <div class="d-flex justify-content-between small fw-semibold mb-1">
                                <span class="text-capitalize">{{ str_replace('_', ' ', $cat['category']) }}</span>
                                <span>Rp {{ number_format($cat['total'], 0, ',', '.') }}</span>
                            </div>
                            @php
                                $totalExp = $analytics['kpis']['total_paid_expenses'] > 0 ? $analytics['kpis']['total_paid_expenses'] : 1;
                                $pct = min(100, round(($cat['total'] / $totalExp) * 100, 1));
                            @endphp
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary-teal" role="progressbar" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
                        <span>Belum ada data pengeluaran disetujui pada periode ini.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
