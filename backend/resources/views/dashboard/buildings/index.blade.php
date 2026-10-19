@extends('layouts.app')

@section('title', 'Master Gedung - SIM Kos & Guesthouse')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Master Gedung & Sayap Hunian</h1>
            <p class="text-muted mb-0 small">Kelola blok, paviliun, tower, dan gedung properti kos untuk pengelompokan unit kamar.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.rooms.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-door-open fs-6"></i>
                <span class="fw-semibold">Master Kamar</span>
            </a>
            <button type="button" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-2 shadow-sm" onclick="openCreateBuildingDrawer()">
                <i class="bi bi-plus-circle fs-6"></i>
                <span class="fw-semibold">Tambah Gedung</span>
            </button>
        </div>
    </div>

    <!-- Alert Feedback -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Total Gedung</div>
                        <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($summary['total_buildings']) }}</div>
                    </div>
                    <div class="rounded-3 bg-teal-subtle text-teal p-3">
                        <i class="bi bi-building fs-4 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Gedung Aktif</div>
                        <div class="fs-4 fw-bold text-success mt-1">{{ number_format($summary['active_buildings']) }}</div>
                    </div>
                    <div class="rounded-3 bg-success-subtle text-success p-3">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-medium">Kamar Terhubung</div>
                        <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($summary['total_rooms']) }}</div>
                    </div>
                    <div class="rounded-3 bg-secondary-subtle text-secondary p-3">
                        <i class="bi bi-door-closed fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.buildings.index') }}" class="row g-2 align-items-center">
                @if($properties->count() > 1)
                <div class="col-12 col-md-4">
                    <select name="property_id" class="form-select form-select-sm">
                        <option value="">-- Semua Properti --</option>
                        @foreach($properties as $prop)
                        <option value="{{ $prop->id }}" {{ request('property_id') == $prop->id ? 'selected' : '' }}>
                            {{ $prop->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-6 col-md-3">
                    <select name="is_active" class="form-select form-select-sm">
                        <option value="">-- Status Aktifitas --</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama atau kode..." value="{{ request('search') }}">
                </div>
                <div class="col-12 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary-teal w-100">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('dashboard.buildings.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Buildings -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-3" style="width: 25%;">Gedung / Blok</th>
                        <th style="width: 20%;">Properti</th>
                        <th style="width: 15%;">Jumlah Lantai</th>
                        <th style="width: 15%;">Unit Kamar</th>
                        <th style="width: 10%;">Status</th>
                        <th class="text-end pe-3" style="width: 15%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($buildings as $building)
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-dark">{{ $building->name }}</div>
                            <div class="small text-muted">
                                <span class="badge bg-secondary-subtle text-secondary me-1">{{ $building->code }}</span>
                                @if($building->address_notes)
                                <span>{{ $building->address_notes }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="small fw-semibold text-dark">{{ $building->property->name ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-layers me-1 text-muted"></i>{{ $building->total_floors }} Lantai
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('dashboard.rooms.index', ['property_id' => $building->property_id, 'building_id' => $building->id]) }}" class="text-decoration-none">
                                <span class="badge bg-info-subtle text-info fw-semibold">
                                    <i class="bi bi-door-closed me-1"></i>{{ $building->rooms_count ?? $building->rooms()->count() }} Kamar
                                </span>
                            </a>
                        </td>
                        <td>
                            @if($building->is_active)
                            <span class="badge bg-success-subtle text-success">Aktif</span>
                            @else
                            <span class="badge bg-danger-subtle text-danger">Non-Aktif</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" title="Edit Gedung"
                                    onclick="openEditBuildingDrawer({{ json_encode($building) }})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('dashboard.buildings.toggle-active', $building->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary" title="{{ $building->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <i class="bi {{ $building->is_active ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' }}"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-outline-danger" title="Hapus Gedung"
                                    onclick="confirmDeleteBuilding({{ $building->id }}, '{{ addslashes($building->name) }}', {{ $building->rooms_count ?? $building->rooms()->count() }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-building fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <div class="fw-semibold">Belum Ada Data Master Gedung</div>
                            <small>Klik tombol "Tambah Gedung" di pojok kanan atas untuk mendaftarkan blok atau gedung baru.</small>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($buildings->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $buildings->links() }}
        </div>
        @endif
    </div>
</div>

<!-- RIGHT-SIDE DRAWER: CREATE / EDIT GEDUNG -->
<div class="offcanvas offcanvas-end border-0 shadow" tabindex="-1" id="buildingDrawer" aria-labelledby="buildingDrawerLabel" style="width: 520px;">
    <div class="offcanvas-header bg-light border-bottom py-3">
        <div>
            <h5 class="offcanvas-title fw-bold text-dark" id="buildingDrawerLabel">Tambah Gedung Baru</h5>
            <p class="text-muted small mb-0" id="buildingDrawerSub">Formulir pendaftaran blok/gedung hunian kos.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form id="buildingForm" method="POST" action="{{ route('dashboard.buildings.store') }}">
            @csrf
            <div id="methodContainer"></div>

            <div class="mb-3" id="propertySelectGroup">
                <label class="form-label small fw-bold">Cabang Properti <span class="text-danger">*</span></label>
                <select name="property_id" id="buildingPropertyId" class="form-select form-select-sm" required>
                    @foreach($properties as $prop)
                    <option value="{{ $prop->id }}">{{ $prop->name }} ({{ $prop->city }})</option>
                    @endforeach
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-5">
                    <label class="form-label small fw-bold">Kode Gedung <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="buildingCode" class="form-control form-control-sm text-uppercase" placeholder="GDG-A" required>
                    <small class="text-muted" style="font-size: 11px;">Contoh: GDG-A, BLOK-B, TWR-1</small>
                </div>
                <div class="col-7">
                    <label class="form-label small fw-bold">Nama Gedung <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="buildingName" class="form-control form-control-sm" placeholder="Gedung Melati Utama" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Total Lantai Fisik <span class="text-danger">*</span></label>
                <input type="number" name="total_floors" id="buildingFloors" class="form-control form-control-sm" value="1" min="1" max="100" required>
                <small class="text-muted" style="font-size: 11px;">Jumlah lantai yang ada di gedung/sayap ini.</small>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Catatan Lokasi / Alamat</label>
                <input type="text" name="address_notes" id="buildingAddress" class="form-control form-control-sm" placeholder="Sayap kiri dekat taman, menghadap timur">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Deskripsi Tambahan</label>
                <textarea name="description" id="buildingDesc" class="form-control form-control-sm" rows="3" placeholder="Fasilitas bersama gedung, aksesibilitas, dll."></textarea>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="buildingActive" value="1" checked>
                <label class="form-check-label small fw-semibold" for="buildingActive">Gedung Aktif Digunakan</label>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="offcanvas">Batal</button>
                <button type="submit" class="btn btn-primary-teal btn-sm px-4 shadow-sm" id="btnSubmitBuilding">
                    <i class="bi bi-check-lg me-1"></i>Simpan Gedung
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal fade" id="deleteBuildingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title fw-bold text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Hapus Gedung
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="small mb-2">Apakah Anda yakin ingin menghapus gedung <strong id="deleteBuildingName"></strong>?</p>
                <div id="deleteBuildingWarning" class="alert alert-warning py-2 small mb-0 d-none">
                    <i class="bi bi-info-circle me-1"></i>Gedung ini memiliki kamar terhubung dan tidak dapat dihapus.
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <form id="deleteBuildingForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger" id="btnConfirmDeleteBuilding">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let buildingDrawerInstance = null;

    document.addEventListener('DOMContentLoaded', function () {
        const drawerEl = document.getElementById('buildingDrawer');
        if (drawerEl) {
            buildingDrawerInstance = new bootstrap.Offcanvas(drawerEl);
        }
    });

    function openCreateBuildingDrawer() {
        const form = document.getElementById('buildingForm');
        form.action = "{{ route('dashboard.buildings.store') }}";
        document.getElementById('methodContainer').innerHTML = '';
        document.getElementById('buildingDrawerLabel').innerText = 'Tambah Gedung Baru';
        document.getElementById('buildingDrawerSub').innerText = 'Formulir pendaftaran blok/gedung hunian kos.';
        document.getElementById('btnSubmitBuilding').innerHTML = '<i class="bi bi-check-lg me-1"></i>Simpan Gedung';

        document.getElementById('buildingCode').value = '';
        document.getElementById('buildingName').value = '';
        document.getElementById('buildingFloors').value = 1;
        document.getElementById('buildingAddress').value = '';
        document.getElementById('buildingDesc').value = '';
        document.getElementById('buildingActive').checked = true;
        document.getElementById('propertySelectGroup').style.display = 'block';

        if (buildingDrawerInstance) {
            buildingDrawerInstance.show();
        }
    }

    function openEditBuildingDrawer(building) {
        const form = document.getElementById('buildingForm');
        form.action = "/dashboard/buildings/" + building.id;
        document.getElementById('methodContainer').innerHTML = '@method("PUT")';
        document.getElementById('buildingDrawerLabel').innerText = 'Edit Gedung: ' + building.name;
        document.getElementById('buildingDrawerSub').innerText = 'Perbarui data spesifikasi dan informasi gedung.';
        document.getElementById('btnSubmitBuilding').innerHTML = '<i class="bi bi-save me-1"></i>Perbarui Gedung';

        document.getElementById('buildingCode').value = building.code;
        document.getElementById('buildingName').value = building.name;
        document.getElementById('buildingFloors').value = building.total_floors;
        document.getElementById('buildingAddress').value = building.address_notes || '';
        document.getElementById('buildingDesc').value = building.description || '';
        document.getElementById('buildingActive').checked = Boolean(building.is_active);
        document.getElementById('buildingPropertyId').value = building.property_id;
        document.getElementById('propertySelectGroup').style.display = 'none';

        if (buildingDrawerInstance) {
            buildingDrawerInstance.show();
        }
    }

    function confirmDeleteBuilding(id, name, roomsCount) {
        document.getElementById('deleteBuildingName').innerText = name;
        const deleteForm = document.getElementById('deleteBuildingForm');
        deleteForm.action = "/dashboard/buildings/" + id;

        const warningEl = document.getElementById('deleteBuildingWarning');
        const deleteBtn = document.getElementById('btnConfirmDeleteBuilding');

        if (roomsCount > 0) {
            warningEl.classList.remove('d-none');
            warningEl.innerHTML = `<i class="bi bi-exclamation-octagon-fill me-1"></i> Gedung ini masih memiliki <strong>${roomsCount} kamar</strong> terhubung. Harap hapus atau pindahkan kamar terlebih dahulu.`;
            deleteBtn.disabled = true;
        } else {
            warningEl.classList.add('d-none');
            deleteBtn.disabled = false;
        }

        const modal = new bootstrap.Modal(document.getElementById('deleteBuildingModal'));
        modal.show();
    }
</script>
@endpush
