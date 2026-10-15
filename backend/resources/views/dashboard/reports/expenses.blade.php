@extends('layouts.app')

@section('title', 'Laporan Pengeluaran Operasional - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.finance.index') }}" class="text-decoration-none">Keuangan</a></li>
                <li class="breadcrumb-item active">Laporan Pengeluaran</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0"><i class="bi bi-cart-x text-primary-teal me-2"></i>Laporan Pengeluaran Operasional</h3>
        <p class="text-muted mb-0">Rincian beban biaya, klasifikasi pos anggaran, dan realisasi kas keluar yang telah dibayarkan.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.reports.export', ['type' => 'expenses'] + request()->all()) }}" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
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
        <form method="GET" action="{{ route('dashboard.reports.expenses') }}" class="row g-3 align-items-end">
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
                <a href="{{ route('dashboard.reports.expenses') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Pengeluaran Diajukan</div>
            <div class="fs-4 fw-bold text-dark">Rp {{ number_format($report['summary']['total_submitted'], 0, ',', '.') }}</div>
            <small class="text-muted">Menunggu persetujuan</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Total Disetujui (Approved)</div>
            <div class="fs-4 fw-bold text-info">Rp {{ number_format($report['summary']['total_approved'], 0, ',', '.') }}</div>
            <small class="text-muted">Siap dicairkan / dibayar</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 border-start border-danger border-4">
            <div class="text-muted small fw-semibold">Realisasi Kas Keluar (Dibayar)</div>
            <div class="fs-4 fw-bold text-danger">Rp {{ number_format($report['summary']['actual_cash_out'], 0, ',', '.') }}</div>
            <small class="text-muted">Uang yang benar-benar keluar</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">Total Dokumen</div>
            <div class="fs-4 fw-bold text-dark">{{ $report['summary']['total_count'] }}</div>
            <small class="text-muted">Transaksi periode ini</small>
        </div>
    </div>
</div>

<!-- Category Breakdown Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-tags text-primary-teal me-2"></i>Distribusi Berdasarkan Kategori Pos Biaya</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Kategori</th>
                        <th>Jumlah Pengeluaran</th>
                        <th>Total Nilai Disetujui</th>
                        <th class="text-end pe-3">Realisasi Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['by_category'] as $cat)
                    <tr>
                        <td class="ps-3 fw-bold text-capitalize">{{ str_replace('_', ' ', $cat->category) }}</td>
                        <td>{{ $cat->count }} transaksi</td>
                        <td class="fw-semibold">Rp {{ number_format($cat->total_amount, 0, ',', '.') }}</td>
                        <td class="text-end pe-3 fw-bold text-danger">Rp {{ number_format($cat->total_paid, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-3 text-muted">Belum ada pengeluaran disetujui pada periode ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-table text-primary-teal me-2"></i>Rincian Seluruh Transaksi Pengeluaran</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">No. Pengeluaran</th>
                        <th>Tanggal</th>
                        <th>Properti</th>
                        <th>Kategori & Judul</th>
                        <th>Vendor</th>
                        <th>Nominal</th>
                        <th>Status Approval</th>
                        <th class="text-end pe-3">Status Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['expenses'] as $exp)
                    <tr>
                        <td class="ps-3 font-monospace fw-bold">
                            <a href="{{ route('dashboard.expenses.show', $exp->id) }}" class="text-decoration-none">
                                {{ $exp->expense_number }}
                            </a>
                        </td>
                        <td>{{ $exp->expense_date?->format('d M Y') }}</td>
                        <td>{{ $exp->property?->name ?? '-' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $exp->title }}</div>
                            <small class="text-muted">{{ $exp->expenseCategory?->name ?? ucfirst($exp->category) }}</small>
                        </td>
                        <td>{{ $exp->vendor_name ?: '-' }}</td>
                        <td class="fw-bold">Rp {{ number_format($exp->amount, 0, ',', '.') }}</td>
                        <td><span class="badge bg-{{ $exp->approval_status->badgeClass() }}">{{ $exp->approval_status->label() }}</span></td>
                        <td class="text-end pe-3"><span class="badge bg-{{ $exp->payment_status->badgeClass() }}">{{ $exp->payment_status->label() }}</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            <span>Tidak ada transaksi pengeluaran pada periode ini.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
