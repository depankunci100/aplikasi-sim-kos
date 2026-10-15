@extends('layouts.app')

@section('title', 'Laporan Arus Kas (Cash Flow) - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.finance.index') }}" class="text-decoration-none">Keuangan</a></li>
                <li class="breadcrumb-item active">Laporan Arus Kas</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0"><i class="bi bi-arrow-left-right text-primary-teal me-2"></i>Laporan Arus Kas (Cash Flow)</h3>
        <p class="text-muted mb-0">Rekonsiliasi pergerakan kas masuk dari pembayaran sewa dan kas keluar pengeluaran operasional.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.reports.export', ['type' => 'cash_flow'] + request()->all()) }}" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-file-earmark-spreadsheet"></i> Ekspor CSV
        </a>
        <a href="{{ route('dashboard.finance.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard.reports.cash-flow') }}" class="row g-3 align-items-end">
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
                <a href="{{ route('dashboard.reports.cash-flow') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm p-3 border-start border-success border-4">
            <div class="text-muted small fw-semibold">Total Kas Masuk (Penerimaan)</div>
            <div class="fs-4 fw-bold text-success">+ Rp {{ number_format($report['summary']['total_cash_in'], 0, ',', '.') }}</div>
            <small class="text-muted">Dari pembayaran sewa & utilitas terverifikasi</small>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm p-3 border-start border-danger border-4">
            <div class="text-muted small fw-semibold">Total Kas Keluar (Pengeluaran)</div>
            <div class="fs-4 fw-bold text-danger">- Rp {{ number_format($report['summary']['total_cash_out'], 0, ',', '.') }}</div>
            <small class="text-muted">Realisasi pembayaran biaya operasional</small>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm p-3 border-start border-primary border-4">
            <div class="text-muted small fw-semibold">Perubahan Bersih Kas (Net Cash)</div>
            <div class="fs-4 fw-bold {{ $report['summary']['net_cash_change'] >= 0 ? 'text-primary-teal' : 'text-danger' }}">
                {{ $report['summary']['net_cash_change'] >= 0 ? '+' : '' }}Rp {{ number_format($report['summary']['net_cash_change'], 0, ',', '.') }}
            </div>
            <small class="text-muted">{{ $report['summary']['transaction_count'] }} transaksi tercatat pada periode ini</small>
        </div>
    </div>
</div>

<!-- Chronological Cash Flow Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary-teal me-2"></i>Buku Mutasi Kas Masuk & Keluar (Kronologis)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tipe</th>
                        <th>Waktu / Tanggal</th>
                        <th>No. Referensi</th>
                        <th>Keterangan Transaksi</th>
                        <th>Cabang Properti</th>
                        <th>Metode</th>
                        <th class="text-end pe-3">Nominal Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['transactions'] as $tx)
                    <tr>
                        <td class="ps-3">
                            @if($tx['type'] === 'in')
                                <span class="badge bg-success"><i class="bi bi-arrow-down-left me-1"></i>Kas Masuk</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-arrow-up-right me-1"></i>Kas Keluar</span>
                            @endif
                        </td>
                        <td><small>{{ $tx['date'] }}</small></td>
                        <td class="font-monospace fw-bold text-dark">{{ $tx['reference'] }}</td>
                        <td>{{ $tx['description'] }}</td>
                        <td>{{ $tx['property_name'] }}</td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $tx['method'])) }}</span></td>
                        <td class="text-end pe-3 fw-bold {{ $tx['type'] === 'in' ? 'text-success' : 'text-danger' }}">
                            {{ $tx['type'] === 'in' ? '+' : '-' }}Rp {{ number_format($tx['amount'], 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            <span>Tidak ada mutasi kas masuk maupun keluar pada periode ini.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
