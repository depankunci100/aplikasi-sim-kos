@extends('layouts.app')

@section('title', 'Manajemen Inspeksi Kamar')

@section('content')
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1">Inspeksi & Checklist Kamar</h3>
        <p class="text-muted mb-0">Pemeriksaan fisik sebelum check-in, berkala, saat check-out, serta verifikasi bukti foto aset.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <a href="{{ route('dashboard.inspections.create') }}" class="btn btn-teal text-white shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Jadwalkan Inspeksi Baru
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('dashboard.inspections.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari kode / no kamar / penyewa..." value="{{ request('q') }}">
            </div>
            @if(count($properties) > 1)
            <div class="col-md-3">
                <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Semua Properti --</option>
                    @foreach($properties as $prop)
                        <option value="{{ $prop->id }}" {{ $propertyFilter == $prop->id ? 'selected' : '' }}>{{ $prop->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">-- Semua Tipe --</option>
                    <option value="check_in" {{ $typeFilter == 'check_in' ? 'selected' : '' }}>Check-In (Masuk)</option>
                    <option value="periodic" {{ $typeFilter == 'periodic' ? 'selected' : '' }}>Berkala (Rutin)</option>
                    <option value="check_out" {{ $typeFilter == 'check_out' ? 'selected' : '' }}>Check-Out (Keluar)</option>
                    <option value="maintenance" {{ $typeFilter == 'maintenance' ? 'selected' : '' }}>Pemeliharaan</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">-- Semua Status --</option>
                    <option value="scheduled" {{ $statusFilter == 'scheduled' ? 'selected' : '' }}>Terjadwal</option>
                    <option value="in_progress" {{ $statusFilter == 'in_progress' ? 'selected' : '' }}>Sedang Berlangsung</option>
                    <option value="completed" {{ $statusFilter == 'completed' ? 'selected' : '' }}>Selesai Petugas</option>
                    <option value="approved_by_tenant" {{ $statusFilter == 'approved_by_tenant' ? 'selected' : '' }}>Disetujui Penyewa</option>
                    <option value="objected_by_tenant" {{ $statusFilter == 'objected_by_tenant' ? 'selected' : '' }}>Keberatan Diajukan</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Inspections Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-list-check text-primary-teal me-2"></i>Daftar Laporan & Jadwal Inspeksi
        </h6>
        <span class="badge bg-light text-muted border">{{ $inspections->total() }} Data Total</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>Kode / Tipe</th>
                    <th>Kamar & Cabang</th>
                    <th>Penyewa Terkait</th>
                    <th>Petugas Pemeriksa</th>
                    <th>Jadwal / Tanggal</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inspections as $insp)
                <tr>
                    <td>
                        <span class="fw-bold text-dark">{{ $insp->inspection_code }}</span>
                        <br>
                        <span class="badge bg-light text-dark border">{{ $insp->type->label() }}</span>
                    </td>
                    <td>
                        <span class="fw-bold text-primary-teal">Kamar {{ $insp->room->room_number }}</span>
                        <div class="small text-muted">{{ $insp->property->name }}</div>
                    </td>
                    <td>
                        @if($insp->tenant)
                            <span class="fw-semibold">{{ $insp->tenant->name }}</span>
                            <div class="small text-muted">{{ $insp->tenant->phone ?? '-' }}</div>
                        @else
                            <span class="text-muted italic small">Kamar Kosong (Vacant)</span>
                        @endif
                    </td>
                    <td>
                        <span class="small">{{ $insp->inspector->name ?? 'Belum Ditugaskan' }}</span>
                    </td>
                    <td>
                        <div>{{ $insp->scheduled_date->format('d M Y') }}</div>
                        @if($insp->completed_at)
                            <div class="small text-success"><i class="bi bi-check2"></i> {{ $insp->completed_at->format('H:i') }} WIB</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $insp->status->badgeClass() }}">
                            {{ $insp->status->label() }}
                        </span>
                        @if($insp->overall_condition)
                            <div class="small text-muted mt-1">Kondisi: <strong>{{ ucfirst($insp->overall_condition) }}</strong></div>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('dashboard.inspections.show', $insp->id) }}" class="btn btn-sm btn-outline-teal">
                            <i class="bi bi-eye"></i> Detail / Periksa
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="bi bi-clipboard-x fs-1 text-muted d-block mb-2"></i>
                        <span class="text-muted">Belum ada data inspeksi kamar yang sesuai filter.</span>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($inspections->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $inspections->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>
@endsection
