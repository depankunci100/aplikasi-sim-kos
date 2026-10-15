@extends('layouts.app')

@section('title', 'Laporan Piutang Sewa & Aging - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.finance.index') }}" class="text-decoration-none">Keuangan</a></li>
                <li class="breadcrumb-item active">Laporan Piutang</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary-teal me-2"></i>Laporan Piutang Sewa & Umur Tagihan (Aging)</h3>
        <p class="text-muted mb-0">Daftar invoice belum lunas, saldo sisa tagihan, dan pengelompokan umur jatuh tempo.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.reports.export', ['type' => 'receivables'] + request()->all()) }}" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1">
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
        <form method="GET" action="{{ route('dashboard.reports.receivables') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
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
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Tipe Invoice</label>
                <select name="invoice_type" class="form-select form-select-sm">
                    <option value="">Semua Tipe Tagihan</option>
                    <option value="room_rental" {{ ($filters['invoice_type'] ?? '') == 'room_rental' ? 'selected' : '' }}>Sewa Kamar</option>
                    <option value="utility" {{ ($filters['invoice_type'] ?? '') == 'utility' ? 'selected' : '' }}>Utilitas (Listrik/Air)</option>
                    <option value="other" {{ ($filters['invoice_type'] ?? '') == 'other' ? 'selected' : '' }}>Lainnya</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary-teal btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button>
                <a href="{{ route('dashboard.reports.receivables') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Aging Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 border-start border-danger border-4">
            <div class="text-muted small fw-semibold">Total Seluruh Piutang</div>
            <div class="fs-4 fw-bold text-danger">Rp {{ number_format($report['summary']['total_receivables'], 0, ',', '.') }}</div>
            <small class="text-muted">{{ $report['summary']['invoice_count'] }} tagihan belum lunas</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">0 - 30 Hari (Lancar/Baru)</div>
            <div class="fs-4 fw-bold text-dark">Rp {{ number_format($report['summary']['bucket_current'], 0, ',', '.') }}</div>
            <small class="text-muted">Jatuh tempo baru</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">31 - 60 Hari (Perhatian)</div>
            <div class="fs-4 fw-bold text-warning">Rp {{ number_format($report['summary']['bucket_31_60'], 0, ',', '.') }}</div>
            <small class="text-muted">Perlu penagihan aktif</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <div class="text-muted small fw-semibold">> 60 Hari (Kritis / Macet)</div>
            <div class="fs-4 fw-bold text-danger">Rp {{ number_format($report['summary']['bucket_61_90'] + $report['summary']['bucket_over_90'], 0, ',', '.') }}</div>
            <small class="text-muted">Tunggakan lama</small>
        </div>
    </div>
</div>

<!-- Receivables Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Daftar Tagihan Belum Lunas (Accounts Receivable)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">No. Invoice</th>
                        <th>Cabang Properti</th>
                        <th>Penyewa & Kamar</th>
                        <th>Jatuh Tempo</th>
                        <th>Keterlambatan</th>
                        <th>Total Tagihan</th>
                        <th>Sudah Dibayar</th>
                        <th class="text-end pe-3">Sisa Piutang</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['invoices'] as $inv)
                    <tr>
                        <td class="ps-3 fw-bold font-monospace">
                            <a href="{{ route('dashboard.invoices.show', $inv['id']) }}" class="text-decoration-none">
                                {{ $inv['invoice_number'] }}
                            </a>
                        </td>
                        <td>{{ $inv['property_name'] }}</td>
                        <td>
                            <div class="fw-semibold">{{ $inv['tenant_name'] }}</div>
                            <small class="text-muted"><i class="bi bi-door-closed me-1"></i>Kamar {{ $inv['room_number'] }}</small>
                        </td>
                        <td>{{ $inv['due_date'] }}</td>
                        <td>
                            @if($inv['days_overdue'] > 0)
                                <span class="badge bg-danger">{{ $inv['days_overdue'] }} hari terlambat</span>
                            @else
                                <span class="badge bg-success">Belum lewat tempo</span>
                            @endif
                            <div class="small text-muted mt-1">{{ $inv['aging_bucket'] }}</div>
                        </td>
                        <td>Rp {{ number_format($inv['total_amount'], 0, ',', '.') }}</td>
                        <td class="text-success">Rp {{ number_format($inv['paid_amount'], 0, ',', '.') }}</td>
                        <td class="text-end pe-3 fw-bold text-danger">Rp {{ number_format($inv['remaining_balance'], 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
                            <span>Luar biasa! Tidak ada tagihan atau piutang yang tertunggak pada properti ini.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
