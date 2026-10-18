@extends('layouts.app')

@section('title', 'Neraca Keuangan (Balance Sheet) — SIM Kos & Guesthouse')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.accounting.journals.index') }}" class="text-decoration-none">Akuntansi</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Neraca Keuangan</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-1">Neraca Keuangan (Balance Sheet)</h1>
            <p class="text-muted small mb-0">Posisi aset, kewajiban titipan uang jaminan, dan ekuitas properti per tanggal pelaporan.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.accounting.reports.balance-sheet', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Ekspor CSV</span>
            </a>
            <a href="{{ route('dashboard.accounting.reports.reconciliation', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-teal d-flex align-items-center gap-2">
                <i class="bi bi-check2-square"></i>
                <span>Rekonsiliasi Subledger</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.accounting.reports.balance-sheet') }}" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">Properti</label>
                    <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ $selectedPropertyId == $prop->id ? 'selected' : '' }}>
                                {{ $prop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">Per Tanggal (As of Date)</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}" required>
                </div>

                <div class="col-md-2 d-flex gap-1 align-items-end pt-3">
                    <button type="submit" class="btn btn-sm btn-primary-teal w-100"><i class="bi bi-arrow-repeat"></i> Perbarui</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Balance Status Banner -->
    @if($data['is_balanced'])
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-shield-check fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-success">Neraca Keuangan SEIMBANG</h6>
                    <small class="text-muted">Total Aset (Rp {{ number_format($data['assets']['total'], 2, ',', '.') }}) = Total Kewajiban & Ekuitas (Rp {{ number_format($data['total_liabilities_and_equity'], 2, ',', '.') }})</small>
                </div>
            </div>
            <span class="badge bg-success px-3 py-2 fs-6">Seimbang</span>
        </div>
    @else
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center justify-content-between p-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-exclamation-triangle fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-danger">PERINGATAN: Neraca Tidak Seimbang!</h6>
                    <small class="text-muted">Terdapat selisih antara Aset dan (Kewajiban + Ekuitas).</small>
                </div>
            </div>
            <span class="badge bg-danger px-3 py-2 fs-6">Selisih: Rp {{ number_format($data['difference'], 2, ',', '.') }}</span>
        </div>
    @endif

    <!-- Two-Column Balance Sheet Layout -->
    <div class="row g-4">
        <!-- Kolom Kiri: ASET -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-safe2-fill text-teal"></i>
                        <span>ASET (ASSETS)</span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted text-uppercase">
                            <tr>
                                <th class="ps-3" style="width: 90px;">Kode</th>
                                <th>Akun Aset</th>
                                <th class="text-end pe-3" style="width: 170px;">Saldo (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['assets']['rows'] as $asset)
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge bg-light text-dark font-monospace border">{{ $asset['code'] }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $asset['name'] }}</div>
                                        @if($asset['category'])
                                            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $asset['category'])) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3 font-monospace fw-semibold text-dark">
                                        Rp {{ number_format($asset['amount'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">Tidak ada saldo aset tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fs-6">
                            <tr>
                                <td colspan="2" class="ps-3 fw-bold text-dark">TOTAL ASET</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-primary">
                                    Rp {{ number_format($data['assets']['total'], 2, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: KEWAJIBAN & EKUITAS -->
        <div class="col-lg-6">
            <!-- 1. Kewajiban -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock-fill text-warning"></i>
                        <span>KEWAJIBAN (LIABILITIES)</span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted text-uppercase">
                            <tr>
                                <th class="ps-3" style="width: 90px;">Kode</th>
                                <th>Akun Kewajiban</th>
                                <th class="text-end pe-3" style="width: 170px;">Saldo (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['liabilities']['rows'] as $liab)
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge bg-light text-dark font-monospace border">{{ $liab['code'] }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $liab['name'] }}</div>
                                        @if($liab['category'])
                                            <small class="text-muted">{{ ucwords(str_replace('_', ' ', $liab['category'])) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3 font-monospace fw-semibold text-dark">
                                        Rp {{ number_format($liab['amount'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Tidak ada saldo kewajiban.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2" class="ps-3 fw-bold text-dark">TOTAL KEWAJIBAN</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-warning">
                                    Rp {{ number_format($data['liabilities']['total'], 2, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- 2. Ekuitas -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-bank2 text-info"></i>
                        <span>EKUITAS (EQUITY)</span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted text-uppercase">
                            <tr>
                                <th class="ps-3" style="width: 90px;">Kode</th>
                                <th>Akun Ekuitas</th>
                                <th class="text-end pe-3" style="width: 170px;">Saldo (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['equity']['rows'] as $eq)
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge bg-light text-dark font-monospace border">{{ $eq['code'] }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $eq['name'] }}</span>
                                    </td>
                                    <td class="text-end pe-3 font-monospace fw-semibold text-dark">
                                        Rp {{ number_format($eq['amount'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Tidak ada saldo ekuitas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2" class="ps-3 fw-bold text-dark">TOTAL EKUITAS</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-info">
                                    Rp {{ number_format($data['equity']['total'], 2, ',', '.') }}
                                </td>
                            </tr>
                            <tr class="fs-6">
                                <td colspan="2" class="ps-3 fw-bold text-dark">TOTAL KEWAJIBAN & EKUITAS</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-primary">
                                    Rp {{ number_format($data['total_liabilities_and_equity'], 2, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
