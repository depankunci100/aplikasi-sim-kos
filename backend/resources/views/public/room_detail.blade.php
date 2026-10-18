@extends('layouts.public')

@section('title', 'Kamar ' . $room->room_number . ' — ' . $room->property->name)

@section('content')
<div class="container">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('public.catalog') }}" class="text-teal text-decoration-none">Katalog</a></li>
            <li class="breadcrumb-item text-muted">{{ $room->property->name }}</li>
            <li class="breadcrumb-item active" aria-current="page">Kamar {{ $room->room_number }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Kolom Kiri: Informasi Kamar & Properti -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="bg-light d-flex align-items-center justify-content-center position-relative" style="height: 280px; background: linear-gradient(135deg, #0d9488 0%, #115e59 100%);">
                    <div class="text-center text-white">
                        <i class="bi bi-door-open fs-1 opacity-75"></i>
                        <h2 class="fw-bold mt-2 mb-0">Kamar {{ $room->room_number }}</h2>
                        <span class="badge bg-white bg-opacity-25 mt-1">{{ $room->roomType->name }} &bull; Lantai {{ $room->floor }}</span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">{{ $room->property->name }}</span>
                            <h3 class="fw-bold text-dark mb-1">{{ $room->roomType->name }} — Kamar {{ $room->room_number }}</h3>
                            <p class="text-muted mb-0 small">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> {{ $room->property->address }}, {{ $room->property->city }}
                            </p>
                        </div>
                        @if($isAvailable)
                            <span class="badge badge-available px-3 py-2 fs-6">
                                <i class="bi bi-check-circle me-1"></i> Tersedia
                            </span>
                        @else
                            <span class="badge bg-danger px-3 py-2 fs-6">
                                <i class="bi bi-x-circle me-1"></i> Tidak Tersedia pada Tanggal Ini
                            </span>
                        @endif
                    </div>

                    <div class="row g-3 py-3 border-top border-bottom my-3">
                        <div class="col-sm-4">
                            <span class="text-muted small d-block">Luas Kamar</span>
                            <span class="fw-bold text-dark">{{ $room->roomType->size_m2 ? $room->roomType->size_m2 . ' m²' : 'Standar' }}</span>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small d-block">Kapasitas Maksimal</span>
                            <span class="fw-bold text-dark">{{ $room->roomType->max_occupants }} Orang</span>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small d-block">Tipe Bangunan</span>
                            <span class="fw-bold text-dark text-capitalize">{{ $room->property->type }}</span>
                        </div>
                    </div>

                    <!-- Deskripsi Tipe Kamar -->
                    <div class="mb-4">
                        <h5 class="fw-bold text-dark mb-2">Deskripsi Kamar</h5>
                        <p class="text-secondary mb-0">
                            {{ $room->roomType->description ?: 'Kamar nyaman dan bersih yang dirancang untuk mendukung kenyamanan istirahat dan aktivitas harian Anda. Lokasi strategis dekat fasilitas umum.' }}
                        </p>
                    </div>

                    <!-- Fasilitas Kamar -->
                    <div class="mb-4">
                        <h5 class="fw-bold text-dark mb-2">Fasilitas Kamar & Bersama</h5>
                        <div class="row g-2">
                            @if(is_array($room->roomType->facilities) && count($room->roomType->facilities) > 0)
                                @foreach($room->roomType->facilities as $facility)
                                <div class="col-md-4 col-sm-6">
                                    <div class="d-flex align-items-center gap-2 p-2 bg-light rounded-3">
                                        <i class="bi bi-check2-circle text-teal"></i>
                                        <span class="small fw-medium">{{ $facility }}</span>
                                    </div>
                                </div>
                                @endforeach
                            @else
                                <div class="col-12 text-muted small">Fasilitas standar kamar kos/guesthouse.</div>
                            @endif
                        </div>
                    </div>

                    <!-- Aturan Properti -->
                    <div>
                        <h5 class="fw-bold text-dark mb-2">Peraturan & Kebijakan Properti</h5>
                        <div class="p-3 bg-light rounded-3 small text-secondary">
                            {!! nl2br(e($room->property->rules ?: "1. Menjaga ketertiban dan ketenangan lingkungan kos.\n2. Tamu menginap wajib melapor kepada pengelola.\n3. Pembayaran sewa dilakukan tepat waktu sesuai jatuh tempo invoice.")) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Pemilihan Tanggal & Estimasi Biaya Server-Side -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 85px;">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Cek Ketersediaan & Biaya</h5>
                    <p class="text-muted small mb-0">Estimasi biaya resmi dihitung langsung dari server.</p>
                </div>

                <div class="card-body p-4">
                    <form action="{{ route('public.rooms.show', $room->id) }}" method="GET" id="dateCheckForm">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Model Sewa</label>
                            <select name="rental_type" class="form-select form-select-sm" onchange="document.getElementById('dateCheckForm').submit();">
                                <option value="monthly" {{ $rentalType == 'monthly' ? 'selected' : '' }}>Bulanan (Kos)</option>
                                <option value="daily" {{ $rentalType == 'daily' ? 'selected' : '' }}>Harian (Guesthouse)</option>
                                <option value="weekly" {{ $rentalType == 'weekly' ? 'selected' : '' }}>Mingguan</option>
                                <option value="yearly" {{ $rentalType == 'yearly' ? 'selected' : '' }}>Tahunan</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Tanggal Masuk</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" min="{{ date('Y-m-d') }}" onchange="document.getElementById('dateCheckForm').submit();" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Tanggal Keluar</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" min="{{ date('Y-m-d') }}" onchange="document.getElementById('dateCheckForm').submit();" required>
                        </div>
                    </form>

                    <!-- Rincian Biaya Real-time -->
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Durasi Sewa:</span>
                            <span class="fw-semibold">{{ $pricing['days'] }} Hari</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Biaya Sewa:</span>
                            <span class="fw-semibold">Rp {{ number_format($pricing['rate_amount'], 0, ',', '.') }}</span>
                        </div>
                        @if($pricing['deposit_amount'] > 0)
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Uang Jaminan (Deposit):</span>
                            <span class="fw-semibold">Rp {{ number_format($pricing['deposit_amount'], 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">Total Biaya:</span>
                            <span class="fw-bold fs-5 text-teal">Rp {{ number_format($pricing['total_amount'], 0, ',', '.') }}</span>
                        </div>

                        @if($pricing['dp_required'])
                        <div class="mt-2 pt-2 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-semibold text-primary"><i class="bi bi-clock me-1"></i> Uang Muka (DP):</span>
                                <span class="fw-bold text-primary">Rp {{ number_format($pricing['down_payment_amount'], 0, ',', '.') }}</span>
                            </div>
                            <span class="text-muted very-small d-block mt-1" style="font-size: 0.75rem;">
                                Wajib dibayarkan maks {{ $pricing['payment_deadline_hours'] }} jam setelah pengajuan booking.
                            </span>
                        </div>
                        @endif
                    </div>

                    @if($isAvailable)
                        <a href="{{ route('public.booking.create', ['room_id' => $room->id, 'start_date' => $startDate, 'end_date' => $endDate, 'rental_type' => $rentalType]) }}" class="btn btn-teal w-100 py-2 fw-semibold">
                            <i class="bi bi-calendar-plus me-1"></i> Lanjutkan Pemesanan
                        </a>
                    @else
                        <button class="btn btn-secondary w-100 py-2 fw-semibold" disabled>
                            <i class="bi bi-x-circle me-1"></i> Kamar Tidak Tersedia
                        </button>
                        <p class="text-danger small text-center mt-2 mb-0">Silakan pilih tanggal lain yang belum dipesan.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
