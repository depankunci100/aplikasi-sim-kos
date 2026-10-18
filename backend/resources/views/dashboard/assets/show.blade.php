@extends('layouts.app')

@section('title', 'Detail Aset: ' . $asset->name . ' (' . $asset->asset_code . ')')

@section('content')
<div class="container-fluid px-0">
    <!-- Header & Breadcrumb -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.assets.index') }}" class="text-decoration-none text-muted">Aset & Inventaris</a></li>
                    <li class="breadcrumb-item"><span class="text-muted">{{ $asset->property->name }}</span></li>
                    <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">{{ $asset->asset_code }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold text-dark mb-0">{{ $asset->name }}</h1>
                @php
                    $badgeColor = match($asset->lifecycle_status) {
                        'active' => 'bg-success-subtle text-success border border-success-subtle',
                        'in_maintenance' => 'bg-warning-subtle text-warning border border-warning-subtle',
                        'inactive' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                        'disposed', 'written_off' => 'bg-danger-subtle text-danger border border-danger-subtle',
                        default => 'bg-light text-dark'
                    };
                @endphp
                <span class="badge {{ $badgeColor }} px-3 py-1 fs-6">
                    {{ ucfirst(str_replace('_', ' ', $asset->lifecycle_status)) }}
                </span>
                <span class="badge bg-light text-dark border px-2 py-1">
                    Kondisi: {{ ucfirst(str_replace('_', ' ', $asset->condition)) }}
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if(!in_array($asset->lifecycle_status, ['disposed', 'written_off']))
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#transferLocationModal">
                    <i class="bi bi-arrow-left-right me-1"></i>Mutasi Lokasi
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#disposalModal">
                    <i class="bi bi-box-arrow-right me-1"></i>Pelepasan Aset
                </button>
                <button type="button" class="btn btn-primary-teal btn-sm shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#editAssetDrawer">
                    <i class="bi bi-pencil-square me-1"></i>Edit Aset
                </button>
            @endif
            <a href="{{ route('dashboard.assets.index') }}" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
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

    <!-- Mini Financial Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Harga Perolehan (Cost)</div>
                <div class="h5 fw-bold text-dark mt-1 mb-0">Rp {{ number_format($asset->purchase_cost, 0, ',', '.') }}</div>
                <div class="small text-muted">Perolehan: {{ $asset->acquisition_date?->format('d/m/Y') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Akumulasi Penyusutan</div>
                <div class="h5 fw-bold text-danger mt-1 mb-0">Rp {{ number_format($asset->accumulated_depreciation, 0, ',', '.') }}</div>
                <div class="small text-muted">Masa: {{ $asset->useful_life_months }} Bulan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Nilai Buku Saat Ini (Book Value)</div>
                <div class="h5 fw-bold text-success mt-1 mb-0">Rp {{ number_format($asset->current_book_value, 0, ',', '.') }}</div>
                <div class="small text-muted">Residu: Rp {{ number_format($asset->salvage_value, 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Lokasi Penempatan</div>
                <div class="fw-bold text-dark fs-6 mt-1 mb-0">{{ $asset->location_label }}</div>
                <div class="small text-muted"><i class="bi bi-geo-alt text-primary me-1"></i>{{ $asset->property->name }}</div>
            </div>
        </div>
    </div>

    <!-- Multi-Tab Details -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs card-header-tabs mx-3 mt-2 border-bottom-0" id="assetDetailTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="spec-tab" data-bs-toggle="tab" data-bs-target="#tab-spec" type="button" role="tab">
                        <i class="bi bi-info-circle me-1"></i>Spesifikasi & Detail
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="depr-tab" data-bs-toggle="tab" data-bs-target="#tab-depr" type="button" role="tab">
                        <i class="bi bi-calculator me-1"></i>Skedul Penyusutan ({{ count($previewSchedules) }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="transfer-tab" data-bs-toggle="tab" data-bs-target="#tab-transfer" type="button" role="tab">
                        <i class="bi bi-arrow-left-right me-1"></i>Histori Mutasi Lokasi ({{ $asset->locationHistories->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#tab-maintenance" type="button" role="tab">
                        <i class="bi bi-tools me-1"></i>Riwayat Pemeliharaan ({{ $asset->maintenanceRequests->count() }})
                    </button>
                </li>
                @if(in_array($asset->lifecycle_status, ['disposed', 'written_off']))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-danger" id="disposal-tab" data-bs-toggle="tab" data-bs-target="#tab-disposal" type="button" role="tab">
                            <i class="bi bi-receipt me-1"></i>Pelepasan & Jurnal
                        </button>
                    </li>
                @endif
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="assetDetailTabsContent">
                <!-- 1. TAB SPESIFIKASI -->
                <div class="tab-pane fade show active" id="tab-spec" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Identitas & Legalitas Aset</h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-muted py-2" style="width: 40%;">Kode Aset Unik</td>
                                        <td class="fw-bold font-monospace text-primary py-2">{{ $asset->asset_code }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Nama Barang</td>
                                        <td class="fw-semibold py-2">{{ $asset->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Kelompok Kategori</td>
                                        <td class="py-2">{{ ucfirst(str_replace('_', ' ', $asset->category)) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Klasifikasi Akuntansi</td>
                                        <td class="py-2">{{ ucfirst(str_replace('_', ' ', $asset->classification)) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Nomor Seri (Serial No)</td>
                                        <td class="py-2 font-monospace">{{ $asset->serial_number ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Masa Garansi</td>
                                        <td class="py-2">
                                            @if($asset->warranty_expiry_date)
                                                {{ $asset->warranty_expiry_date->format('d/m/Y') }}
                                                @if($asset->warranty_expiry_date->isPast())
                                                    <span class="badge bg-danger ms-1">Garansi Berakhir</span>
                                                @else
                                                    <span class="badge bg-success ms-1">Masih Bergaransi</span>
                                                @endif
                                            @else
                                                <span class="text-muted">Tidak ada data garansi</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Vendor / Pemasok</td>
                                        <td class="py-2">{{ $asset->vendor_name ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Referensi Pembelian</td>
                                        <td class="py-2">{{ $asset->purchase_reference ?: '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Akuntansi & Parameter Penyusutan</h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-muted py-2" style="width: 45%;">Harga Perolehan Asli</td>
                                        <td class="fw-bold text-dark py-2">Rp {{ number_format($asset->purchase_cost, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Nilai Residu (Salvage)</td>
                                        <td class="py-2">Rp {{ number_format($asset->salvage_value, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Masa Manfaat Ekonomis</td>
                                        <td class="py-2">{{ $asset->useful_life_months }} Bulan ({{ round($asset->useful_life_months / 12, 1) }} Tahun)</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Penyusutan Bulanan</td>
                                        <td class="fw-semibold text-danger py-2">
                                            Rp {{ number_format($asset->calculateMonthlyDepreciation(), 0, ',', '.') }}/bulan
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Akumulasi Penyusutan</td>
                                        <td class="text-danger py-2">Rp {{ number_format($asset->accumulated_depreciation, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Nilai Buku Saat Ini</td>
                                        <td class="fw-bold text-success py-2">Rp {{ number_format($asset->current_book_value, 0, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Tanggal Mulai Dipakai</td>
                                        <td class="py-2">{{ $asset->in_service_date?->format('d/m/Y') ?: $asset->acquisition_date?->format('d/m/Y') }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            @if($asset->notes)
                                <div class="mt-3 p-3 bg-light rounded border">
                                    <div class="small fw-bold text-secondary mb-1"><i class="bi bi-sticky me-1"></i>Catatan Operasional:</div>
                                    <div class="small text-dark">{{ $asset->notes }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 2. TAB SKEDUL DEPRESIASI -->
                <div class="tab-pane fade" id="tab-depr" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Skedul Penyusutan Garis Lurus (Straight-Line)</h6>
                            <div class="small text-muted">Jadwal perhitungan beban bulanan berpasangan (Beban Penyusutan 5201 vs Akumulasi Penyusutan 1391).</div>
                        </div>
                    </div>

                    @if(!empty($previewSchedules))
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2">Periode (Bulan/Thn)</th>
                                        <th class="py-2 text-end">Beban Depresiasi</th>
                                        <th class="py-2 text-end">Akum. Awal</th>
                                        <th class="py-2 text-end">Akum. Akhir</th>
                                        <th class="py-2 text-end">Nilai Buku Awal</th>
                                        <th class="py-2 text-end">Nilai Buku Akhir</th>
                                        <th class="py-2 text-center">Status</th>
                                        <th class="py-2">No. Jurnal</th>
                                        <th class="py-2 text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($previewSchedules as $sch)
                                        <tr>
                                            <td class="fw-bold py-2">{{ $sch['fiscal_month'] }}/{{ $sch['fiscal_year'] }}</td>
                                            <td class="text-end text-danger fw-semibold py-2">
                                                Rp {{ number_format($sch['depreciation_amount'], 0, ',', '.') }}
                                            </td>
                                            <td class="text-end text-muted py-2">Rp {{ number_format($sch['accumulated_before'], 0, ',', '.') }}</td>
                                            <td class="text-end text-danger py-2">Rp {{ number_format($sch['accumulated_after'], 0, ',', '.') }}</td>
                                            <td class="text-end text-muted py-2">Rp {{ number_format($sch['book_value_beginning'], 0, ',', '.') }}</td>
                                            <td class="text-end text-success fw-bold py-2">Rp {{ number_format($sch['book_value_ending'], 0, ',', '.') }}</td>
                                            <td class="text-center py-2">
                                                @if($sch['status'] === 'posted')
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Posted</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Belum Posted</span>
                                                @endif
                                            </td>
                                            <td class="py-2 font-monospace text-muted">
                                                {{ $sch['journal_number'] ?: '-' }}
                                            </td>
                                            <td class="text-end py-2">
                                                @if($sch['status'] !== 'posted' && !in_array($asset->lifecycle_status, ['disposed', 'written_off']))
                                                    <form action="{{ route('dashboard.assets.post-depreciation', $asset->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="fiscal_year" value="{{ $sch['fiscal_year'] }}">
                                                        <input type="hidden" name="fiscal_month" value="{{ $sch['fiscal_month'] }}">
                                                        <button type="submit" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: 0.72rem;">
                                                            <i class="bi bi-check2 me-1"></i>Post Jurnal
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-muted" style="font-size: 0.72rem;">Tercatat</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 border rounded text-center text-muted small bg-light">
                            <i class="bi bi-slash-circle fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            Aset ini tidak memiliki parameter penyusutan atau bukan aset tetap.
                        </div>
                    @endif
                </div>

                <!-- 3. TAB MUTASI LOKASI -->
                <div class="tab-pane fade" id="tab-transfer" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Riwayat Mutasi & Pergerakan Lokasi Fisik</h6>
                        @if(!in_array($asset->lifecycle_status, ['disposed', 'written_off']))
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#transferLocationModal">
                                <i class="bi bi-plus-circle me-1"></i>Pindahkan Lokasi Aset
                            </button>
                        @endif
                    </div>

                    @if($asset->locationHistories->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2">Waktu Pemindahan</th>
                                        <th class="py-2">Dari Lokasi</th>
                                        <th class="py-2">Ke Lokasi Baru</th>
                                        <th class="py-2">Petugas</th>
                                        <th class="py-2">Alasan Pemindahan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($asset->locationHistories as $hist)
                                        <tr>
                                            <td class="text-muted py-2 text-nowrap">{{ $hist->transferred_at->format('d/m/Y H:i') }}</td>
                                            <td class="py-2">
                                                @if($hist->from_room_id && $hist->fromRoom)
                                                    Kamar {{ $hist->fromRoom->room_number }}
                                                @else
                                                    {{ $hist->from_location_name ?: ucfirst($hist->from_location_type ?: '-') }}
                                                @endif
                                            </td>
                                            <td class="fw-semibold py-2">
                                                @if($hist->to_room_id && $hist->toRoom)
                                                    Kamar {{ $hist->toRoom->room_number }}
                                                @else
                                                    {{ $hist->to_location_name ?: ucfirst($hist->to_location_type) }}
                                                @endif
                                            </td>
                                            <td class="py-2">{{ $hist->transferredByUser?->name ?? 'Sistem' }}</td>
                                            <td class="text-muted py-2">{{ $hist->reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 border rounded text-center text-muted small bg-light">
                            <i class="bi bi-geo-alt fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            Belum ada riwayat mutasi perpindahan lokasi untuk aset ini.
                        </div>
                    @endif
                </div>

                <!-- 4. TAB RIWAYAT PEMELIHARAAN -->
                <div class="tab-pane fade" id="tab-maintenance" role="tabpanel">
                    <h6 class="fw-bold text-dark mb-3">Kartu Pemeliharaan & Tiket Perbaikan</h6>
                    @if($asset->maintenanceRequests->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2">No. Tiket</th>
                                        <th class="py-2">Keluhan / Masalah</th>
                                        <th class="py-2">Pelapor / Teknisi</th>
                                        <th class="py-2 text-end">Biaya Jasa</th>
                                        <th class="py-2 text-end">Biaya Sparepart</th>
                                        <th class="py-2 text-end">Total Biaya</th>
                                        <th class="py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($asset->maintenanceRequests as $req)
                                        <tr>
                                            <td class="fw-bold py-2 font-monospace">{{ $req->ticket_number }}</td>
                                            <td class="py-2">{{ $req->title }}</td>
                                            <td class="py-2">{{ $req->reporter?->name }} / {{ $req->assignee?->name ?: 'Belum ditugaskan' }}</td>
                                            <td class="text-end py-2">Rp {{ number_format($req->labor_cost, 0, ',', '.') }}</td>
                                            <td class="text-end py-2">Rp {{ number_format($req->material_cost, 0, ',', '.') }}</td>
                                            <td class="text-end fw-bold py-2">Rp {{ number_format($req->total_cost, 0, ',', '.') }}</td>
                                            <td class="py-2"><span class="badge bg-light text-dark border">{{ $req->status }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 border rounded text-center text-muted small bg-light">
                            <i class="bi bi-tools fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            Belum ada tiket pemeliharaan atau perbaikan yang tercatat untuk aset ini.
                        </div>
                    @endif
                </div>

                <!-- 5. TAB PELEPASAN & JURNAL (Jika sudah dilepas) -->
                @if(in_array($asset->lifecycle_status, ['disposed', 'written_off']))
                    <div class="tab-pane fade" id="tab-disposal" role="tabpanel">
                        <div class="alert alert-warning">
                            <h6 class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i>Informasi Pelepasan Aset Tetap</h6>
                            <p class="mb-0 small">Aset ini telah dinyatakan dilepas dari buku aset operasional kos.</p>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted py-2" style="width: 45%;">Tanggal Pelepasan</td>
                                            <td class="fw-bold py-2">{{ $asset->disposal_date?->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">Hasil Penjualan Kas</td>
                                            <td class="fw-bold text-success py-2">Rp {{ number_format($asset->disposal_price ?? 0, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">Biaya Pelepasan / Angkut</td>
                                            <td class="text-danger py-2">Rp {{ number_format($asset->disposal_cost ?? 0, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-2">Catatan Pelepasan</td>
                                            <td class="py-2">{{ $asset->disposal_notes ?: '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                @if($asset->disposalJournal)
                                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Jurnal Akuntansi Pelepasan</h6>
                                    <div class="p-3 bg-light rounded border">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="small fw-bold">Nomor Jurnal:</span>
                                            <span class="font-monospace fw-bold text-primary">{{ $asset->disposalJournal->journal_number }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="small text-muted">Total Nilai Transaksi:</span>
                                            <span class="fw-bold">Rp {{ number_format($asset->disposalJournal->total_amount, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="small text-muted">Status Jurnal:</span>
                                            <span class="badge bg-success">Diposting</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal Mutasi Lokasi -->
<div class="modal fade" id="transferLocationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('dashboard.assets.transfer-location', $asset->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Mutasi Lokasi: {{ $asset->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Lokasi Tujuan <span class="text-danger">*</span></label>
                        <select name="to_location_type" id="modalToLocType" class="form-select form-select-sm" required>
                            <option value="room">Kamar Penyewa</option>
                            <option value="common_area">Area Umum / Lobi</option>
                            <option value="warehouse">Gudang Penyimpanan</option>
                            <option value="building">Struktur Gedung</option>
                        </select>
                    </div>

                    <div class="mb-3" id="modalRoomTargetDiv">
                        <label class="form-label small fw-bold">Pilih Kamar Tujuan</label>
                        <select name="to_room_id" class="form-select form-select-sm">
                            <option value="">-- Tanpa Kamar --</option>
                            @foreach($rooms as $rm)
                                <option value="{{ $rm->id }}" {{ $asset->room_id == $rm->id ? 'selected' : '' }}>
                                    Kamar {{ $rm->room_number }} (Lantai {{ $rm->floor }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Keterangan Lokasi Baru</label>
                        <input type="text" name="to_location_name" class="form-control form-control-sm" placeholder="Contoh: Meja Resepsionis, Koridor Lt 2" value="{{ $asset->location_name }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alasan Pemindahan <span class="text-danger">*</span></label>
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Contoh: Pemindahan inventaris kamar 101 ke kamar 102" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary-teal">Simpan Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pelepasan Aset -->
<div class="modal fade" id="disposalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('dashboard.assets.dispose', $asset->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-danger">Pelepasan / Penjualan Aset Tetap</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Pelepasan aset akan menghentikan jadwal depresiasi mendatang dan dapat memposting jurnal penyesuaian laba/rugi pelepasan aset secara otomatis ke buku besar keuangan.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tanggal Pelepasan <span class="text-danger">*</span></label>
                        <input type="date" name="disposal_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Harga Penjualan Kas (Rp)</label>
                            <input type="number" name="disposal_price" class="form-control form-control-sm" placeholder="0 jika dibuang/rusak" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary">Biaya Pelepasan (Rp)</label>
                            <input type="number" name="disposal_cost" class="form-control form-control-sm" placeholder="0" value="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alasan / Catatan Pelepasan <span class="text-danger">*</span></label>
                        <textarea name="disposal_notes" class="form-control form-control-sm" rows="2" placeholder="Rusak permanen tidak dapat diperbaiki / dijual ke pihak ketiga..." required></textarea>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="post_journal" id="disposalPostJournalCheck" value="1" checked>
                        <label class="form-check-label small fw-semibold text-dark" for="disposalPostJournalCheck">
                            Posting Jurnal Penyesuaian Laba / Rugi Otomatis
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger">Konfirmasi Pelepasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RIGHT-SIDE DRAWER: Edit Aset -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="editAssetDrawer" aria-labelledby="editAssetDrawerLabel" style="width: 540px; max-width: 95vw;">
    <div class="offcanvas-header border-bottom bg-light">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary-teal text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                <i class="bi bi-pencil-fill"></i>
            </div>
            <h5 class="offcanvas-title fw-bold text-dark fs-6" id="editAssetDrawerLabel">Edit Data Aset {{ $asset->asset_code }}</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form action="{{ route('dashboard.assets.update', $asset->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Nama Aset <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $asset->name) }}" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Kategori</label>
                    <select name="category" class="form-select form-select-sm" required>
                        <option value="electronics" {{ $asset->category === 'electronics' ? 'selected' : '' }}>Elektronik</option>
                        <option value="furniture" {{ $asset->category === 'furniture' ? 'selected' : '' }}>Furnitur</option>
                        <option value="building_infrastructure" {{ $asset->category === 'building_infrastructure' ? 'selected' : '' }}>Bangunan</option>
                        <option value="vehicles" {{ $asset->category === 'vehicles' ? 'selected' : '' }}>Kendaraan</option>
                        <option value="operational_equipment" {{ $asset->category === 'operational_equipment' ? 'selected' : '' }}>Peralatan</option>
                        <option value="other" {{ $asset->category === 'other' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Klasifikasi</label>
                    <select name="classification" class="form-select form-select-sm" required>
                        <option value="fixed_asset" {{ $asset->classification === 'fixed_asset' ? 'selected' : '' }}>Aset Tetap</option>
                        <option value="low_value_inventory" {{ $asset->classification === 'low_value_inventory' ? 'selected' : '' }}>Inventaris Rendah</option>
                        <option value="consumable" {{ $asset->classification === 'consumable' ? 'selected' : '' }}>Habis Pakai</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Kondisi</label>
                    <select name="condition" class="form-select form-select-sm">
                        <option value="good" {{ $asset->condition === 'good' ? 'selected' : '' }}>Baik</option>
                        <option value="slightly_damaged" {{ $asset->condition === 'slightly_damaged' ? 'selected' : '' }}>Rusak Ringan</option>
                        <option value="broken" {{ $asset->condition === 'broken' ? 'selected' : '' }}>Rusak Berat</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Status Operasional</label>
                    <select name="lifecycle_status" class="form-select form-select-sm">
                        <option value="active" {{ $asset->lifecycle_status === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="in_maintenance" {{ $asset->lifecycle_status === 'in_maintenance' ? 'selected' : '' }}>Dalam Perawatan</option>
                        <option value="inactive" {{ $asset->lifecycle_status === 'inactive' ? 'selected' : '' }}>Tidak Aktif</option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Masa Manfaat (Bulan)</label>
                    <input type="number" name="useful_life_months" class="form-control form-control-sm" value="{{ old('useful_life_months', $asset->useful_life_months) }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Nilai Residu (Rp)</label>
                    <input type="number" name="salvage_value" class="form-control form-control-sm" value="{{ old('salvage_value', $asset->salvage_value) }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Nomor Seri</label>
                    <input type="text" name="serial_number" class="form-control form-control-sm" value="{{ old('serial_number', $asset->serial_number) }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Garansi Berakhir</label>
                    <input type="date" name="warranty_expiry_date" class="form-control form-control-sm" value="{{ old('warranty_expiry_date', $asset->warranty_expiry_date?->format('Y-m-d')) }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Catatan</label>
                <textarea name="notes" class="form-control form-control-sm" rows="2">{{ old('notes', $asset->notes) }}</textarea>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary-teal py-2 fw-semibold">
                    <i class="bi bi-save me-1"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-light py-2" data-bs-dismiss="offcanvas">Batal</button>
            </div>
        </form>
    </div>
</div>
@endsection
