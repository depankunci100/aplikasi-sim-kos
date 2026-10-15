@extends('layouts.app')

@section('title', 'Manajemen Perbaikan (Maintenance) - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-tools text-primary-teal me-2"></i>Tiket Perbaikan & Maintenance</h3>
        <p class="text-muted mb-0">Kelola dan pantau pengerjaan perbaikan fasilitas, kerusakan kamar, dan area bersama.</p>
    </div>
    <a href="{{ route('dashboard.maintenance.create') }}" class="btn btn-primary-teal d-flex align-items-center gap-2">
        <i class="bi bi-plus-circle"></i>
        <span>Ajukan Perbaikan</span>
    </a>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light p-3 text-primary-teal">
                    <i class="bi bi-tools fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Tiket</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                    <i class="bi bi-gear-wide-connected fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Dikerjakan</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['in_progress'] ?? 0 }}</div>
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
                    <div class="text-muted small fw-semibold">Verifikasi</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['pending_verification'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                    <i class="bi bi-alarm fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Terlambat (SLA)</div>
                    <div class="fs-4 fw-bold text-danger">{{ $counts['overdue'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard.maintenance.index') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
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
                <select name="category" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach(\App\Enums\MaintenanceCategory::cases() as $cat)
                        <option value="{{ $cat->value }}" {{ ($filters['category'] ?? '') == $cat->value ? 'selected' : '' }}>
                            {{ $cat->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Prioritas</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="">Semua Prioritas</option>
                    @foreach(\App\Enums\MaintenancePriority::cases() as $pri)
                        <option value="{{ $pri->value }}" {{ ($filters['priority'] ?? '') == $pri->value ? 'selected' : '' }}>
                            {{ $pri->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach(\App\Enums\MaintenanceStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ ($filters['status'] ?? '') == $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Tanggal Jadwal</label>
                <input type="date" name="scheduled_date" class="form-control form-control-sm" value="{{ $filters['scheduled_date'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-semibold text-muted">Overdue</label>
                <select name="is_overdue" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="1" {{ ($filters['is_overdue'] ?? '') === '1' ? 'selected' : '' }}>Ya</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary-teal btn-sm w-100" title="Filter"><i class="bi bi-filter"></i></button>
                <a href="{{ route('dashboard.maintenance.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
            <div class="col-12 mt-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan No. Tiket, Judul Kerusakan, atau Nomor Kamar..." value="{{ $filters['search'] ?? '' }}">
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Maintenance Requests Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">No. Tiket</th>
                        <th>Properti & Lokasi</th>
                        <th>Kerusakan</th>
                        <th>Prioritas & SLA</th>
                        <th>Teknisi</th>
                        <th>Status</th>
                        <th>Biaya Total</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td class="ps-3 fw-bold text-dark font-monospace">
                            {{ $req->ticket_number }}
                            @if($req->is_overdue)
                                <span class="badge bg-danger ms-1" title="Melewati batas SLA"><i class="bi bi-clock-history"></i> Terlambat</span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $req->property->name }}</div>
                            <div class="small text-muted">
                                @if($req->room)
                                    <i class="bi bi-door-closed me-1"></i>Kamar {{ $req->room->room_number }}
                                @else
                                    <i class="bi bi-geo-alt me-1"></i>Area Bersama
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $req->title }}</div>
                            <span class="badge bg-light text-dark border">{{ $req->category->label() }}</span>
                        </td>
                        <td>
                            @php
                                $priBadge = match($req->priority->value) {
                                    'urgent' => 'bg-danger text-white',
                                    'high' => 'bg-warning text-dark',
                                    'normal' => 'bg-info text-dark',
                                    default => 'bg-secondary text-white',
                                };
                            @endphp
                            <span class="badge {{ $priBadge }}">{{ $req->priority->label() }}</span>
                            @if($req->sla_due_at)
                                <div class="small text-muted mt-1" title="Target SLA">
                                    <i class="bi bi-hourglass-split me-1"></i>{{ $req->sla_due_at->format('d M H:i') }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($req->assignee)
                                <div class="fw-semibold text-dark">{{ $req->assignee->name }}</div>
                                <div class="small text-muted"><i class="bi bi-person-badge me-1"></i>Teknisi</div>
                            @else
                                <span class="badge bg-light text-muted border">Belum Ditugaskan</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $statusBadge = match($req->status->value) {
                                    'reported' => 'bg-secondary text-white',
                                    'triaged' => 'bg-info text-dark',
                                    'assigned' => 'bg-primary text-white',
                                    'in_progress' => 'bg-warning text-dark',
                                    'pending_verification' => 'bg-purple text-white bg-opacity-75',
                                    'completed' => 'bg-success text-white',
                                    'cancelled' => 'bg-dark text-white',
                                    'rejected' => 'bg-danger text-white',
                                    default => 'bg-light text-dark',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $req->status->label() }}</span>
                        </td>
                        <td class="fw-semibold">
                            Rp {{ number_format((float) $req->total_cost, 0, ',', '.') }}
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('dashboard.maintenance.show', $req->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-tools fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada tiket perbaikan yang sesuai filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($requests->hasPages())
    <div class="card-footer bg-white border-top py-3">
        {{ $requests->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
