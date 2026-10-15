@extends('layouts.app')

@section('title', 'Laporan Pendapatan & Penerimaan Kas - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.finance.index') }}" class="text-decoration-none">Keuangan</a></li>
                <li class="breadcrumb-item active">Laporan Pendapatan</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0"><i class="bi bi-cash-stack text-primary-teal me-2"></i>Laporan Pendapatan & Penerimaan Kas</h3>
        <p class="text-muted mb-0">Rincian invoice diterbitkan, realisasi kas masuk terverifikasi, dan breakdown utilitas.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.reports.export', ['type' => 'income'] + request()->all()) }}" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
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
        <form method="GET" action="{{ route('dashboard.reports.income') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
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
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary-teal btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button>
                <a href="{{ route('dashboard.reports.income') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Total Tagihan (Invoice Ditagih)</div>
            <div class="fs-4 fw-bold text-dark">Rp {{ number_format($report['summary']['total_invoiced'], 0, ',', '.') }}</div>
            <small class="text-muted">{{ $report['summary']['invoice_count'] }} lembar invoice</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 border-start border-success border-4">
            <div class="text-muted small fw-semibold">Penerimaan Kas Terverifikasi</div>
            <div class="fs-4 fw-bold text-success">Rp {{ number_format($report['summary']['verified_cash_received'], 0, ',', '.') }}</div>
            <small class="text-muted">{{ $report['summary']['verified_payment_count'] }} transaksi pembayaran sah</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Penerimaan Sewa Kamar</div>
            <div class="fs-4 fw-bold text-primary-teal">Rp {{ number_format($report['summary']['rental_cash'], 0, ',', '.') }}</div>
            <small class="text-muted">Bulanan & Guesthouse</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Penerimaan Tagihan Utilitas</div>
            <div class="fs-4 fw-bold text-info">Rp {{ number_format($report['summary']['utility_cash'], 0, ',', '.') }}</div>
            <small class="text-muted">Listrik PLN & Air PDAM</small>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-check text-primary-teal me-2"></i>Rincian Pembayaran Terverifikasi (Penerimaan Kas)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Kode Bayar</th>
                        <th>No. Invoice</th>
                        <th>Properti</th>
                        <th>Penyewa</th>
                        <th>Metode Bayar</th>
                        <th>Tanggal Verifikasi</th>
                        <th class="text-end pe-3">Nominal Masuk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['payments'] as $pmt)
                    <tr>
                        <td class="ps-3 fw-bold font-monospace text-dark">{{ $pmt->payment_code }}</td>
                        <td>
                            <a href="{{ route('dashboard.invoices.show', $pmt->invoice_id) }}" class="text-decoration-none fw-semibold">
                                {{ $pmt->invoice?->invoice_number ?? '-' }}
                            </a>
                        </td>
                        <td>{{ $pmt->invoice?->property?->name ?? '-' }}</td>
                        <td>{{ $pmt->invoice?->user?->name ?? '-' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $pmt->payment_method)) }}</span></td>
                        <td>{{ $pmt->verified_at ? $pmt->verified_at->format('d M Y H:i') : '-' }}</td>
                        <td class="text-end pe-3 fw-bold text-success">Rp {{ number_format($pmt->amount, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            <span>Tidak ada transaksi penerimaan kas pada rentang tanggal ini.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
