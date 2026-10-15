@extends('layouts.app')

@section('title', 'Manajemen Pengeluaran Operasional - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-wallet2 text-primary-teal me-2"></i>Pengeluaran Operasional</h3>
        <p class="text-muted mb-0">Kelola pengajuan biaya cabang, persetujuan manajer, dan pencatatan realisasi pembayaran.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.expenses.categories') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-tags"></i> Kategori
        </a>
        <a href="{{ route('dashboard.expenses.create') }}" class="btn btn-primary-teal d-flex align-items-center gap-1">
            <i class="bi bi-plus-circle"></i> Catat Pengeluaran
        </a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light p-3 text-primary-teal">
                    <i class="bi bi-receipt fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Pengajuan</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Menunggu Approval</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['submitted'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                    <i class="bi bi-patch-check fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Disetujui</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['approved'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Lunas Terbayar</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['paid'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard.expenses.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="No. pengeluaran / judul / vendor..." value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Properti</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">Semua Properti</option>
                    @foreach($properties as $prop)
                        <option value="{{ $prop->id }}" {{ ($filters['property_id'] ?? '') == $prop->id ? 'selected' : '' }}>
                            {{ $prop->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Kategori</label>
                <select name="expense_category_id" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ ($filters['expense_category_id'] ?? '') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Persetujuan</label>
                <select name="approval_status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach(\App\Enums\ExpenseApprovalStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ ($filters['approval_status'] ?? '') == $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Pembayaran</label>
                <select name="payment_status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach(\App\Enums\ExpensePaymentStatus::cases() as $pst)
                        <option value="{{ $pst->value }}" {{ ($filters['payment_status'] ?? '') == $pst->value ? 'selected' : '' }}>
                            {{ $pst->label() }}
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
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary-teal btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button>
                <a href="{{ route('dashboard.expenses.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">No. Pengeluaran</th>
                        <th>Properti & Tanggal</th>
                        <th>Kategori & Judul</th>
                        <th>Vendor / Penerima</th>
                        <th>Nominal</th>
                        <th>Persetujuan</th>
                        <th>Pembayaran</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $exp)
                    <tr>
                        <td class="ps-3 fw-bold text-dark font-monospace">
                            {{ $exp->expense_number }}
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $exp->property->name ?? '-' }}</div>
                            <small class="text-muted"><i class="bi bi-calendar me-1"></i>{{ $exp->expense_date?->format('d M Y') }}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border mb-1">{{ $exp->expenseCategory?->name ?? ucfirst($exp->category) }}</span>
                            <div class="fw-semibold text-dark">{{ $exp->title }}</div>
                        </td>
                        <td>
                            {{ $exp->vendor_name ?: '-' }}
                        </td>
                        <td>
                            <div class="fw-bold text-dark">Rp {{ number_format($exp->amount, 0, ',', '.') }}</div>
                            @if($exp->paid_amount > 0 && $exp->paid_amount < $exp->amount)
                                <small class="text-muted">Dibayar: Rp {{ number_format($exp->paid_amount, 0, ',', '.') }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $exp->approval_status->badgeClass() }}">
                                {{ $exp->approval_status->label() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $exp->payment_status->badgeClass() }}">
                                {{ $exp->payment_status->label() }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('dashboard.expenses.show', $exp->id) }}" class="btn btn-sm btn-outline-primary-teal">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet2 fs-2 d-block mb-2"></i>
                            <span>Belum ada data pengeluaran operasional.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $expenses->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
