@extends('layouts.app')

@section('title', 'Ringkasan Hasil Usaha Operasional - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.finance.index') }}" class="text-decoration-none">Keuangan</a></li>
                <li class="breadcrumb-item active">Ringkasan Hasil Usaha</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0"><i class="bi bi-calculator text-primary-teal me-2"></i>Ringkasan Hasil Usaha Operasional</h3>
        <p class="text-muted mb-0">Estimasi surplus/defisit operasional bulanan berbasis arus kas riil (Cash-Basis Accounting).</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.reports.export', ['type' => 'operating_summary'] + request()->all()) }}" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-file-earmark-spreadsheet"></i> Ekspor CSV
        </a>
        <a href="{{ route('dashboard.finance.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="alert alert-light border shadow-sm mb-4">
    <div class="d-flex gap-3">
        <i class="bi bi-info-circle-fill text-primary-teal fs-4"></i>
        <div class="small text-muted">
            <strong>Catatan Transparansi Finansial:</strong> Ringkasan ini menghitung surplus/defisit operasional langsung dari transaksi kas masuk terverifikasi dikurangi pengeluaran operasional yang telah dibayarkan. Nilai ini merupakan indikator performa arus kas operasional (Cash-Basis), bukan laporan laba rugi akuntansi komersial akrual penuh.
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard.reports.operating-summary') }}" class="row g-3 align-items-end">
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
                <a href="{{ route('dashboard.reports.operating-summary') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Penerimaan Kas (Pendapatan)</div>
            <div class="fs-4 fw-bold text-success">Rp {{ number_format($report['summary']['total_verified_revenue'], 0, ',', '.') }}</div>
            <small class="text-muted">Kas masuk terverifikasi</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Beban Pengeluaran Kas</div>
            <div class="fs-4 fw-bold text-danger">Rp {{ number_format($report['summary']['total_paid_expenses'], 0, ',', '.') }}</div>
            <small class="text-muted">Kas keluar telah dibayar</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 border-start border-primary border-4">
            <div class="text-muted small fw-semibold">Hasil Operasional (Selisih)</div>
            <div class="fs-4 fw-bold {{ $report['summary']['operating_result'] >= 0 ? 'text-primary-teal' : 'text-danger' }}">
                {{ $report['summary']['operating_result'] >= 0 ? '+' : '' }}Rp {{ number_format($report['summary']['operating_result'], 0, ',', '.') }}
            </div>
            <small class="text-muted">{{ $report['summary']['operating_result'] >= 0 ? 'Surplus Operasional' : 'Defisit Operasional' }}</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Margin Kas Bersih</div>
            <div class="fs-4 fw-bold {{ $report['summary']['margin_percentage'] >= 0 ? 'text-primary-teal' : 'text-danger' }}">
                {{ $report['summary']['margin_percentage'] }}%
            </div>
            <small class="text-muted">Rasio efisiensi kas</small>
        </div>
    </div>
</div>

<!-- Monthly Trend Comparison -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar3-range text-primary-teal me-2"></i>Perbandingan Performa Bulanan (Tren 6 Bulan)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Periode Bulan</th>
                        <th>Penerimaan Kas (Masuk)</th>
                        <th>Pengeluaran Kas (Keluar)</th>
                        <th>Hasil Operasional</th>
                        <th class="text-end pe-3">Status Kinerja</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['monthly_trend'] as $row)
                    <tr>
                        <td class="ps-3 fw-bold text-dark">{{ $row['month'] }}</td>
                        <td class="text-success fw-semibold">Rp {{ number_format($row['cash_in'], 0, ',', '.') }}</td>
                        <td class="text-danger fw-semibold">Rp {{ number_format($row['cash_out'], 0, ',', '.') }}</td>
                        <td class="fw-bold {{ $row['operating_result'] >= 0 ? 'text-primary-teal' : 'text-danger' }}">
                            {{ $row['operating_result'] >= 0 ? '+' : '' }}Rp {{ number_format($row['operating_result'], 0, ',', '.') }}
                        </td>
                        <td class="text-end pe-3">
                            @if($row['operating_result'] > 0)
                                <span class="badge bg-success px-2 py-1"><i class="bi bi-graph-up-arrow me-1"></i>Surplus</span>
                            @elseif($row['operating_result'] < 0)
                                <span class="badge bg-danger px-2 py-1"><i class="bi bi-graph-down-arrow me-1"></i>Defisit</span>
                            @else
                                <span class="badge bg-secondary px-2 py-1">Seimbang (0)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
