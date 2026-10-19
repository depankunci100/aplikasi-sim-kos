@extends('layouts.app')

@section('title', 'Master Kamar & Unit Hunian')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Master Kamar & Unit Hunian</h1>
            <p class="text-muted mb-0 small">Kelola data kamar, harga sewa, fasilitas, status hunian, dan ketersediaan booking.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary-teal d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#roomDrawer">
                <i class="bi bi-plus-circle fs-6"></i>
                <span class="fw-semibold">Tambah Kamar</span>
            </button>
        </div>
    </div>

    <!-- Alert Feedback -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <div class="fw-bold mb-1"><i class="bi bi-x-circle me-1"></i> Terjadi kesalahan validasi:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Total Kamar</div>
                    <div class="h3 fw-bold text-dark mt-1 mb-0">{{ number_format($counts['total']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-door-closed text-primary me-1"></i>Semua unit</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Tersedia</div>
                    <div class="h3 fw-bold text-success mt-1 mb-0">{{ number_format($counts['available']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-check2-circle text-success me-1"></i>Siap dipesan</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Ditempati</div>
                    <div class="h3 fw-bold text-primary mt-1 mb-0">{{ number_format($counts['occupied']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-person-check text-primary me-1"></i>Sedang dihuni</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Perbaikan</div>
                    <div class="h3 fw-bold text-warning mt-1 mb-0">{{ number_format($counts['maintenance']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-tools text-warning me-1"></i>Maintenance</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Kotor / Turnover</div>
                    <div class="h3 fw-bold text-danger mt-1 mb-0">{{ number_format($counts['dirty']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-stars text-danger me-1"></i>Perlu dibersihkan</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Nonaktif</div>
                    <div class="h3 fw-bold text-secondary mt-1 mb-0">{{ number_format($counts['inactive']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-slash-circle text-secondary me-1"></i>Mati operasional</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('dashboard.rooms.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Cari Kamar</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Nomor kamar, gedung..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Properti</label>
                    <select name="property_id" class="form-select form-select-sm">
                        <option value="">Semua Properti</option>
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ request('property_id') == $prop->id ? 'selected' : '' }}>
                                {{ $prop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Tipe Kamar</label>
                    <select name="room_type_id" class="form-select form-select-sm">
                        <option value="">Semua Tipe</option>
                        @foreach($roomTypes as $type)
                            <option value="{{ $type->id }}" {{ request('room_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }} ({{ $type->property?->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Status Hunian</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Tersedia</option>
                        <option value="occupied" {{ request('status') === 'occupied' ? 'selected' : '' }}>Ditempati</option>
                        <option value="maintenance" {{ request('status') === 'maintenance' ? 'selected' : '' }}>Dalam Perbaikan</option>
                        <option value="dirty" {{ request('status') === 'dirty' ? 'selected' : '' }}>Kotor</option>
                    </select>
                </div>
                @if(isset($buildings) && $buildings->count() > 0)
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Gedung</label>
                    <select name="building_id" class="form-select form-select-sm">
                        <option value="">Semua Gedung</option>
                        @foreach($buildings as $bld)
                            <option value="{{ $bld->id }}" {{ request('building_id') == $bld->id ? 'selected' : '' }}>
                                {{ $bld->name }} ({{ $bld->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-6 col-md-1">
                    <label class="form-label small fw-semibold text-secondary mb-1">Status Aktif</label>
                    <select name="is_active" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary-teal flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('dashboard.rooms.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3 py-3">Kamar & Lokasi</th>
                            <th class="py-3">Properti & Tipe</th>
                            <th class="py-3">Spesifikasi</th>
                            <th class="py-3">Tarif Sewa (Bln)</th>
                            <th class="py-3">Status Hunian</th>
                            <th class="py-3">Operasional</th>
                            <th class="py-3 text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($rooms as $room)
                            <tr>
                                <td class="ps-3 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-light text-dark fw-bold d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                            {{ $room->room_number }}
                                        </div>
                                        <div>
                                            <a href="{{ route('dashboard.rooms.show', $room->id) }}" class="fw-bold text-dark text-decoration-none hover-teal">
                                                Kamar {{ $room->room_number }}
                                            </a>
                                            <div class="small text-muted">
                                                Lantai {{ $room->floor }} @if($room->building_name) • Gedung {{ $room->building_name }} @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-semibold text-dark">{{ $room->property->name }}</div>
                                    <div class="small text-muted">{{ $room->roomType->name }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="small text-dark">
                                        <i class="bi bi-people me-1 text-secondary"></i>Kapasitas: {{ $room->effective_capacity }} Org
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-aspect-ratio me-1 text-secondary"></i>Luas: {{ $room->effective_size > 0 ? $room->effective_size . ' m²' : '-' }}
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark">
                                        Rp {{ number_format($room->effective_monthly_price, 0, ',', '.') }}
                                    </div>
                                    @if($room->price_monthly)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 0.65rem;">Harga Kustom</span>
                                    @else
                                        <span class="text-muted" style="font-size: 0.72rem;">Bawaan Tipe</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    @php
                                        $statusClass = match($room->status->value) {
                                            'available' => 'bg-success-subtle text-success border border-success-subtle',
                                            'occupied' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                            'maintenance' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                            'dirty' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                            default => 'bg-secondary-subtle text-secondary'
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }} px-2 py-1">
                                        {{ $room->status->label() }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    @if($room->is_active)
                                        <span class="badge bg-teal-subtle text-teal border border-teal-subtle px-2 py-1">
                                            <i class="bi bi-check-circle me-1"></i>Aktif
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                            <i class="bi bi-slash-circle me-1"></i>Nonaktif
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('dashboard.rooms.show', $room->id) }}" class="btn btn-outline-secondary" title="Detail Kamar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('dashboard.rooms.show', $room->id) }}">
                                                    <i class="bi bi-card-text me-2 text-primary"></i>Lihat Detail & Tab
                                                </a>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#statusModal{{ $room->id }}">
                                                    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Ubah Status Operasional
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#toggleActiveModal{{ $room->id }}">
                                                    <i class="bi bi-power me-2"></i>{{ $room->is_active ? 'Nonaktifkan Kamar' : 'Aktifkan Kembali' }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Modal Ubah Status -->
                                    <div class="modal fade text-start" id="statusModal{{ $room->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                            <div class="modal-content border-0 shadow">
                                                <form action="{{ route('dashboard.rooms.status', $room->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header border-bottom-0 pb-0">
                                                        <h6 class="modal-title fw-bold">Ubah Status: {{ $room->room_number }}</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body py-3">
                                                        <label class="form-label small fw-semibold text-secondary">Pilih Status Baru</label>
                                                        <select name="status" class="form-select form-select-sm mb-2">
                                                            <option value="available" {{ $room->status->value === 'available' ? 'selected' : '' }}>Tersedia</option>
                                                            <option value="occupied" {{ $room->status->value === 'occupied' ? 'selected' : '' }}>Ditempati</option>
                                                            <option value="maintenance" {{ $room->status->value === 'maintenance' ? 'selected' : '' }}>Dalam Perbaikan</option>
                                                            <option value="dirty" {{ $room->status->value === 'dirty' ? 'selected' : '' }}>Kotor</option>
                                                        </select>
                                                        <label class="form-label small fw-semibold text-secondary">Catatan / Alasan</label>
                                                        <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Alasan perubahan status..."></textarea>
                                                    </div>
                                                    <div class="modal-footer border-top-0 pt-0">
                                                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-sm btn-primary-teal">Simpan Status</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Toggle Active -->
                                    <div class="modal fade text-start" id="toggleActiveModal{{ $room->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow">
                                                <form action="{{ route('dashboard.rooms.toggle-active', $room->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">
                                                            {{ $room->is_active ? 'Konfirmasi Nonaktifkan Kamar' : 'Konfirmasi Aktifkan Kamar' }}
                                                        </h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="small text-muted mb-3">
                                                            Anda akan {{ $room->is_active ? 'menonaktifkan' : 'mengaktifkan kembali' }} Kamar <strong>{{ $room->room_number }}</strong> pada properti <strong>{{ $room->property->name }}</strong>.
                                                            @if($room->is_active)
                                                                Kamar yang dinonaktifkan tidak akan muncul pada pencarian booking publik maupun alokasi sewa baru.
                                                            @endif
                                                        </p>
                                                        <label class="form-label small fw-semibold text-secondary">Alasan Perubahan Status</label>
                                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Contoh: Renovasi total lantai, persiapan pembongkaran...">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-sm {{ $room->is_active ? 'btn-danger' : 'btn-success' }}">
                                                            {{ $room->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-door-open fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    Tidak ada data kamar yang sesuai dengan kriteria filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($rooms->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Menampilkan {{ $rooms->firstItem() }} - {{ $rooms->lastItem() }} dari {{ $rooms->total() }} kamar
                    </div>
                    <div>
                        {{ $rooms->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- RIGHT-SIDE DRAWER: Tambah Master Kamar -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="roomDrawer" aria-labelledby="roomDrawerLabel" style="width: 520px; max-width: 95vw;">
    <div class="offcanvas-header border-bottom bg-light">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary-teal text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                <i class="bi bi-door-closed-fill"></i>
            </div>
            <h5 class="offcanvas-title fw-bold text-dark fs-6" id="roomDrawerLabel">Tambah Master Kamar Baru</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form action="{{ route('dashboard.rooms.store') }}" method="POST" id="createRoomForm">
            @csrf

            <!-- Cabang Properti -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Cabang Properti <span class="text-danger">*</span></label>
                <select name="property_id" id="drawerPropertySelect" class="form-select form-select-sm" required>
                    <option value="">Pilih Properti</option>
                    @foreach($properties as $prop)
                        <option value="{{ $prop->id }}" {{ old('property_id') == $prop->id ? 'selected' : '' }}>
                            {{ $prop->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Tipe Kamar -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Tipe Kamar <span class="text-danger">*</span></label>
                <select name="room_type_id" id="drawerRoomTypeSelect" class="form-select form-select-sm" required>
                    <option value="">Pilih Tipe Kamar</option>
                    @foreach($roomTypes as $type)
                        <option value="{{ $type->id }}" data-property="{{ $type->property_id }}" {{ old('room_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }} (Rp {{ number_format($type->base_price_monthly, 0, ',', '.') }}/bln)
                        </option>
                    @endforeach
                </select>
                <div class="form-text small">Spesifikasi dasar, fasilitas bawaan, dan foto utama akan diwarisi dari tipe kamar.</div>
            </div>

            <div class="row g-2 mb-3">
                <!-- Nomor Kamar -->
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Nomor Kamar <span class="text-danger">*</span></label>
                    <input type="text" name="room_number" class="form-control form-control-sm" placeholder="Contoh: 101, A-02" value="{{ old('room_number') }}" required>
                </div>
                <!-- Lantai -->
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Lantai <span class="text-danger">*</span></label>
                    <input type="number" name="floor" class="form-control form-control-sm" min="1" max="50" value="{{ old('floor', 1) }}" required>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <!-- Gedung / Sayap -->
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Gedung / Blok / Sayap</label>
                    @if(isset($buildings) && $buildings->count() > 0)
                    <select name="building_id" class="form-select form-select-sm mb-1" id="drawerBuildingSelect" onchange="syncBuildingName(this)">
                        <option value="">-- Pilih Master Gedung --</option>
                        @foreach($buildings as $bld)
                        <option value="{{ $bld->id }}" data-name="{{ $bld->name }}" {{ old('building_id') == $bld->id ? 'selected' : '' }}>
                            {{ $bld->name }} ({{ $bld->code }})
                        </option>
                        @endforeach
                    </select>
                    <input type="text" name="building" id="drawerBuildingText" class="form-control form-control-sm" placeholder="Atau ketik nama kustom" value="{{ old('building') }}">
                    @else
                    <input type="text" name="building" class="form-control form-control-sm" placeholder="Gedung Utama, Blok B" value="{{ old('building') }}">
                    @endif
                </div>
                <!-- Luas Kamar -->
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Luas Kamar (m²)</label>
                    <input type="number" step="0.1" name="size_m2" class="form-control form-control-sm" placeholder="Contoh: 12.5" value="{{ old('size_m2') }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <!-- Kapasitas Orang -->
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Maks Penghuni (Org)</label>
                    <input type="number" name="capacity" class="form-control form-control-sm" min="1" max="10" placeholder="1" value="{{ old('capacity', 1) }}">
                </div>
                <!-- Status Awal -->
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Status Awal</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="available" selected>Tersedia (Ready)</option>
                        <option value="maintenance">Dalam Perbaikan</option>
                        <option value="dirty">Kotor / Perlu Dibersihkan</option>
                    </select>
                </div>
            </div>

            <hr class="text-muted my-3">

            <div class="fw-bold small text-dark mb-2">Penyesuaian Tarif Sewa Khusus (Opsional)</div>
            <p class="text-muted small mb-2">Kosongkan jika ingin menggunakan tarif dasar bawaan Tipe Kamar.</p>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Tarif Bulanan (Rp)</label>
                    <input type="number" name="price_monthly" class="form-control form-control-sm" placeholder="Contoh: 1500000" value="{{ old('price_monthly') }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Tarif Harian (Rp)</label>
                    <input type="number" name="price_daily" class="form-control form-control-sm" placeholder="Contoh: 150000" value="{{ old('price_daily') }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Deposit / Uang Jaminan (Rp)</label>
                    <input type="number" name="deposit_amount" class="form-control form-control-sm" placeholder="Default properti" value="{{ old('deposit_amount') }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Down Payment / DP (Rp)</label>
                    <input type="number" name="down_payment_amount" class="form-control form-control-sm" placeholder="Default properti" value="{{ old('down_payment_amount') }}">
                </div>
            </div>

            <!-- Fasilitas Tambahan -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Fasilitas Khusus Unit</label>
                <input type="text" name="facilities" class="form-control form-control-sm" placeholder="AC Split, Balkon Pribadi, Smart TV (pisahkan dengan koma)" value="{{ old('facilities') }}">
                <div class="form-text small">Fasilitas bawaan tipe kamar tetap otomatis disertakan.</div>
            </div>

            <!-- Catatan Internal -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Catatan Internal Kamar</label>
                <textarea name="internal_notes" class="form-control form-control-sm" rows="2" placeholder="Catatan khusus teknis, meteran listrik terpisah, dll.">{{ old('internal_notes') }}</textarea>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="drawerIsActive" value="1" checked>
                <label class="form-check-label small fw-semibold text-dark" for="drawerIsActive">Kamar Aktif untuk Operasional</label>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary-teal py-2 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Data Kamar
                </button>
                <button type="button" class="btn btn-light py-2" data-bs-dismiss="offcanvas">Batal</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function syncBuildingName(select) {
        const selected = select.options[select.selectedIndex];
        const name = selected ? selected.getAttribute('data-name') : '';
        const textInput = document.getElementById('drawerBuildingText');
        if (textInput && name) {
            textInput.value = name;
        }
    }
</script>
@endpush
