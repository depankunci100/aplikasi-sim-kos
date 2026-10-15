@extends('layouts.app')

@section('title', 'Pencatatan & Review Meter Utilitas')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Pencatatan & Review Konsumsi Meter</h4>
        <p class="text-muted small mb-0">Verifikasi pembacaan meter, review foto bukti fisik, dan terbitkan tagihan.</p>
    </div>
    @if(!auth()->user()->isTenant())
    <a href="{{ route('dashboard.utilities.readings.create') }}" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-pencil-square"></i> Catat Pembacaan Baru
    </a>
    @endif
</div>

<!-- Review Status Tabs -->
<ul class="nav nav-pills mb-3 gap-2">
    <li class="nav-item">
        <a class="nav-link {{ empty($filters['status']) && !isset($filters['is_billed']) ? 'active bg-primary-teal text-white' : 'bg-white border text-dark' }}" href="{{ route('dashboard.utilities.readings.index') }}">
            Semua
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($filters['status'] ?? '') === 'pending_review' ? 'active bg-warning text-dark' : 'bg-white border text-dark' }}" href="{{ route('dashboard.utilities.readings.index', ['status' => 'pending_review']) }}">
            Menunggu Review
            @if($pendingCount > 0)
                <span class="badge bg-danger rounded-pill ms-1">{{ $pendingCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($filters['status'] ?? '') === 'approved' && !isset($filters['is_billed']) ? 'active bg-success text-white' : 'bg-white border text-dark' }}" href="{{ route('dashboard.utilities.readings.index', ['status' => 'approved']) }}">
            Disetujui
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($filters['status'] ?? '') === 'rejected' ? 'active bg-danger text-white' : 'bg-white border text-dark' }}" href="{{ route('dashboard.utilities.readings.index', ['status' => 'rejected']) }}">
            Ditolak
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($filters['is_billed'] ?? '') === '1' ? 'active bg-info text-white' : 'bg-white border text-dark' }}" href="{{ route('dashboard.utilities.readings.index', ['is_billed' => '1']) }}">
            Sudah Ditagihkan (Billed)
        </a>
    </li>
</ul>

<!-- Filter Bar -->
<div class="card p-3 mb-4">
    <form action="{{ route('dashboard.utilities.readings.index') }}" method="GET" class="row g-2 align-items-end">
        @if(!empty($filters['status']))
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
        @endif
        @if(isset($filters['is_billed']))
            <input type="hidden" name="is_billed" value="{{ $filters['is_billed'] }}">
        @endif
        <div class="col-md-4">
            <label class="form-label small text-muted fw-semibold">Properti</label>
            <select name="property_id" class="form-select form-select-sm">
                <option value="">Semua Properti</option>
                @foreach($properties as $prop)
                    <option value="{{ $prop->id }}" {{ ($filters['property_id'] ?? '') == $prop->id ? 'selected' : '' }}>
                        {{ $prop->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted fw-semibold">Jenis Utilitas</label>
            <select name="utility_type" class="form-select form-select-sm">
                <option value="">Semua Jenis</option>
                <option value="electricity" {{ ($filters['utility_type'] ?? '') === 'electricity' ? 'selected' : '' }}>Listrik (kWh)</option>
                <option value="water" {{ ($filters['utility_type'] ?? '') === 'water' ? 'selected' : '' }}>Air (m³)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted fw-semibold">ID Meter</label>
            <input type="number" name="meter_id" class="form-control form-control-sm" placeholder="Contoh: 1" value="{{ $filters['meter_id'] ?? '' }}">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary-teal btn-sm w-100">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <a href="{{ route('dashboard.utilities.readings.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th>Tgl Catat</th>
                    <th>Properti & Kamar</th>
                    <th>No. Meter</th>
                    <th>Jenis</th>
                    <th class="text-end">Lalu &rarr; Kini</th>
                    <th class="text-end">Pemakaian</th>
                    <th class="text-end">Estimasi Biaya</th>
                    <th class="text-center">Bukti</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($readings as $reading)
                <tr>
                    <td>
                        <a href="{{ route('dashboard.utilities.readings.show', $reading->id) }}" class="fw-bold text-teal text-decoration-none">
                            {{ $reading->reading_date->format('d/m/Y') }}
                        </a>
                        <div class="small text-muted">Oleh: {{ $reading->recorder->name ?? '-' }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $reading->property->name ?? '-' }}</div>
                        <div class="small text-muted">
                            @if($reading->room)
                                Kamar {{ $reading->room->room_number }}
                                @if($reading->booking && $reading->booking->user)
                                    &bull; {{ $reading->booking->user->name }}
                                @endif
                            @else
                                <span class="badge bg-light text-dark border">Komunal</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <code>{{ $reading->meter->meter_number ?? '-' }}</code>
                    </td>
                    <td>
                        @if($reading->utility_type->value === 'electricity')
                            <span class="badge bg-warning-subtle text-warning-emphasis">Listrik</span>
                        @else
                            <span class="badge bg-info-subtle text-info-emphasis">Air</span>
                        @endif
                    </td>
                    <td class="text-end small">
                        <span class="text-muted">{{ number_format($reading->previous_meter, 2) }}</span>
                        &rarr;
                        <strong class="text-dark">{{ number_format($reading->current_meter, 2) }}</strong>
                    </td>
                    <td class="text-end">
                        <span class="badge bg-teal-subtle text-teal fw-semibold">
                            {{ number_format($reading->usage, 2) }} {{ $reading->meter->unit ?? '' }}
                        </span>
                    </td>
                    <td class="text-end fw-semibold">
                        Rp {{ number_format($reading->total_cost, 0, ',', '.') }}
                        <div class="small text-muted">@ Rp {{ number_format($reading->rate_per_unit, 0, ',', '.') }}</div>
                    </td>
                    <td class="text-center">
                        @if($reading->proof_photo)
                            <a href="{{ route('dashboard.utilities.readings.proof', $reading->id) }}" target="_blank" class="badge bg-light text-primary border" title="Lihat Foto Bukti">
                                <i class="bi bi-camera"></i> Foto
                            </a>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($reading->status->value === 'approved')
                            <span class="badge bg-success-subtle text-success">Disetujui</span>
                        @elseif($reading->status->value === 'pending_review')
                            <span class="badge bg-warning-subtle text-warning">Review</span>
                        @elseif($reading->status->value === 'rejected')
                            <span class="badge bg-danger-subtle text-danger" title="{{ $reading->rejection_reason }}">Ditolak</span>
                        @else
                            <span class="badge bg-light text-dark border">Draf</span>
                        @endif

                        @if($reading->is_billed)
                            <div class="mt-1">
                                <a href="{{ route('dashboard.invoices.show', $reading->invoice_id) }}" class="badge bg-primary text-white text-decoration-none">
                                    <i class="bi bi-receipt"></i> Billed
                                </a>
                            </div>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('dashboard.utilities.readings.show', $reading->id) }}" class="btn btn-outline-secondary" title="Detail & Tindakan">
                                <i class="bi bi-eye"></i> Detail
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x fs-2 text-secondary opacity-50 d-block mb-2"></i>
                        Tidak ada data pembacaan meter yang sesuai filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($readings->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $readings->links() }}
    </div>
    @endif
</div>
@endsection
