@extends('layouts.public')

@section('title', 'Katalog Kamar Kos & Guesthouse')

@section('content')
<div class="container">
    <!-- Hero / Headline -->
    <div class="p-4 p-md-5 mb-4 rounded-4 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #115e59 100%);">
        <div class="col-lg-8">
            <h1 class="display-6 fw-bold mb-2">Temukan Kamar Kos & Guesthouse Nyaman</h1>
            <p class="lead mb-0 text-white-50">
                Pilih kamar idaman Anda dengan harga transparan, fasilitas lengkap, dan proses booking mudah secara online.
            </p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="{{ route('public.catalog') }}" method="GET">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label text-muted small fw-semibold mb-1">Kota / Lokasi</label>
                        <select name="city" class="form-select form-select-sm">
                            <option value="">Semua Kota</option>
                            @foreach($cities as $city)
                                <option value="{{ $city }}" {{ request('city') == $city ? 'selected' : '' }}>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label class="form-label text-muted small fw-semibold mb-1">Properti</label>
                        <select name="property_id" class="form-select form-select-sm">
                            <option value="">Semua Properti</option>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}" {{ request('property_id') == $prop->id ? 'selected' : '' }}>{{ $prop->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label class="form-label text-muted small fw-semibold mb-1">Tipe Kamar</label>
                        <select name="room_type_id" class="form-select form-select-sm">
                            <option value="">Semua Tipe</option>
                            @foreach($roomTypes as $rt)
                                <option value="{{ $rt->id }}" {{ request('room_type_id') == $rt->id ? 'selected' : '' }}>{{ $rt->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label class="form-label text-muted small fw-semibold mb-1">Harga Maks / Bln</label>
                        <select name="max_price" class="form-select form-select-sm">
                            <option value="">Tanpa Batas</option>
                            <option value="1000000" {{ request('max_price') == '1000000' ? 'selected' : '' }}>< Rp 1 Jt</option>
                            <option value="2000000" {{ request('max_price') == '2000000' ? 'selected' : '' }}>< Rp 2 Jt</option>
                            <option value="3000000" {{ request('max_price') == '3000000' ? 'selected' : '' }}>< Rp 3 Jt</option>
                            <option value="5000000" {{ request('max_price') == '5000000' ? 'selected' : '' }}>< Rp 5 Jt</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-12 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-teal w-100">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <a href="{{ route('public.catalog') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>

                <!-- Toggle Filter Tanggal & Periode -->
                <div class="row g-2 mt-2 pt-2 border-top">
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label text-muted small fw-semibold mb-1">Rencana Tanggal Masuk</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}" min="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label text-muted small fw-semibold mb-1">Rencana Tanggal Keluar</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}" min="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <label class="form-label text-muted small fw-semibold mb-1">Model Sewa</label>
                        <select name="rental_type" class="form-select form-select-sm">
                            <option value="monthly" {{ request('rental_type', 'monthly') == 'monthly' ? 'selected' : '' }}>Bulanan (Kos)</option>
                            <option value="daily" {{ request('rental_type') == 'daily' ? 'selected' : '' }}>Harian (Guesthouse)</option>
                            <option value="weekly" {{ request('rental_type') == 'weekly' ? 'selected' : '' }}>Mingguan</option>
                            <option value="yearly" {{ request('rental_type') == 'yearly' ? 'selected' : '' }}>Tahunan</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Room Cards Grid -->
    <div class="row g-4 mb-4">
        @forelse($rooms as $room)
        <div class="col-lg-4 col-md-6">
            <div class="card card-custom h-100 overflow-hidden d-flex flex-column">
                <!-- Room Card Image / Placeholder Header -->
                <div class="position-relative bg-light d-flex align-items-center justify-content-center" style="height: 180px; background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);">
                    <i class="bi bi-door-open fs-1 text-secondary opacity-50"></i>
                    <div class="position-absolute top-0 end-0 m-2">
                        @if(isset($room->is_available_for_period))
                            @if($room->is_available_for_period)
                                <span class="badge badge-available px-2 py-1"><i class="bi bi-check-circle me-1"></i> Tersedia Periode Ini</span>
                            @else
                                <span class="badge bg-danger px-2 py-1"><i class="bi bi-x-circle me-1"></i> Terisi / Tidak Tersedia</span>
                            @endif
                        @else
                            <span class="badge badge-available px-2 py-1"><i class="bi bi-check2"></i> Siap Dipesan</span>
                        @endif
                    </div>
                    <div class="position-absolute bottom-0 start-0 m-2">
                        <span class="badge bg-dark bg-opacity-75 text-white px-2 py-1">
                            <i class="bi bi-geo-alt me-1"></i> {{ $room->property->city ?? 'Indonesia' }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-3 flex-grow-1 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">{{ $room->property->name }}</span>
                            <h5 class="card-title fw-bold text-dark mb-0">Kamar {{ $room->room_number }}</h5>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary border">Lt. {{ $room->floor }}</span>
                    </div>

                    <div class="text-muted small mb-2">{{ $room->roomType->name }} &bull; {{ $room->roomType->max_occupants }} Orang</div>

                    <!-- Facilities Icons/Badges -->
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        @if(is_array($room->roomType->facilities))
                            @foreach(array_slice($room->roomType->facilities, 0, 3) as $fac)
                                <span class="badge bg-light text-dark border small">{{ $fac }}</span>
                            @endforeach
                            @if(count($room->roomType->facilities) > 3)
                                <span class="badge bg-light text-muted border small">+{{ count($room->roomType->facilities) - 3 }}</span>
                            @endif
                        @endif
                    </div>

                    <!-- Pricing Info -->
                    <div class="mt-auto pt-2 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Sewa Bulanan</span>
                            <span class="fw-bold text-teal">Rp {{ number_format($room->roomType->base_price_monthly, 0, ',', '.') }} <small class="text-muted fw-normal">/bln</small></span>
                        </div>
                        @if($room->roomType->base_price_daily > 0)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Sewa Harian</span>
                            <span class="fw-semibold text-secondary small">Rp {{ number_format($room->roomType->base_price_daily, 0, ',', '.') }} /malam</span>
                        </div>
                        @endif

                        @if($room->property->dp_required)
                            <div class="mb-2">
                                <span class="badge badge-dp small">
                                    <i class="bi bi-info-circle me-1"></i> Uang Muka (DP): 
                                    @if($room->property->dp_type === 'percentage')
                                        {{ $room->property->dp_percentage }}%
                                    @else
                                        Rp {{ number_format($room->property->dp_amount, 0, ',', '.') }}
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="{{ route('public.rooms.show', ['id' => $room->id, 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'rental_type' => request('rental_type', 'monthly')]) }}" class="btn btn-sm btn-outline-secondary w-100">
                                Detail Kamar
                            </a>
                        </div>
                        <div class="col-6">
                            @if(!isset($room->is_available_for_period) || $room->is_available_for_period)
                            <a href="{{ route('public.booking.create', ['room_id' => $room->id, 'start_date' => request('start_date'), 'end_date' => request('end_date'), 'rental_type' => request('rental_type', 'monthly')]) }}" class="btn btn-sm btn-teal w-100">
                                Ajukan Sewa
                            </a>
                            @else
                            <button class="btn btn-sm btn-secondary w-100" disabled>
                                Tidak Tersedia
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <div class="text-muted mb-3">
                <i class="bi bi-search fs-1"></i>
            </div>
            <h5 class="fw-bold">Tidak ada kamar yang cocok dengan kriteria filter</h5>
            <p class="text-muted">Coba ubah tanggal pemesanan, rentang harga, atau pilih properti lain.</p>
            <a href="{{ route('public.catalog') }}" class="btn btn-teal btn-sm">Lihat Semua Kamar</a>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $rooms->links() }}
    </div>
</div>
@endsection
