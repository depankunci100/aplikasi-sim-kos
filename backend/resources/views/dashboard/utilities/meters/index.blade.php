@extends('layouts.app')

@section('title', 'Daftar Meter Utilitas')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Master Meter Utilitas</h4>
        <p class="text-muted small mb-0">Kelola meteran fisik listrik (kWh) dan air (m³) per kamar atau komunal.</p>
    </div>
    @if(!auth()->user()->isTenant())
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard.utilities.readings.create') }}" class="btn btn-outline-teal btn-sm d-flex align-items-center gap-1 border-teal text-teal">
            <i class="bi bi-pencil-square"></i> Catat Pembacaan
        </a>
        <button type="button" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-1" data-bs-toggle="offcanvas" data-bs-target="#createMeterDrawer" aria-controls="createMeterDrawer">
            <i class="bi bi-plus-circle"></i> Tambah Meter Baru
        </button>
    </div>
    @endif
</div>

<!-- Filter Box -->
<div class="card p-3 mb-4">
    <form action="{{ route('dashboard.utilities.meters.index') }}" method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
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
        <div class="col-md-2">
            <label class="form-label small text-muted fw-semibold">Jenis Utilitas</label>
            <select name="utility_type" class="form-select form-select-sm">
                <option value="">Semua Jenis</option>
                <option value="electricity" {{ ($filters['utility_type'] ?? '') === 'electricity' ? 'selected' : '' }}>Listrik (kWh)</option>
                <option value="water" {{ ($filters['utility_type'] ?? '') === 'water' ? 'selected' : '' }}>Air (m³)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted fw-semibold">Tipe Meter</label>
            <select name="meter_type" class="form-select form-select-sm">
                <option value="">Semua Tipe</option>
                <option value="individual" {{ ($filters['meter_type'] ?? '') === 'individual' ? 'selected' : '' }}>Individual (Kamar)</option>
                <option value="shared" {{ ($filters['meter_type'] ?? '') === 'shared' ? 'selected' : '' }}>Bersama (Komunal)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted fw-semibold">Pencarian</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="No. Meter / Kamar..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary-teal btn-sm w-100">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <a href="{{ route('dashboard.utilities.meters.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset">
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
                    <th>No. Meter</th>
                    <th>Properti</th>
                    <th>Kamar / Penempatan</th>
                    <th>Jenis Utilitas</th>
                    <th>Tipe</th>
                    <th class="text-end">Pembacaan Terakhir</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($meters as $meter)
                <tr>
                    <td>
                        <a href="{{ route('dashboard.utilities.meters.show', $meter->id) }}" class="fw-bold text-teal text-decoration-none">
                            {{ $meter->meter_number }}
                        </a>
                        <div class="small text-muted">Awal: {{ number_format($meter->initial_reading, 2) }} {{ $meter->unit }}</div>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark">{{ $meter->property->name ?? '-' }}</span>
                    </td>
                    <td>
                        @if($meter->room)
                            <span class="badge bg-light text-dark border">Kamar {{ $meter->room->room_number }}</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Fasilitas Bersama</span>
                        @endif
                    </td>
                    <td>
                        @if($meter->utility_type->value === 'electricity')
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Listrik (kWh)
                            </span>
                        @else
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                <i class="bi bi-droplet-fill me-1"></i> Air (m³)
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="small text-muted">{{ $meter->meter_type->label() }}</span>
                    </td>
                    <td class="text-end">
                        <span class="fw-bold fs-6 text-dark">{{ number_format($meter->current_reading, 2) }}</span>
                        <span class="small text-muted">{{ $meter->unit }}</span>
                    </td>
                    <td class="text-center">
                        @if($meter->is_active)
                            <span class="badge bg-success-subtle text-success">Aktif</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('dashboard.utilities.readings.create', ['meter_id' => $meter->id]) }}" class="btn btn-outline-teal" title="Catat Nilai Baru">
                                <i class="bi bi-plus"></i> Catat
                            </a>
                            <a href="{{ route('dashboard.utilities.meters.show', $meter->id) }}" class="btn btn-outline-secondary" title="Detail Riwayat">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if(!auth()->user()->isTenant() && !auth()->user()->isStaff())
                            <a href="{{ route('dashboard.utilities.meters.edit', $meter->id) }}" class="btn btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-speedometer2 fs-1 text-secondary opacity-50 d-block mb-2"></i>
                        Belum ada data meter utilitas yang sesuai dengan kriteria pencarian.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($meters->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $meters->links() }}
    </div>
    @endif
</div>

