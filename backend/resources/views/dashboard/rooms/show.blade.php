@extends('layouts.app')

@section('title', 'Detail Kamar ' . $room->room_number . ' - ' . $room->property->name)

@section('content')
<div class="container-fluid px-0">
    <!-- Breadcrumb & Navigasi -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.rooms.index') }}" class="text-decoration-none text-muted">Master Kamar</a></li>
                    <li class="breadcrumb-item"><span class="text-muted">{{ $room->property->name }}</span></li>
                    <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">Kamar {{ $room->room_number }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 fw-bold text-dark mb-0">Kamar {{ $room->room_number }}</h1>
                @php
                    $statusClass = match($room->status->value) {
                        'available' => 'bg-success-subtle text-success border border-success-subtle',
                        'occupied' => 'bg-primary-subtle text-primary border border-primary-subtle',
                        'maintenance' => 'bg-warning-subtle text-warning border border-warning-subtle',
                        'dirty' => 'bg-danger-subtle text-danger border border-danger-subtle',
                        default => 'bg-secondary-subtle text-secondary'
                    };
                @endphp
                <span class="badge {{ $statusClass }} px-3 py-1 fs-6">
                    {{ $room->status->label() }}
                </span>
                @if(!$room->is_active)
                    <span class="badge bg-secondary px-2 py-1">Nonaktif</span>
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal">
                <i class="bi bi-arrow-repeat me-1"></i>Ubah Status
            </button>
            <button type="button" class="btn btn-primary-teal btn-sm shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#editRoomDrawer">
                <i class="bi bi-pencil-square me-1"></i>Edit Data Kamar
            </button>
            <a href="{{ route('dashboard.rooms.index') }}" class="btn btn-light btn-sm">
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

    <!-- Mini Overview Header Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Properti & Cabang</div>
                <div class="fw-bold text-dark fs-6 mt-1">{{ $room->property->name }}</div>
                <div class="small text-muted">Lantai {{ $room->floor }} @if($room->building) (Gedung {{ $room->building }}) @endif</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Tipe Kamar</div>
                <div class="fw-bold text-dark fs-6 mt-1">{{ $room->roomType->name }}</div>
                <div class="small text-muted">Kapasitas: {{ $room->effective_capacity }} Org • {{ $room->effective_size > 0 ? $room->effective_size . ' m²' : '-' }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Tarif Sewa Pokok</div>
                <div class="fw-bold text-success fs-6 mt-1">Rp {{ number_format($room->effective_monthly_price, 0, ',', '.') }}<span class="small text-muted fw-normal">/bln</span></div>
                <div class="small text-muted">
                    @if($room->effective_daily_price > 0) Rp {{ number_format($room->effective_daily_price, 0, ',', '.') }}/hari @else Tarif harian: - @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 bg-white">
                <div class="small text-muted fw-semibold">Aset & Inventaris</div>
                <div class="fw-bold text-dark fs-6 mt-1">{{ $room->assets->count() + $room->fixedAssets->count() }} Item</div>
                <div class="small text-muted"><i class="bi bi-box-seam me-1 text-primary"></i>Tercatat di unit</div>
            </div>
        </div>
    </div>

    <!-- Multi-Tab Navigation -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs card-header-tabs mx-3 mt-2 border-bottom-0" id="roomDetailTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="info-tab" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab">
                        <i class="bi bi-info-circle me-1"></i>Informasi & Spesifikasi
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="facilities-tab" data-bs-toggle="tab" data-bs-target="#tab-facilities" type="button" role="tab">
                        <i class="bi bi-stars me-1"></i>Fasilitas & Foto
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="history-tab" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">
                        <i class="bi bi-clock-history me-1"></i>Riwayat Perubahan ({{ $room->histories->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#tab-bookings" type="button" role="tab">
                        <i class="bi bi-calendar-check me-1"></i>Riwayat Booking ({{ $room->bookings->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="assets-tab" data-bs-toggle="tab" data-bs-target="#tab-assets" type="button" role="tab">
                        <i class="bi bi-box-seam me-1"></i>Aset Terkait ({{ $room->assets->count() + $room->fixedAssets->count() }})
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="roomDetailTabsContent">
                <!-- 1. TAB INFORMASI -->
                <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Spesifikasi Unit</h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-muted py-2" style="width: 40%;">Nomor Kamar</td>
                                        <td class="fw-bold py-2">{{ $room->room_number }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Properti / Cabang</td>
                                        <td class="fw-semibold py-2">{{ $room->property->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Tipe Kamar</td>
                                        <td class="py-2">{{ $room->roomType->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Lantai & Gedung</td>
                                        <td class="py-2">Lantai {{ $room->floor }} @if($room->building) (Gedung {{ $room->building }}) @endif</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Luas Kamar</td>
                                        <td class="py-2">{{ $room->effective_size > 0 ? $room->effective_size . ' m²' : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Kapasitas Maksimal</td>
                                        <td class="py-2">{{ $room->effective_capacity }} Orang</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Status Operasional</td>
                                        <td class="py-2">
                                            @if($room->is_active)
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Tarif & Kebijakan Deposit</h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-muted py-2" style="width: 45%;">Harga Sewa Bulanan</td>
                                        <td class="fw-bold text-success py-2">
                                            Rp {{ number_format($room->effective_monthly_price, 0, ',', '.') }}
                                            @if($room->price_monthly)
                                                <span class="badge bg-info-subtle text-info ms-1">Override</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Harga Sewa Harian</td>
                                        <td class="py-2">
                                            @if($room->effective_daily_price > 0)
                                                Rp {{ number_format($room->effective_daily_price, 0, ',', '.') }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Harga Sewa Tahunan</td>
                                        <td class="py-2">
                                            @if($room->effective_yearly_price > 0)
                                                Rp {{ number_format($room->effective_yearly_price, 0, ',', '.') }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Uang Jaminan (Deposit)</td>
                                        <td class="py-2">
                                            @if($room->effective_deposit > 0)
                                                Rp {{ number_format($room->effective_deposit, 0, ',', '.') }}
                                            @else
                                                <span class="text-muted">Sesuai kebijakan properti</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-2">Down Payment (DP)</td>
                                        <td class="py-2">
                                            @if($room->effective_down_payment > 0)
                                                Rp {{ number_format($room->effective_down_payment, 0, ',', '.') }}
                                            @else
                                                <span class="text-muted">Sesuai kebijakan properti</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            @if($room->internal_notes)
                                <div class="mt-3 p-3 bg-light rounded border">
                                    <div class="small fw-bold text-secondary mb-1"><i class="bi bi-sticky me-1"></i>Catatan Internal Pengelola:</div>
                                    <div class="small text-dark">{{ $room->internal_notes }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 2. TAB FASILITAS & FOTO -->
                <div class="tab-pane fade" id="tab-facilities" role="tabpanel">
                    <h6 class="fw-bold text-dark mb-3">Fasilitas Kamar Tersedia</h6>
                    @php $facilities = $room->effective_facilities; @endphp
                    @if(!empty($facilities))
                        <div class="row g-2 mb-4">
                            @foreach($facilities as $item)
                                <div class="col-6 col-md-4 col-xl-3">
                                    <div class="p-2 border rounded bg-light d-flex align-items-center gap-2 small">
                                        <i class="bi bi-check-circle-fill text-primary-teal"></i>
                                        <span>{{ is_array($item) ? ($item['name'] ?? json_encode($item)) : $item }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small mb-4">Belum ada daftar fasilitas spesifik yang tercatat.</div>
                    @endif

                    <h6 class="fw-bold text-dark mb-3">Galeri Foto Unit</h6>
                    @php $photos = $room->effective_photos; @endphp
                    @if(!empty($photos))
                        <div class="row g-3">
                            @foreach($photos as $photo)
                                <div class="col-6 col-md-3">
                                    <div class="card border rounded overflow-hidden shadow-sm">
                                        <img src="{{ is_array($photo) ? ($photo['url'] ?? '') : $photo }}" class="img-fluid object-fit-cover" style="height: 160px;" alt="Foto Kamar {{ $room->room_number }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 border rounded text-center text-muted small bg-light">
                            <i class="bi bi-images fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            Belum ada foto khusus yang diunggah untuk kamar ini.
                        </div>
                    @endif
                </div>

                <!-- 3. TAB RIWAYAT PERUBAHAN -->
                <div class="tab-pane fade" id="tab-history" role="tabpanel">
                    <h6 class="fw-bold text-dark mb-3">Audit Trail Perubahan Master Kamar</h6>
                    @if($room->histories->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2">Waktu Perubahan</th>
                                        <th class="py-2">Aktor</th>
                                        <th class="py-2">Tindakan</th>
                                        <th class="py-2">Detail Perubahan</th>
                                        <th class="py-2">Catatan / Alasan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($room->histories as $history)
                                        <tr>
                                            <td class="text-muted text-nowrap py-2">{{ $history->created_at->format('d/m/Y H:i:s') }}</td>
                                            <td class="fw-semibold py-2">{{ $history->user?->name ?? 'Sistem' }}</td>
                                            <td class="py-2">
                                                <span class="badge bg-light text-dark border">
                                                    {{ strtoupper($history->action) }}
                                                </span>
                                            </td>
                                            <td class="py-2">
                                                @if($history->new_values)
                                                    <pre class="bg-light p-2 rounded mb-0 text-muted" style="font-size: 0.72rem; max-height: 100px; overflow-y: auto;">{{ json_encode($history->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-muted py-2">{{ $history->notes ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 border rounded text-center text-muted small bg-light">
                            <i class="bi bi-clock-history fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            Belum ada catatan riwayat perubahan pada unit kamar ini.
                        </div>
                    @endif
                </div>

                <!-- 4. TAB RIWAYAT BOOKING -->
                <div class="tab-pane fade" id="tab-bookings" role="tabpanel">
                    <h6 class="fw-bold text-dark mb-3">Daftar Pemesanan Kamar (Terbaru)</h6>
                    @if($room->bookings->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2">Kode Booking</th>
                                        <th class="py-2">Penyewa</th>
                                        <th class="py-2">Periode Sewa</th>
                                        <th class="py-2">Total Biaya</th>
                                        <th class="py-2">Status</th>
                                        <th class="py-2 text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($room->bookings as $booking)
                                        <tr>
                                            <td class="fw-bold py-2">{{ $booking->booking_code }}</td>
                                            <td class="py-2">{{ $booking->user?->name ?? $booking->guest_name ?? 'Tamu' }}</td>
                                            <td class="py-2 text-muted">
                                                {{ $booking->check_in_date?->format('d/m/Y') }} s/d {{ $booking->check_out_date?->format('d/m/Y') }}
                                            </td>
                                            <td class="fw-bold text-dark py-2">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td>
                                            <td class="py-2">
                                                <span class="badge bg-light text-dark border">{{ $booking->status }}</span>
                                            </td>
                                            <td class="text-end py-2">
                                                <a href="{{ route('dashboard.bookings.show', $booking->id) }}" class="btn btn-outline-secondary btn-sm p-1" title="Lihat Booking">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 border rounded text-center text-muted small bg-light">
                            <i class="bi bi-calendar-x fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            Belum ada pemesanan yang tercatat untuk kamar ini.
                        </div>
                    @endif
                </div>

                <!-- 5. TAB ASET TERKAIT -->
                <div class="tab-pane fade" id="tab-assets" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Inventaris & Aset Terpasang</h6>
                        <a href="{{ route('dashboard.assets.index', ['room_id' => $room->id]) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-plus-circle me-1"></i>Daftarkan Aset ke Kamar
                        </a>
                    </div>
                    @php 
                        $totalAssetItems = $room->assets->count() + $room->fixedAssets->count();
                    @endphp
                    @if($totalAssetItems > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2">Kode Aset</th>
                                        <th class="py-2">Nama Barang / Aset</th>
                                        <th class="py-2">Jumlah</th>
                                        <th class="py-2">Kondisi</th>
                                        <th class="py-2">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($room->assets as $asset)
                                        <tr>
                                            <td class="text-muted py-2">-</td>
                                            <td class="fw-semibold py-2">{{ $asset->item_name }}</td>
                                            <td class="py-2">{{ $asset->quantity }}</td>
                                            <td class="py-2"><span class="badge bg-light text-dark border">{{ $asset->condition }}</span></td>
                                            <td class="text-muted py-2">{{ $asset->notes ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                    @foreach($room->fixedAssets as $fAsset)
                                        <tr>
                                            <td class="fw-bold text-primary py-2">{{ $fAsset->asset_code }}</td>
                                            <td class="fw-semibold py-2">
                                                <a href="{{ route('dashboard.assets.show', $fAsset->id) }}" class="text-decoration-none text-dark">
                                                    {{ $fAsset->name }}
                                                </a>
                                            </td>
                                            <td class="py-2">1 Unit</td>
                                            <td class="py-2"><span class="badge bg-light text-dark border">{{ $fAsset->condition }}</span></td>
                                            <td class="text-muted py-2">{{ $fAsset->notes ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 border rounded text-center text-muted small bg-light">
                            <i class="bi bi-box-seam fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            Belum ada inventaris atau aset tetap yang dialokasikan di kamar ini.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ubah Status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('dashboard.rooms.status', $room->id) }}" method="POST">
                @csrf
                <div class="modal-header border-bottom-0 pb-0">
                    <h6 class="modal-title fw-bold">Ubah Status Kamar {{ $room->room_number }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <label class="form-label small fw-semibold text-secondary">Status Baru</label>
                    <select name="status" class="form-select form-select-sm mb-2">
                        <option value="available" {{ $room->status->value === 'available' ? 'selected' : '' }}>Tersedia</option>
                        <option value="occupied" {{ $room->status->value === 'occupied' ? 'selected' : '' }}>Ditempati</option>
                        <option value="maintenance" {{ $room->status->value === 'maintenance' ? 'selected' : '' }}>Dalam Perbaikan</option>
                        <option value="dirty" {{ $room->status->value === 'dirty' ? 'selected' : '' }}>Kotor</option>
                    </select>
                    <label class="form-label small fw-semibold text-secondary">Alasan / Catatan</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Catatan operasional..."></textarea>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary-teal">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RIGHT-SIDE DRAWER: Edit Data Kamar -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="editRoomDrawer" aria-labelledby="editRoomDrawerLabel" style="width: 520px; max-width: 95vw;">
    <div class="offcanvas-header border-bottom bg-light">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary-teal text-white rounded p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                <i class="bi bi-pencil-fill"></i>
            </div>
            <h5 class="offcanvas-title fw-bold text-dark fs-6" id="editRoomDrawerLabel">Edit Master Kamar {{ $room->room_number }}</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-4">
        <form action="{{ route('dashboard.rooms.update', $room->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Tipe Kamar -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Tipe Kamar <span class="text-danger">*</span></label>
                <select name="room_type_id" class="form-select form-select-sm" required>
                    @foreach($roomTypes as $type)
                        <option value="{{ $type->id }}" {{ old('room_type_id', $room->room_type_id) == $type->id ? 'selected' : '' }}>
                            {{ $type->name }} (Rp {{ number_format($type->base_price_monthly, 0, ',', '.') }}/bln)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Nomor Kamar <span class="text-danger">*</span></label>
                    <input type="text" name="room_number" class="form-control form-control-sm" value="{{ old('room_number', $room->room_number) }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark">Lantai <span class="text-danger">*</span></label>
                    <input type="number" name="floor" class="form-control form-control-sm" min="1" max="50" value="{{ old('floor', $room->floor) }}" required>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Gedung / Blok</label>
                    <input type="text" name="building" class="form-control form-control-sm" value="{{ old('building', $room->building) }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Luas Kamar (m²)</label>
                    <input type="number" step="0.1" name="size_m2" class="form-control form-control-sm" value="{{ old('size_m2', $room->size_m2) }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Maks Penghuni (Org)</label>
                    <input type="number" name="capacity" class="form-control form-control-sm" min="1" max="10" value="{{ old('capacity', $room->capacity) }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Status Hunian</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="available" {{ old('status', $room->status->value) === 'available' ? 'selected' : '' }}>Tersedia</option>
                        <option value="occupied" {{ old('status', $room->status->value) === 'occupied' ? 'selected' : '' }}>Ditempati</option>
                        <option value="maintenance" {{ old('status', $room->status->value) === 'maintenance' ? 'selected' : '' }}>Dalam Perbaikan</option>
                        <option value="dirty" {{ old('status', $room->status->value) === 'dirty' ? 'selected' : '' }}>Kotor</option>
                    </select>
                </div>
            </div>

            <hr class="text-muted my-3">

            <div class="fw-bold small text-dark mb-2">Penyesuaian Tarif Sewa Khusus (Opsional)</div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Tarif Bulanan (Rp)</label>
                    <input type="number" name="price_monthly" class="form-control form-control-sm" value="{{ old('price_monthly', $room->price_monthly) }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Tarif Harian (Rp)</label>
                    <input type="number" name="price_daily" class="form-control form-control-sm" value="{{ old('price_daily', $room->price_daily) }}">
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Uang Jaminan / Deposit (Rp)</label>
                    <input type="number" name="deposit_amount" class="form-control form-control-sm" value="{{ old('deposit_amount', $room->deposit_amount) }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-secondary">Down Payment (Rp)</label>
                    <input type="number" name="down_payment_amount" class="form-control form-control-sm" value="{{ old('down_payment_amount', $room->down_payment_amount) }}">
                </div>
            </div>

            <!-- Fasilitas Khusus Unit -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Fasilitas Tambahan Unit</label>
                @php 
                    $facVal = is_array($room->facilities) ? implode(', ', $room->facilities) : $room->facilities;
                @endphp
                <input type="text" name="facilities" class="form-control form-control-sm" value="{{ old('facilities', $facVal) }}" placeholder="Pisahkan dengan koma">
            </div>

            <!-- Catatan Internal -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Catatan Internal</label>
                <textarea name="internal_notes" class="form-control form-control-sm" rows="2">{{ old('internal_notes', $room->internal_notes) }}</textarea>
            </div>

            <!-- Alasan Perubahan -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Alasan Perubahan Data (Audit Trail)</label>
                <input type="text" name="change_reason" class="form-control form-control-sm" placeholder="Contoh: Penyesuaian tarif tahunan, perbaikan fasilitas..." required>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" value="1" {{ $room->is_active ? 'checked' : '' }}>
                <label class="form-check-label small fw-semibold text-dark" for="editIsActive">Kamar Aktif untuk Operasional</label>
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
