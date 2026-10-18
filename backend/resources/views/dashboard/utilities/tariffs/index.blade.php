@extends('layouts.app')

@section('title', 'Pengaturan Tarif Utilitas')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Konfigurasi Tarif Utilitas</h4>
        <p class="text-muted small mb-0">Atur harga per satuan (kWh atau m³) per properti dengan rentang masa berlaku.</p>
    </div>
    @if(!auth()->user()->isTenant() && !auth()->user()->isStaff())
    <button type="button" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-1" data-bs-toggle="offcanvas" data-bs-target="#createTariffDrawer" aria-controls="createTariffDrawer">
        <i class="bi bi-plus-circle"></i> Tambah Tarif Baru
    </button>
    @endif
</div>

<!-- Info Banner -->
<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-shield-check fs-5"></i>
    <div class="small">
        <strong>Aturan Tarif Aman:</strong> Perubahan tarif baru <em>tidak akan mengubah</em> pemakaian masa lalu atau tagihan yang sudah diterbitkan. Sistem selalu merekam snapshot tarif secara permanen pada saat pembacaan dan invoice dibuat.
    </div>
</div>

<!-- Filter Bar -->
<div class="card p-3 mb-4">
    <form action="{{ route('dashboard.utilities.tariffs.index') }}" method="GET" class="row g-2 align-items-end">
        <div class="col-md-5">
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
        <div class="col-md-4">
            <label class="form-label small text-muted fw-semibold">Jenis Utilitas</label>
            <select name="utility_type" class="form-select form-select-sm">
                <option value="">Semua Jenis</option>
                <option value="electricity" {{ ($filters['utility_type'] ?? '') === 'electricity' ? 'selected' : '' }}>Listrik (kWh)</option>
                <option value="water" {{ ($filters['utility_type'] ?? '') === 'water' ? 'selected' : '' }}>Air (m³)</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <button type="submit" class="btn btn-primary-teal btn-sm w-100">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <a href="{{ route('dashboard.utilities.tariffs.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset">
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
                    <th>Properti</th>
                    <th>Jenis Utilitas</th>
                    <th class="text-end">Tarif per Satuan</th>
                    <th>Satuan</th>
                    <th>Masa Berlaku</th>
                    <th class="text-center">Status</th>
                    <th>Dikonfigurasi Oleh</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tariffs as $tariff)
                <tr>
                    <td>
                        <span class="fw-bold text-dark">{{ $tariff->property->name ?? '-' }}</span>
                    </td>
                    <td>
                        @if($tariff->utility_type->value === 'electricity')
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Listrik
                            </span>
                        @else
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                <i class="bi bi-droplet-fill me-1"></i> Air
                            </span>
                        @endif
                    </td>
                    <td class="text-end">
                        <span class="fw-bold fs-6 text-dark">Rp {{ number_format($tariff->rate_per_unit, 0, ',', '.') }}</span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $tariff->unit }}</span>
                    </td>
                    <td>
                        <div class="small">
                            <strong>{{ $tariff->effective_from->format('d/m/Y') }}</strong>
                            <span class="text-muted">s/d</span>
                            <strong>{{ $tariff->effective_to ? $tariff->effective_to->format('d/m/Y') : 'Sekarang' }}</strong>
                        </div>
                    </td>
                    <td class="text-center">
                        @if($tariff->is_active)
                            <span class="badge bg-success-subtle text-success">Aktif</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td>
                        <div class="small text-muted">{{ $tariff->creator->name ?? '-' }}</div>
                        <div class="small text-muted" style="font-size: 0.75rem;">{{ $tariff->created_at->format('d/m/Y H:i') }}</div>
                    </td>
                    <td>
                        <span class="small text-muted">{{ $tariff->notes ?? '-' }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-tag fs-2 text-secondary opacity-50 d-block mb-2"></i>
                        Belum ada konfigurasi tarif utilitas yang terdaftar.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tariffs->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $tariffs->links() }}
    </div>
    @endif
</div>

<!-- Right-Side Drawer: Konfigurasi Tarif Baru -->
@if(!auth()->user()->isTenant() && !auth()->user()->isStaff())
<div class="offcanvas offcanvas-end drawer-md" tabindex="-1" id="createTariffDrawer" aria-labelledby="createTariffDrawerLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2" id="createTariffDrawerLabel">
            <i class="bi bi-tags text-primary-teal"></i> Konfigurasi Tarif Baru
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <form action="{{ route('dashboard.utilities.tariffs.store') }}" method="POST" class="d-flex flex-column h-100">
        @csrf
        <input type="hidden" name="_drawer_id" value="createTariffDrawer">

        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Properti <span class="text-danger">*</span></label>
                <select name="property_id" class="form-select form-select-sm @error('property_id') is-invalid @enderror" required>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" {{ old('property_id') == $property->id ? 'selected' : '' }}>{{ $property->name }}</option>
                    @endforeach
                </select>
                @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label class="form-label small fw-semibold">Jenis Utilitas <span class="text-danger">*</span></label>
                    <select name="utility_type" id="drawerTariffUtility" class="form-select form-select-sm @error('utility_type') is-invalid @enderror" required>
                        <option value="electricity" {{ old('utility_type') === 'electricity' ? 'selected' : '' }}>Listrik (kWh)</option>
                        <option value="water" {{ old('utility_type') === 'water' ? 'selected' : '' }}>Air (m³)</option>
                    </select>
                    @error('utility_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label small fw-semibold">Satuan <span class="text-danger">*</span></label>
                    <input type="text" name="unit" id="drawerTariffUnit" class="form-control form-control-sm @error('unit') is-invalid @enderror" value="{{ old('unit', 'kWh') }}" required>
                    @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Tarif per Satuan (IDR) <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Rp</span>
                    <input type="number" step="0.01" name="rate_per_unit" class="form-control @error('rate_per_unit') is-invalid @enderror"
                           placeholder="Contoh: 1500" value="{{ old('rate_per_unit') }}" required>
                </div>
                <div class="form-text small">Gunakan angka nominal bulat atau desimal (misal 1500 atau 1444.70).</div>
                @error('rate_per_unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row g-2 mb-3">
                <div class="col-sm-6">
                    <label class="form-label small fw-semibold">Mulai Berlaku <span class="text-danger">*</span></label>
                    <input type="date" name="effective_from" class="form-control form-control-sm @error('effective_from') is-invalid @enderror"
                           value="{{ old('effective_from', date('Y-m-d')) }}" required>
                    @error('effective_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label small fw-semibold">Berlaku Sampai (Opsional)</label>
                    <input type="date" name="effective_to" class="form-control form-control-sm @error('effective_to') is-invalid @enderror" value="{{ old('effective_to') }}">
                    <div class="form-text small">Kosongkan jika berlaku tanpa batas.</div>
                    @error('effective_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Catatan / Dasar SK Tarif</label>
                <textarea name="notes" rows="3" class="form-control form-control-sm @error('notes') is-invalid @enderror"
                          placeholder="Contoh: Penyesuaian tarif listrik PLN triwulan IV">{{ old('notes') }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="offcanvas-footer">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-primary-teal btn-sm px-3">
                <i class="bi bi-save me-1"></i> Simpan Tarif
            </button>
        </div>
    </form>
</div>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const utilSelect = document.getElementById('drawerTariffUtility');
        const unitInput = document.getElementById('drawerTariffUnit');
        if (utilSelect && unitInput) {
            utilSelect.addEventListener('change', function () {
                unitInput.value = this.value === 'electricity' ? 'kWh' : 'm3';
            });
        }
    });
</script>
@endpush
@endsection
