@extends('layouts.app')

@section('title', 'Manajemen Aset Tetap & Inventaris')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Manajemen Aset Tetap & Inventaris</h1>
            <p class="text-muted mb-0 small">Pencatatan perolehan, penempatan lokasi/kamar, perhitungan penyusutan, dan buku aset kos.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#batchDepreciationModal">
                <i class="bi bi-calculator fs-6"></i>
                <span class="fw-semibold">Batch Depresiasi</span>
            </button>
            <button type="button" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#assetDrawer">
                <i class="bi bi-plus-circle fs-6"></i>
                <span class="fw-semibold">Tambah Aset</span>
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

    <!-- Financial KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Total Aset</div>
                    <div class="h3 fw-bold text-dark mt-1 mb-0">{{ number_format($summary['total_count']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-box-seam text-primary me-1"></i>Seluruh unit</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Harga Perolehan</div>
                    <div class="h5 fw-bold text-dark mt-1 mb-0">Rp {{ number_format($summary['total_purchase_cost'], 0, ',', '.') }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-wallet2 text-success me-1"></i>Nilai historis</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Akumulasi Penyusutan</div>
                    <div class="h5 fw-bold text-danger mt-1 mb-0">Rp {{ number_format($summary['total_accumulated_depreciation'], 0, ',', '.') }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-graph-down text-danger me-1"></i>Kontra aset</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Nilai Buku Bersih</div>
                    <div class="h5 fw-bold text-success mt-1 mb-0">Rp {{ number_format($summary['total_book_value'], 0, ',', '.') }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-check2-circle text-success me-1"></i>Net book value</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Aset Aktif</div>
                    <div class="h3 fw-bold text-teal mt-1 mb-0">{{ number_format($summary['active_count']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-play-circle text-teal me-1"></i>Operasional</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card kpi-card border-0 shadow-sm h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-medium">Dalam Perawatan</div>
                    <div class="h3 fw-bold text-warning mt-1 mb-0">{{ number_format($summary['maintenance_count']) }}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-tools text-warning me-1"></i>Maintenance</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('dashboard.assets.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-secondary mb-1">Cari Aset</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Kode aset, nama, serial, vendor..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Cabang Properti</label>
                    <select name="property_id" class="form-select form-select-sm">
                        <option value="">Semua Properti</option>
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ request('property_id') == $prop->id ? 'selected' : '' }}>{{ $prop->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Kategori</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        <option value="building_infrastructure" {{ request('category') === 'building_infrastructure' ? 'selected' : '' }}>Bangunan & Prasarana</option>
                        <option value="electronics" {{ request('category') === 'electronics' ? 'selected' : '' }}>Elektronik & Peralatan</option>
                        <option value="furniture" {{ request('category') === 'furniture' ? 'selected' : '' }}>Furnitur & Mebel</option>
                        <option value="vehicles" {{ request('category') === 'vehicles' ? 'selected' : '' }}>Kendaraan</option>
                        <option value="operational_equipment" {{ request('category') === 'operational_equipment' ? 'selected' : '' }}>Peralatan Operasional</option>
                        <option value="other" {{ request('category') === 'other' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-secondary mb-1">Status Lifecycle</label>
                    <select name="lifecycle_status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="active" {{ request('lifecycle_status') === 'active' ? 'selected' : '' }}>Aktif Digunakan</option>
                        <option value="in_maintenance" {{ request('lifecycle_status') === 'in_maintenance' ? 'selected' : '' }}>Dalam Perawatan</option>
                        <option value="inactive" {{ request('lifecycle_status') === 'inactive' ? 'selected' : '' }}>Tidak Digunakan</option>
                        <option value="disposed" {{ request('lifecycle_status') === 'disposed' ? 'selected' : '' }}>Dijual / Dilepas</option>
                        <option value="written_off" {{ request('lifecycle_status') === 'written_off' ? 'selected' : '' }}>Dihapuskan</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label small fw-semibold text-secondary mb-1">Kondisi</label>
                    <select name="condition" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="good" {{ request('condition') === 'good' ? 'selected' : '' }}>Baik</option>
                        <option value="slightly_damaged" {{ request('condition') === 'slightly_damaged' ? 'selected' : '' }}>Rusak Ringan</option>
                        <option value="broken" {{ request('condition') === 'broken' ? 'selected' : '' }}>Rusak Berat</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary-teal flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('dashboard.assets.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset">
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
                            <th class="ps-3 py-3">Kode & Nama Aset</th>
                            <th class="py-3">Properti & Lokasi</th>
                            <th class="py-3">Kategori</th>
                            <th class="py-3 text-end">Harga Perolehan</th>
                            <th class="py-3 text-end">Akum. Penyusutan</th>
                            <th class="py-3 text-end">Nilai Buku</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($assets as $asset)
                            <tr>
                                <td class="ps-3 py-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded bg-light text-teal fw-bold d-flex align-items-center justify-content-center p-2 border" style="width: 36px; height: 36px;">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                        <div>
                                            <a href="{{ route('dashboard.assets.show', $asset->id) }}" class="fw-bold text-dark text-decoration-none">
                                                {{ $asset->name }}
                                            </a>
                                            <div class="small text-muted font-monospace">
                                                {{ $asset->asset_code }} @if($asset->serial_number) • SN: {{ $asset->serial_number }} @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-semibold text-dark">{{ $asset->property->name }}</div>
                                    <div class="small text-muted">
                                        <i class="bi bi-geo-alt me-1 text-secondary"></i>{{ $asset->location_label }}
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-light text-dark border">
                                        {{ ucfirst(str_replace('_', ' ', $asset->category)) }}
                                    </span>
                                    <div class="small text-muted mt-1">{{ ucfirst(str_replace('_', ' ', $asset->classification)) }}</div>
                                </td>
                                <td class="py-3 text-end fw-semibold">
                                    Rp {{ number_format($asset->purchase_cost, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end text-danger fw-semibold">
                                    Rp {{ number_format($asset->accumulated_depreciation, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end text-success fw-bold">
                                    Rp {{ number_format($asset->current_book_value, 0, ',', '.') }}
                                </td>
                                <td class="py-3">
                                    @php
                                        $badgeColor = match($asset->lifecycle_status) {
                                            'active' => 'bg-success-subtle text-success border border-success-subtle',
                                            'in_maintenance' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                            'inactive' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                            'disposed', 'written_off' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                            default => 'bg-light text-dark'
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeColor }} px-2 py-1">
                                        {{ ucfirst(str_replace('_', ' ', $asset->lifecycle_status)) }}
                                    </span>
                                    <div class="small text-muted mt-1">Kondisi: {{ ucfirst(str_replace('_', ' ', $asset->condition)) }}</div>
                                </td>
                                <td class="py-3 text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('dashboard.assets.show', $asset->id) }}" class="btn btn-outline-secondary" title="Detail Aset">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('dashboard.assets.show', $asset->id) }}">
                                                    <i class="bi bi-card-text me-2 text-primary"></i>Detail & Skedul
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-box fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    Tidak ada data aset tetap yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($assets->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        Menampilkan {{ $assets->firstItem() }} - {{ $assets->lastItem() }} dari {{ $assets->total() }} aset
                    </div>
                    <div>
                        {{ $assets->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- RIGHT-SIDE DRAWER: Tambah Master Aset -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="assetDrawer" aria-labelledby="assetDrawerLabel" style="width: 540px; max-width: 95vw;">
    <div class="offcanvas-header border-bottom bg-light">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary-teal text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                <i class="bi bi-box-seam-fill"></i>
            </div>
            <h5 class="offcanvas-title fw-bold text-dark fs-6" id="assetDrawerLabel">Daftarkan Aset Tetap / Inventaris</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form action="{{ route('dashboard.assets.store') }}" method="POST">
            @csrf

            <!-- Cabang Properti -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Cabang Properti <span class="text-danger">*</span></label>
                <select name="property_id" id="drawerAssetPropSelect" class="form-select form-select-sm" required>
                    <option value="">Pilih Properti</option>
                    @foreach($properties as $prop)
                        <option value="{{ $prop->id }}" {{ old('property_id') == $prop->id ? 'selected' : '' }}>
                            {{ $prop->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Nama Aset -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Nama Barang / Aset <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control form-control-sm" placeholder="Contoh: AC Daikin 1 PK, Smart TV Samsung 43 Inch" value="{{ old('name') }}" required>
            </div>

            <div class="row g-2 mb-3">
                <!-- Kategori -->
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Kelompok Aset <span class="text-danger">*</span></label>
                    <select name="category" class="form-select form-select-sm" required>
                        <option value="electronics" selected>Elektronik & Peralatan</option>
                        <option value="furniture">Furnitur & Mebel</option>
                        <option value="building_infrastructure">Bangunan & Prasarana</option>
                        <option value="vehicles">Kendaraan Operasional</option>
                        <option value="operational_equipment">Peralatan Operasional</option>
                        <option value="other">Lainnya</option>
                    </select>
                </div>
                <!-- Klasifikasi -->
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Klasifikasi Akuntansi <span class="text-danger">*</span></label>
                    <select name="classification" class="form-select form-select-sm" required>
                        <option value="fixed_asset" selected>Aset Tetap (Disusutkan)</option>
                        <option value="low_value_inventory">Inventaris Nilai Rendah</option>
                        <option value="consumable">Barang Habis Pakai</option>
                        <option value="wip">Aset Dalam Pengerjaan (WIP)</option>
                    </select>
                </div>
            </div>

            <!-- Lokasi Penempatan -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Jenis Lokasi <span class="text-danger">*</span></label>
                    <select name="location_type" id="drawerLocTypeSelect" class="form-select form-select-sm" required>
                        <option value="room">Kamar Penyewa</option>
                        <option value="common_area">Area Umum / Fasilitas</option>
                        <option value="warehouse">Gudang / Penyimpanan</option>
                        <option value="building">Struktur Gedung</option>
                    </select>
                </div>
                <div class="col-6" id="roomSelectContainer">
                    <label class="form-label small fw-bold text-dark">Pilih Kamar</label>
                    <select name="room_id" class="form-select form-select-sm">
                        <option value="">-- Tanpa Kamar --</option>
                        @foreach($rooms as $rm)
                            <option value="{{ $rm->id }}">{{ $rm->room_number }} ({{ $rm->property?->name }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Keterangan Lokasi Spesifik</label>
                <input type="text" name="location_name" class="form-control form-control-sm" placeholder="Contoh: Lobi Lantai 1, Balkon Belakang, Dapur Bersama" value="{{ old('location_name') }}">
            </div>

            <hr class="text-muted my-3">

            <div class="fw-bold small text-dark mb-2">Nilai Finansial & Parameter Depresiasi</div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Harga Perolehan (Rp) <span class="text-danger">*</span></label>
                    <input type="number" name="purchase_cost" class="form-control form-control-sm" placeholder="Contoh: 4500000" value="{{ old('purchase_cost') }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Nilai Residu (Rp)</label>
                    <input type="number" name="salvage_value" class="form-control form-control-sm" placeholder="Contoh: 500000" value="{{ old('salvage_value', 0) }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Masa Manfaat (Bulan)</label>
                    <input type="number" name="useful_life_months" class="form-control form-control-sm" placeholder="Contoh: 48 (4 tahun)" value="{{ old('useful_life_months', 48) }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Metode Depresiasi</label>
                    <select name="depreciation_method" class="form-select form-select-sm">
                        <option value="straight_line" selected>Garis Lurus (Straight Line)</option>
                        <option value="none">Tanpa Depresiasi</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Tanggal Perolehan <span class="text-danger">*</span></label>
                    <input type="date" name="acquisition_date" class="form-control form-control-sm" value="{{ old('acquisition_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Mulai Digunakan</label>
                    <input type="date" name="in_service_date" class="form-control form-control-sm" value="{{ old('in_service_date', date('Y-m-d')) }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Nomor Seri (Serial No)</label>
                    <input type="text" name="serial_number" class="form-control form-control-sm" placeholder="SN12345678" value="{{ old('serial_number') }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Masa Garansi Berakhir</label>
                    <input type="date" name="warranty_expiry_date" class="form-control form-control-sm" value="{{ old('warranty_expiry_date') }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Nama Vendor / Toko</label>
                    <input type="text" name="vendor_name" class="form-control form-control-sm" placeholder="Mitra 10, Toko Elektronik Jaya" value="{{ old('vendor_name') }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">No. Faktur / Referensi</label>
                    <input type="text" name="purchase_reference" class="form-control form-control-sm" placeholder="INV-2026-001" value="{{ old('purchase_reference') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Catatan Tambahan</label>
                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Informasi garansi, manual, kontak teknisi...">{{ old('notes') }}</textarea>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary-teal py-2 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Master Aset
                </button>
                <button type="button" class="btn btn-light py-2" data-bs-dismiss="offcanvas">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Batch Depresiasi Massal -->
<div class="modal fade" id="batchDepreciationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('dashboard.assets.batch-depreciation') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Proses Batch Depresiasi Massal</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Proses ini akan menghitung beban penyusutan dan memposting jurnal umum berpasangan secara otomatis (Debit Beban Penyusutan 5201, Kredit Akumulasi Penyusutan 1391) untuk seluruh aset aktif pada properti dan periode yang dipilih.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Cabang Properti <span class="text-danger">*</span></label>
                        <select name="property_id" class="form-select form-select-sm" required>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}">{{ $prop->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Tahun Fiskal <span class="text-danger">*</span></label>
                            <input type="number" name="fiscal_year" class="form-control form-control-sm" value="{{ date('Y') }}" min="2020" max="2050" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Bulan Fiskal <span class="text-danger">*</span></label>
                            <select name="fiscal_month" class="form-select form-select-sm" required>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ date('n') == $m ? 'selected' : '' }}>
                                        Bulan {{ $m }} ({{ DateTime::createFromFormat('!m', $m)->format('F') }})
                                    </option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="dry_run" id="batchDryRunCheck" value="1" checked>
                        <label class="form-check-label small fw-semibold text-dark" for="batchDryRunCheck">
                            Simulasi Dahulu (Dry Run - Tanpa Posting Jurnal Permanen)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary-teal">
                        <i class="bi bi-play-fill me-1"></i>Eksekusi Proses
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