<!-- Right-Side Drawer: Tambah Meter Utilitas Baru -->
@if(!auth()->user()->isTenant())
<div class="offcanvas offcanvas-end drawer-md" tabindex="-1" id="createMeterDrawer" aria-labelledby="createMeterDrawerLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2" id="createMeterDrawerLabel">
            <i class="bi bi-speedometer2 text-primary-teal"></i> Tambah Meter Baru
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <form action="{{ route('dashboard.utilities.meters.store') }}" method="POST" class="d-flex flex-column h-100">
        @csrf
        <input type="hidden" name="_drawer_id" value="createMeterDrawer">

        <div class="offcanvas-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold small">Properti <span class="text-danger">*</span></label>
                    <select name="property_id" id="drawerMeterPropertySelect" class="form-select form-select-sm @error('property_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Properti --</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ old('property_id') == $property->id ? 'selected' : '' }}>
                                {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small">Kamar (Opsional)</label>
                    <select name="room_id" id="drawerMeterRoomSelect" class="form-select form-select-sm @error('room_id') is-invalid @enderror">
                        <option value="">-- Kosongkan untuk Meter Komunal / Fasilitas Bersama --</option>
                        @foreach($properties as $property)
                            @foreach($property->rooms as $room)
                                <option value="{{ $room->id }}" data-property="{{ $property->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                                    {{ $property->name }} - Kamar {{ $room->room_number }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    <div class="form-text small">Pilih kamar jika meter ditujukan khusus untuk satu kamar.</div>
                    @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Jenis Utilitas <span class="text-danger">*</span></label>
                    <select name="utility_type" id="drawerMeterUtilitySelect" class="form-select form-select-sm @error('utility_type') is-invalid @enderror" required>
                        <option value="electricity" {{ old('utility_type', 'electricity') === 'electricity' ? 'selected' : '' }}>Listrik (kWh)</option>
                        <option value="water" {{ old('utility_type') === 'water' ? 'selected' : '' }}>Air (m³)</option>
                    </select>
                    @error('utility_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold small">Satuan Pembacaan <span class="text-danger">*</span></label>
                    <input type="text" name="unit" id="drawerMeterUnitInput" class="form-control form-control-sm @error('unit') is-invalid @enderror" value="{{ old('unit', 'kWh') }}" required>
                    @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small">Nomor / Seri Fisik Meter <span class="text-danger">*</span></label>
                    <input type="text" name="meter_number" class="form-control form-control-sm @error('meter_number') is-invalid @enderror" placeholder="Contoh: PLN-140229871" value="{{ old('meter_number') }}" required>
                    @error('meter_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Tipe Meter <span class="text-danger">*</span></label>
                    <select name="meter_type" class="form-select form-select-sm @error('meter_type') is-invalid @enderror" required>
                        <option value="individual" {{ old('meter_type', 'individual') === 'individual' ? 'selected' : '' }}>Individual (Kamar)</option>
                        <option value="shared" {{ old('meter_type') === 'shared' ? 'selected' : '' }}>Bersama (Komunal)</option>
                    </select>
                    @error('meter_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Pembacaan Awal <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="initial_reading" class="form-control form-control-sm @error('initial_reading') is-invalid @enderror" value="{{ old('initial_reading', '0.00') }}" required>
                    @error('initial_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Kapasitas Maksimum</label>
                    <input type="number" step="0.01" name="max_reading" class="form-control form-control-sm @error('max_reading') is-invalid @enderror" placeholder="Kosongkan jika standar" value="{{ old('max_reading') }}">
                    @error('max_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Tanggal Pemasangan</label>
                    <input type="date" name="installed_at" class="form-control form-control-sm @error('installed_at') is-invalid @enderror" value="{{ old('installed_at', date('Y-m-d')) }}">
                    @error('installed_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="offcanvas-footer">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-primary-teal btn-sm px-3">
                <i class="bi bi-save me-1"></i> Simpan Meter
            </button>
        </div>
    </form>
</div>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const propSelect = document.getElementById('drawerMeterPropertySelect');
        const roomSelect = document.getElementById('drawerMeterRoomSelect');
        const utilSelect = document.getElementById('drawerMeterUtilitySelect');
        const unitInput = document.getElementById('drawerMeterUnitInput');

        if (propSelect && roomSelect) {
            function filterRooms() {
                const selectedPropId = propSelect.value;
                Array.from(roomSelect.options).forEach(opt => {
                    if (!opt.value) return;
                    opt.style.display = (!selectedPropId || opt.dataset.property === selectedPropId) ? '' : 'none';
                });
            }
            propSelect.addEventListener('change', filterRooms);
            filterRooms();
        }

        if (utilSelect && unitInput) {
            utilSelect.addEventListener('change', function () {
                unitInput.value = this.value === 'electricity' ? 'kWh' : 'm3';
            });
        }
    });
</script>
@endpush
@endsection
