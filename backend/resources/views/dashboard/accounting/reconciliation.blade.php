@extends('layouts.app')

@section('title', 'Rekonsiliasi Subledger terhadap Jurnal — SIM Kos & Guesthouse')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.accounting.journals.index') }}" class="text-decoration-none">Akuntansi</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rekonsiliasi Subledger</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-1">Rekonsiliasi Subledger terhadap Jurnal</h1>
            <p class="text-muted small mb-0">Audit silang antara catatan operasional (Payments, Expenses, Deposit Ledger) dengan buku jurnal akuntansi.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.accounting.journals.index', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-journal-text"></i>
                <span>Jurnal Umum</span>
            </a>
            <a href="{{ route('dashboard.accounting.reports.trial-balance', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-teal d-flex align-items-center gap-2">
                <i class="bi bi-scale"></i>
                <span>Neraca Saldo</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.accounting.reports.reconciliation') }}" class="row g-2 align-items-center">
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
                    <button type="submit" class="btn btn-sm btn-primary-teal w-100"><i class="bi bi-arrow-repeat"></i> Rekonsiliasi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 1. Grid Card Rekonsiliasi 3 Pilar Finansial -->
    <div class="row g-4 mb-4">
        <!-- A. Penerimaan Kas & Bank -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-down-left-circle-fill text-success"></i>
                        <span>Penerimaan Kas & Bank</span>
                    </h6>
                    @if($data['cash_inflow_reconciliation']['is_reconciled'])
                        <span class="badge bg-success-subtle text-success">Cocok (Reconciled)</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger">Ada Selisih</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="text-muted small">Subledger Pembayaran Sah</span>
                        <span class="font-monospace fw-bold text-dark">
                            Rp {{ number_format($data['cash_inflow_reconciliation']['subledger_verified_payments'], 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="text-muted small">Jurnal Debit Kas/Bank</span>
                        <span class="font-monospace fw-bold text-dark">
                            Rp {{ number_format($data['cash_inflow_reconciliation']['journal_cash_debits'], 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="p-2 rounded-2 {{ $data['cash_inflow_reconciliation']['is_reconciled'] ? 'bg-light' : 'bg-danger-subtle' }} d-flex justify-content-between align-items-center">
                        <span class="small fw-bold {{ $data['cash_inflow_reconciliation']['is_reconciled'] ? 'text-muted' : 'text-danger' }}">Selisih Variance:</span>
                        <span class="font-monospace fw-bold {{ $data['cash_inflow_reconciliation']['is_reconciled'] ? 'text-success' : 'text-danger' }}">
                            Rp {{ number_format($data['cash_inflow_reconciliation']['variance'], 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- B. Pengeluaran Kas & Bank -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-up-right-circle-fill text-danger"></i>
                        <span>Pengeluaran Kas & Bank</span>
                    </h6>
                    @if($data['cash_outflow_reconciliation']['is_reconciled'])
                        <span class="badge bg-success-subtle text-success">Cocok (Reconciled)</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger">Ada Selisih</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="text-muted small">Subledger Pencairan Beban</span>
                        <span class="font-monospace fw-bold text-dark">
                            Rp {{ number_format($data['cash_outflow_reconciliation']['subledger_expense_disbursements'], 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="text-muted small">Jurnal Kredit Kas/Bank</span>
                        <span class="font-monospace fw-bold text-dark">
                            Rp {{ number_format($data['cash_outflow_reconciliation']['journal_cash_credits'], 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="p-2 rounded-2 {{ $data['cash_outflow_reconciliation']['is_reconciled'] ? 'bg-light' : 'bg-danger-subtle' }} d-flex justify-content-between align-items-center">
                        <span class="small fw-bold {{ $data['cash_outflow_reconciliation']['is_reconciled'] ? 'text-muted' : 'text-danger' }}">Selisih Variance:</span>
                        <span class="font-monospace fw-bold {{ $data['cash_outflow_reconciliation']['is_reconciled'] ? 'text-success' : 'text-danger' }}">
                            Rp {{ number_format($data['cash_outflow_reconciliation']['variance'], 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- C. Uang Jaminan / Deposit -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock-fill text-warning"></i>
                        <span>Kewajiban Uang Jaminan</span>
                    </h6>
                    @if($data['security_deposit_reconciliation']['is_reconciled'])
                        <span class="badge bg-success-subtle text-success">Cocok (Reconciled)</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger">Ada Selisih</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="text-muted small">Saldo Deposit Subledger</span>
                        <span class="font-monospace fw-bold text-dark">
                            Rp {{ number_format($data['security_deposit_reconciliation']['subledger_current_balance'], 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <span class="text-muted small">Saldo Kewajiban Akun 2101</span>
                        <span class="font-monospace fw-bold text-dark">
                            Rp {{ number_format($data['security_deposit_reconciliation']['journal_liability_balance'], 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="p-2 rounded-2 {{ $data['security_deposit_reconciliation']['is_reconciled'] ? 'bg-light' : 'bg-danger-subtle' }} d-flex justify-content-between align-items-center">
                        <span class="small fw-bold {{ $data['security_deposit_reconciliation']['is_reconciled'] ? 'text-muted' : 'text-danger' }}">Selisih Variance:</span>
                        <span class="font-monospace fw-bold {{ $data['security_deposit_reconciliation']['is_reconciled'] ? 'text-success' : 'text-danger' }}">
                            Rp {{ number_format($data['security_deposit_reconciliation']['variance'], 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Audit Trail & Deteksi Transaksi Tanpa Jurnal -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-diamond text-teal"></i>
                <span>Status Kelengkapan Jurnal (Audit Exceptions)</span>
            </h6>
        </div>
        <div class="card-body">
            @if($data['unposted_exceptions_count'] > 0)
                <div class="alert alert-warning border-0 d-flex align-items-center justify-content-between mb-0">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Ditemukan {{ $data['unposted_exceptions_count'] }} Transaksi Pembayaran Tanpa Jurnal!</h6>
                            <p class="small text-muted mb-0">Terdapat pembayaran historis sebelum Phase 8 yang belum memiliki jurnal pembukuan.</p>
                        </div>
                    </div>
                    <div class="small text-muted">
                        Jalankan: <code class="bg-light p-1 rounded font-monospace">php artisan finance:backfill-journals</code>
                    </div>
                </div>
            @else
                <div class="alert alert-success border-0 d-flex align-items-center gap-3 mb-0">
                    <i class="bi bi-check-circle-fill fs-3 text-success"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Seluruh Transaksi Terpetakan Sempurna</h6>
                        <p class="small text-muted mb-0">Tidak ditemukan transaksi operasional yang tertinggal atau gagal diposting ke jurnal buku besar.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
