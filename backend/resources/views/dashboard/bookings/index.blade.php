@extends('layouts.app')

@section('title', 'Manajemen Pemesanan (Booking)')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header Page & CTA Tambah Booking -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold text-dark mb-1">Manajemen Pemesanan Kamar</h4>
            <p class="text-muted small mb-0">Kelola reservasi, verifikasi uang muka, dan alur check-in calon penyewa.</p>
        </div>
        <div>
            @if(auth()->user()->hasRole('super_admin', 'owner', 'manager', 'receptionist'))
            <button type="button" class="btn btn-teal shadow-sm d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#createBookingDrawer" aria-controls="createBookingDrawer">
                <i class="bi bi-calendar-plus"></i>
                <span>Buat Pemesanan Baru</span>
            </button>
            @endif
        </div>
    </div>

    <!-- 5 KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Total Pemesanan</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1">{{ number_format($kpis['total']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-warning border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-warning">Menunggu / Pending</span>
                    <h3 class="fw-bold text-warning mb-0 mt-1">{{ number_format($kpis['pending']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-info border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-info">Terkonfirmasi (Siap Masuk)</span>
                    <h3 class="fw-bold text-info mb-0 mt-1">{{ number_format($kpis['confirmed']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-success border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-success">Aktif (Ditempati)</span>
                    <h3 class="fw-bold text-success mb-0 mt-1">{{ number_format($kpis['active']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 kpi-card h-100 border-start border-secondary border-4">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold text-secondary">Selesai (Check-Out)</span>
                    <h3 class="fw-bold text-secondary mb-0 mt-1">{{ number_format($kpis['completed']) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('dashboard.bookings.index') }}" method="GET">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-semibold mb-1">Status Pemesanan</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Semua Status</option>
                            @foreach(\App\Enums\BookingStatus::cases() as $st)
                                <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                                    {{ $st->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-semibold mb-1">Properti</label>
                        <select name="property_id" class="form-select form-select-sm">
                            <option value="">Semua Properti</option>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}" {{ request('property_id') == $prop->id ? 'selected' : '' }}>
                                    {{ $prop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label text-muted small fw-semibold mb-1">Model Sewa</label>
                        <select name="rental_type" class="form-select form-select-sm">
                            <option value="">Semua Tipe</option>
                            <option value="monthly" {{ request('rental_type') === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                            <option value="daily" {{ request('rental_type') === 'daily' ? 'selected' : '' }}>Harian</option>
                            <option value="weekly" {{ request('rental_type') === 'weekly' ? 'selected' : '' }}>Mingguan</option>
                            <option value="yearly" {{ request('rental_type') === 'yearly' ? 'selected' : '' }}>Tahunan</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-semibold mb-1">Pencarian</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Kode booking, nama, kamar..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-teal w-100" title="Terapkan Filter">
                            <i class="bi bi-funnel"></i>
                        </button>
                        <a href="{{ route('dashboard.bookings.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small">
                        <tr>
                            <th class="ps-3 py-3">Kode Booking</th>
                            <th>Penyewa / Calon Tamu</th>
                            <th>Properti & Kamar</th>
                            <th>Periode Sewa</th>
                            <th>Total & Uang Muka</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $item)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('dashboard.bookings.show', $item->id) }}" class="fw-bold text-teal text-decoration-none">
                                    {{ $item->booking_code }}
                                </a>
                                <small class="text-muted d-block">{{ $item->created_at->isoFormat('D MMM Y, HH:mm') }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $item->customer_name }}</span>
                                <div class="text-muted small">
                                    {{ $item->customer_phone ?: '-' }}
                                    @if($item->isGuest())
                                        <span class="badge bg-secondary-subtle text-secondary small py-0">Guest</span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary small py-0">Penyewa</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $item->property?->name ?? 'Properti' }}</div>
                                <small class="text-muted">Kamar {{ $item->room?->room_number ?? '-' }} ({{ $item->room?->roomType?->name ?? '-' }})</small>
                            </td>
                            <td>
                                <div>{{ $item->start_date->isoFormat('D MMM Y') }} &ndash; {{ $item->end_date->isoFormat('D MMM Y') }}</div>
                                <small class="text-muted text-capitalize">{{ $item->rental_type->value }}</small>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">Rp {{ number_format($item->total_amount, 0, ',', '.') }}</div>
                                @if($item->down_payment_amount > 0)
                                    <small class="text-primary fw-semibold">DP: Rp {{ number_format($item->down_payment_amount, 0, ',', '.') }}</small>
                                @else
                                    <small class="text-muted">Tanpa DP</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $item->status->badgeClass() }} px-2 py-1">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <td class="pe-3 text-end">
                                <a href="{{ route('dashboard.bookings.show', $item->id) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye me-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                Belum ada data pemesanan kamar yang sesuai kriteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($bookings->hasPages())
        <div class="card-footer bg-white border-0 py-3">
            {{ $bookings->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Right-Side Drawer: Buat Pemesanan Baru (drawer-lg) -->
<div class="offcanvas offcanvas-end drawer-lg" tabindex="-1" id="createBookingDrawer" aria-labelledby="createBookingDrawerLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="createBookingDrawerLabel">
            <i class="bi bi-calendar-plus text-teal me-2"></i> Buat Pemesanan Baru
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <form action="{{ route('dashboard.bookings.store') }}" method="POST" id="drawerBookingForm">
        @csrf
        <div class="offcanvas-body">
            <!-- Tipe Pelanggan: Tenant Terdaftar vs Guest Baru -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-muted">Tipe Pelanggan</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="customer_type" id="typeGuest" value="guest" checked onchange="toggleCustomerType()">
                    <label class="btn btn-outline-secondary btn-sm" for="typeGuest">Tamu / Guest Baru</label>

                    <input type="radio" class="btn-check" name="customer_type" id="typeTenant" value="tenant" onchange="toggleCustomerType()">
                    <label class="btn btn-outline-secondary btn-sm" for="typeTenant">Penyewa Terdaftar</label>
                </div>
            </div>

            <!-- Bagian Guest -->
            <div id="guestFields">
                <div class="mb-3">
                    <label for="drawer_guest_name" class="form-label small fw-semibold text-muted">Nama Tamu <span class="text-danger">*</span></label>
                    <input type="text" name="guest_name" id="drawer_guest_name" class="form-control form-control-sm" placeholder="Nama lengkap">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="drawer_guest_phone" class="form-label small fw-semibold text-muted">No. HP / WA <span class="text-danger">*</span></label>
                        <input type="tel" name="guest_phone" id="drawer_guest_phone" class="form-control form-control-sm" placeholder="0812...">
                    </div>
                    <div class="col-6">
                        <label for="drawer_guest_email" class="form-label small fw-semibold text-muted">Email <span class="text-danger">*</span></label>
                        <input type="email" name="guest_email" id="drawer_guest_email" class="form-control form-control-sm" placeholder="tamu@mail.com">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="drawer_guest_id_card" class="form-label small fw-semibold text-muted">No. KTP / NIK (Opsional)</label>
                    <input type="text" name="guest_id_card" id="drawer_guest_id_card" class="form-control form-control-sm" placeholder="16 digit NIK">
                </div>
            </div>

            <!-- Bagian Tenant Terdaftar -->
            <div id="tenantFields" style="display: none;">
                <div class="mb-3">
                    <label for="drawer_user_id" class="form-label small fw-semibold text-muted">Pilih Akun Penyewa <span class="text-danger">*</span></label>
                    <select name="user_id" id="drawer_user_id" class="form-select form-select-sm">
                        <option value="">-- Pilih Penyewa --</option>
                        @foreach($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->email }} &bull; {{ $t->phone }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <hr class="my-3">

            <!-- Pilih Kamar & Model Sewa -->
            <div class="mb-3">
                <label for="drawer_room_id" class="form-label small fw-semibold text-muted">Pilih Properti & Kamar <span class="text-danger">*</span></label>
                <select name="room_id" id="drawer_room_id" class="form-select form-select-sm" required>
                    <option value="">-- Pilih Kamar --</option>
                    @foreach($properties as $p)
                        <optgroup label="{{ $p->name }} ({{ $p->city }})">
                            @foreach($p->rooms->where('is_active', true) as $r)
                                <option value="{{ $r->id }}">
                                    Kamar {{ $r->room_number }} &bull; {{ $r->roomType->name }} (Lt {{ $r->floor }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="drawer_rental_type" class="form-label small fw-semibold text-muted">Model Sewa <span class="text-danger">*</span></label>
                <select name="rental_type" id="drawer_rental_type" class="form-select form-select-sm" required>
                    <option value="monthly">Bulanan (Kos)</option>
                    <option value="daily">Harian (Guesthouse)</option>
                    <option value="weekly">Mingguan</option>
                    <option value="yearly">Tahunan</option>
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label for="drawer_start_date" class="form-label small fw-semibold text-muted">Tanggal Masuk <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" id="drawer_start_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" min="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-6">
                    <label for="drawer_end_date" class="form-label small fw-semibold text-muted">Tanggal Keluar <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" id="drawer_end_date" class="form-control form-control-sm" value="{{ date('Y-m-d', strtotime('+1 month')) }}" min="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="drawer_notes" class="form-label small fw-semibold text-muted">Catatan Operasional (Opsional)</label>
                <textarea name="notes" id="drawer_notes" rows="2" class="form-control form-control-sm" placeholder="Catatan internal..."></textarea>
            </div>
        </div>

        <div class="offcanvas-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-sm btn-teal px-3">
                <i class="bi bi-check-lg me-1"></i> Simpan Pemesanan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function toggleCustomerType() {
        var isTenant = document.getElementById('typeTenant').checked;
        document.getElementById('tenantFields').style.display = isTenant ? 'block' : 'none';
        document.getElementById('guestFields').style.display = isTenant ? 'none' : 'block';

        if (isTenant) {
            document.getElementById('drawer_guest_name').removeAttribute('required');
            document.getElementById('drawer_guest_phone').removeAttribute('required');
            document.getElementById('drawer_guest_email').removeAttribute('required');
            document.getElementById('drawer_user_id').setAttribute('required', 'required');
        } else {
            document.getElementById('drawer_guest_name').setAttribute('required', 'required');
            document.getElementById('drawer_guest_phone').setAttribute('required', 'required');
            document.getElementById('drawer_guest_email').setAttribute('required', 'required');
            document.getElementById('drawer_user_id').removeAttribute('required');
        }
    }
</script>
@endpush
