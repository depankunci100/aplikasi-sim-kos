@extends('layouts.app')

@section('title', 'Laporan Laba Rugi (Income Statement) — SIM Kos & Guesthouse')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.accounting.journals.index') }}" class="text-decoration-none">Akuntansi</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Laporan Laba Rugi</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-1">Laporan Laba Rugi (Income Statement)</h1>
            <p class="text-muted small mb-0">Kinerja finansial pendapatan dan beban operasional berbasis pembukuan berpasangan.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.accounting.reports.income-statement', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Ekspor CSV</span>
            </a>
            <a href="{{ route('dashboard.accounting.reports.balance-sheet', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-teal d-flex align-items-center gap-2">
                <i class="bi bi-cash-stack"></i>
                <span>Neraca Keuangan</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.accounting.reports.income-statement') }}" class="row g-2 align-items-center">
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
                    <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" required>
                </div>

                <div class="col-md-2 d-flex gap-1 align-items-end pt-3">
                    <button type="submit" class="btn btn-sm btn-primary-teal w-100"><i class="bi bi-arrow-repeat"></i> Perbarui</button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Cards Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-success-subtle text-success p-3 d-flex align-items-center justify-content-center">
                        <i class="bi bi-graph-up-arrow fs-3"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-medium text-uppercase">Total Pendapatan Usaha</span>
                        <h4 class="fw-bold mb-0 text-success">Rp {{ number_format($data['total_revenue'], 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-danger-subtle text-danger p-3 d-flex align-items-center justify-content-center">
                        <i class="bi bi-graph-down-arrow fs-3"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-medium text-uppercase">Total Beban Operasional</span>
                        <h4 class="fw-bold mb-0 text-danger">Rp {{ number_format($data['total_expense'], 2, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    @php $isProfit = $data['net_income'] >= 0; @endphp
                    <div class="rounded-3 {{ $isProfit ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }} p-3 d-flex align-items-center justify-content-center">
                        <i class="bi {{ $isProfit ? 'bi-trophy-fill' : 'bi-exclamation-octagon-fill' }} fs-3"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-medium text-uppercase">{{ $isProfit ? 'Laba Bersih Operasional' : 'Rugi Bersih Operasional' }}</span>
                        <h4 class="fw-bold mb-0 {{ $isProfit ? 'text-primary' : 'text-warning' }}">
                            Rp {{ number_format($data['net_income'], 2, ',', '.') }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rincian Laba Rugi -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-receipt-cutoff text-teal"></i>
                <span>Rincian Akun Laba Rugi — {{ $data['property']['name'] }} ({{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }})</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <!-- 1. PENDAPATAN -->
            <div class="px-4 py-2 bg-light text-muted small fw-bold text-uppercase border-top border-bottom">
                I. Pendapatan Operasional (Revenue)
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        @forelse($data['revenues'] as $rev)
                            <tr>
                                <td class="ps-4" style="width: 120px;">
                                    <span class="badge bg-light text-dark font-monospace border">{{ $rev['code'] }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $rev['name'] }}</span>
                                </td>
                                <td class="text-end pe-4 font-monospace fw-semibold text-success" style="width: 200px;">
                                    Rp {{ number_format($rev['amount'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="ps-4 py-3 text-muted">Tidak ada pendapatan pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2" class="ps-4 fw-bold text-dark">TOTAL PENDAPATAN OPERASIONAL</td>
                            <td class="text-end pe-4 font-monospace fw-bold text-success fs-6">
                                Rp {{ number_format($data['total_revenue'], 2, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 2. BEBAN OPERASIONAL -->
            <div class="px-4 py-2 bg-light text-muted small fw-bold text-uppercase border-top border-bottom mt-3">
                II. Beban Operasional (Operating Expenses)
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        @forelse($data['expenses'] as $exp)
                            <tr>
                                <td class="ps-4" style="width: 120px;">
                                    <span class="badge bg-light text-dark font-monospace border">{{ $exp['code'] }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $exp['name'] }}</span>
                                </td>
                                <td class="text-end pe-4 font-monospace fw-semibold text-danger" style="width: 200px;">
                                    Rp {{ number_format($exp['amount'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="ps-4 py-3 text-muted">Tidak ada beban operasional pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2" class="ps-4 fw-bold text-dark">TOTAL BEBAN OPERASIONAL</td>
                            <td class="text-end pe-4 font-monospace fw-bold text-danger fs-6">
                                Rp {{ number_format($data['total_expense'], 2, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 3. LABA / RUGI BERSIH -->
            <div class="p-4 bg-light border-top d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">LABA / (RUGI) BERSIH OPERASIONAL</h5>
                <h4 class="font-monospace fw-bold mb-0 {{ $data['net_income'] >= 0 ? 'text-primary' : 'text-danger' }}">
                    Rp {{ number_format($data['net_income'], 2, ',', '.') }}
                </h4>
            </div>
        </div>
    </div>
</div>
@endsection
