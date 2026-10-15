@extends('layouts.app')

@section('title', 'Keluhan Penyewa (Complaints) - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-chat-left-dots text-primary-teal me-2"></i>Keluhan & Pengaduan Penyewa</h3>
        <p class="text-muted mb-0">Pantau tiket keluhan penyewa, berikan respon cepat, dan tindak lanjuti penyelesaian.</p>
    </div>
    <a href="{{ route('dashboard.complaints.create') }}" class="btn btn-primary-teal d-flex align-items-center gap-2">
        <i class="bi bi-plus-circle"></i>
        <span>Kirim Keluhan Baru</span>
    </a>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light p-3 text-primary-teal">
                    <i class="bi bi-chat-left-dots fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Keluhan</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-secondary bg-opacity-10 p-3 text-secondary">
                    <i class="bi bi-inbox fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Belum Ditinjau</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['submitted'] ?? 0 }}</div>
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
                    <div class="text-muted small fw-semibold">Sedang Ditangani</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['in_progress'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                    <i class="bi bi-check-circle fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Terselesaikan</div>
                    <div class="fs-4 fw-bold text-success">{{ $counts['resolved'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard.complaints.index') }}" class="row g-3 align-items-end">
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
                <select name="category" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    @foreach(\App\Enums\ComplaintCategory::cases() as $cat)
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
                    @foreach(\App\Enums\ComplaintPriority::cases() as $pri)
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
                    @foreach(\App\Enums\ComplaintStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ ($filters['status'] ?? '') == $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Tanggal</label>
                <input type="date" name="date" class="form-control form-control-sm" value="{{ $filters['date'] ?? '' }}">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary-teal btn-sm w-100" title="Filter"><i class="bi bi-filter"></i></button>
                <a href="{{ route('dashboard.complaints.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
            <div class="col-12 mt-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan No. Tiket, Nama Pelapor, Judul Keluhan, atau Nomor Kamar..." value="{{ $filters['search'] ?? '' }}">
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Complaints Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">No. Tiket</th>
                        <th>Properti & Kamar</th>
                        <th>Pelapor</th>
                        <th>Judul & Kategori</th>
                        <th>Prioritas</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($complaints as $c)
                    <tr>
                        <td class="ps-3 fw-bold text-dark font-monospace">
                            {{ $c->ticket_number }}
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $c->property->name }}</div>
                            <div class="small text-muted">
                                @if($c->room)
                                    <i class="bi bi-door-closed me-1"></i>Kamar {{ $c->room->room_number }}
                                @else
                                    <i class="bi bi-geo-alt me-1"></i>Umum / Fasilitas
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $c->user->name }}</div>
                            <div class="small text-muted">{{ $c->created_at->format('d M Y, H:i') }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $c->title }}</div>
                            <span class="badge bg-light text-dark border">{{ $c->category->label() }}</span>
                            @if($c->maintenance_request_id)
                                <span class="badge bg-info text-dark ms-1" title="Tertaut ke tiket perbaikan"><i class="bi bi-tools"></i> Ada Maintenance</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $priBadge = match($c->priority->value) {
                                    'urgent' => 'bg-danger text-white',
                                    'high' => 'bg-warning text-dark',
                                    'normal' => 'bg-info text-dark',
                                    default => 'bg-secondary text-white',
                                };
                            @endphp
                            <span class="badge {{ $priBadge }}">{{ $c->priority->label() }}</span>
                        </td>
                        <td>
                            @if($c->assignee)
                                <div class="fw-semibold text-dark">{{ $c->assignee->name }}</div>
                            @else
                                <span class="badge bg-light text-muted border">Belum Ditugaskan</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $stBadge = match($c->status->value) {
                                    'submitted' => 'bg-secondary text-white',
                                    'acknowledged' => 'bg-info text-dark',
                                    'in_progress' => 'bg-warning text-dark',
                                    'resolved' => 'bg-success text-white',
                                    'closed' => 'bg-dark text-white',
                                    'reopened' => 'bg-danger text-white',
                                    'rejected' => 'bg-danger text-white bg-opacity-75',
                                    default => 'bg-light text-dark',
                                };
                            @endphp
                            <span class="badge {{ $stBadge }}">{{ $c->status->label() }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('dashboard.complaints.show', $c->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-1"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-chat-left-dots fs-1 d-block mb-2 text-secondary"></i>
                            Tidak ada keluhan penyewa yang sesuai filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($complaints->hasPages())
    <div class="card-footer bg-white border-top py-3">
        {{ $complaints->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
