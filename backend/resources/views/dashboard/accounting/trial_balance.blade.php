@extends('layouts.app')

@section('title', 'Neraca Saldo (Trial Balance) — SIM Kos & Guesthouse')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.accounting.journals.index') }}" class="text-decoration-none">Akuntansi</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Neraca Saldo</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-1">Neraca Saldo (Trial Balance)</h1>
            <p class="text-muted small mb-0">Verifikasi keseimbangan saldo debit dan kredit seluruh akun buku besar per tanggal pelaporan.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.accounting.reports.trial-balance', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Ekspor CSV</span>
            </a>
            <a href="{{ route('dashboard.accounting.journals.index', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-journal-text"></i>
                <span>Lihat Jurnal</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.accounting.reports.trial-balance') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Properti</label>
                    <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ $selectedPropertyId == $prop->id ? 'selected' : '' }}>
                                {{ $prop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Saldo Mulai Dari (Opsional)</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Per Tanggal (As of Date)</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}" required>
                </div>

                <div class="col-md-2 d-flex gap-1 align-items-end pt-3">
                    <button type="submit" class="btn btn-sm btn-primary-teal w-100"><i class="bi bi-arrow-repeat"></i> Perbarui</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Keseimbangan Banner -->
    @if($data['summary']['is_balanced'])
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-check-lg fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-success">Neraca Saldo SEIMBANG (Balanced)</h6>
                    <small class="text-muted">Total Debit dan Total Kredit sama persis: <strong>Rp {{ number_format($data['summary']['total_debit'], 2, ',', '.') }}</strong></small>
                </div>
            </div>
            <span class="badge bg-success px-3 py-2 fs-6">Selisih: Rp 0,00</span>
        </div>
    @else
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-exclamation-triangle fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-danger">PERINGATAN: Neraca Saldo TIDAK SEIMBANG!</h6>
                    <small class="text-muted">Terdapat selisih antara Total Debit dan Total Kredit.</small>
                </div>
            </div>
            <span class="badge bg-danger px-3 py-2 fs-6">Selisih: Rp {{ number_format($data['summary']['difference'], 2, ',', '.') }}</span>
        </div>
    @endif

    <!-- Tabel Neraca Saldo -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-table text-teal"></i>
                <span>Rincian Akun Neraca Saldo — {{ $data['property']['name'] }} (Per {{ \Carbon\Carbon::parse($data['period']['as_of_date'])->format('d M Y') }})</span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 100px;">Kode</th>
                        <th>Nama Akun</th>
                        <th>Klasifikasi</th>
                        <th>Normal</th>
                        <th class="text-end" style="width: 140px;">Mutasi Debit (Rp)</th>
                        <th class="text-end" style="width: 140px;">Mutasi Kredit (Rp)</th>
                        <th class="text-end" style="width: 160px;">Saldo Akhir Debit (Rp)</th>
                        <th class="text-end pe-3" style="width: 160px;">Saldo Akhir Kredit (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['rows'] as $r)
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark font-monospace fw-bold border">
                                    {{ $r['code'] }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $r['name'] }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border">
                                    {{ $r['type_label'] }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $r['normal_balance'] === 'debit' ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' }}">
                                    {{ strtoupper($r['normal_balance']) }}
                                </span>
                            </td>
                            <td class="text-end font-monospace">
                                {{ $r['total_debit'] > 0 ? number_format($r['total_debit'], 2, ',', '.') : '-' }}
                            </td>
                            <td class="text-end font-monospace">
                                {{ $r['total_credit'] > 0 ? number_format($r['total_credit'], 2, ',', '.') : '-' }}
                            </td>
                            <td class="text-end font-monospace fw-semibold text-dark">
                                {{ $r['ending_debit'] > 0 ? number_format($r['ending_debit'], 2, ',', '.') : '-' }}
                            </td>
                            <td class="text-end pe-3 font-monospace fw-semibold text-dark">
                                {{ $r['ending_credit'] > 0 ? number_format($r['ending_credit'], 2, ',', '.') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                Tidak ada mutasi akun buku besar pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-bold fs-6">
                    <tr>
                        <td colspan="4" class="ps-3 text-uppercase text-dark">TOTAL NERACA SALDO</td>
                        <td class="text-end font-monospace">-</td>
                        <td class="text-end font-monospace">-</td>
                        <td class="text-end font-monospace text-dark">Rp {{ number_format($data['summary']['total_debit'], 2, ',', '.') }}</td>
                        <td class="text-end pe-3 font-monospace text-dark">Rp {{ number_format($data['summary']['total_credit'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
