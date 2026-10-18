@extends('layouts.app')

@section('title', 'Ringkasan Dashboard')

@section('content')
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h3 class="fw-bold text-dark mb-1">Ringkasan Operasional</h3>
        <p class="text-muted mb-0">
            Selamat datang, <strong class="text-dark">{{ $user->name }}</strong>. Anda bertindak sebagai 
            <span class="badge bg-primary-teal text-white">{{ $user->role->label() }}</span>
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        @if(count($propertiesList) > 1)
        <form method="GET" action="{{ route('dashboard.index') }}" class="d-inline-flex gap-2">
            <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Semua Properti Cabang --</option>
                @foreach($propertiesList as $p)
                    <option value="{{ $p->id }}" {{ $propertyFilter == $p->id ? 'selected' : '' }}>{{ $p->name }} ({{ $p->code }})</option>
                @endforeach
            </select>
        </form>
        @else
            <span class="badge bg-light text-dark border p-2">
                <i class="bi bi-geo-alt-fill text-danger me-1"></i> {{ $propertiesList->first()->name ?? 'Cabang Utama' }}
            </span>
        @endif
    </div>
</div>

<!-- KPI Metrics Cards Row -->
<div class="row g-3 mb-4">
    <!-- Pendapatan Terverifikasi -->
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 p-3 bg-white">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Pendapatan Masuk</span>
                    <h4 class="fw-bold my-1 text-success">Rp {{ number_format($kpi['total_revenue_verified'], 0, ',', '.') }}</h4>
                    <span class="small text-muted"><i class="bi bi-shield-check text-success"></i> Terverifikasi Sah</span>
                </div>
                <div class="p-2 bg-success-subtle text-success rounded-3">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Antrean Verifikasi Kasir -->
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 p-3 bg-white border-warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Menunggu Verifikasi</span>
                    <h4 class="fw-bold my-1 text-warning">{{ $kpi['pending_payments_count'] }} Transaksi</h4>
                    <a href="{{ route('dashboard.payments.index') }}" class="small text-decoration-none fw-semibold text-warning">
                        Buka Antrean Kasir <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="p-2 bg-warning-subtle text-warning rounded-3">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tagihan Belum Lunas -->
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 p-3 bg-white">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Piutang / Unpaid</span>
                    <h4 class="fw-bold my-1 text-danger">Rp {{ number_format($kpi['total_unpaid_amount'], 0, ',', '.') }}</h4>
                    <span class="small text-muted">{{ $kpi['unpaid_invoices_count'] }} Tagihan Aktif</span>
                </div>
                <div class="p-2 bg-danger-subtle text-danger rounded-3">
                    <i class="bi bi-receipt-cutoff fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Kamar Tersedia -->
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 p-3 bg-white">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Kamar Siap Huni</span>
                    <h4 class="fw-bold my-1 text-primary-teal">{{ $roomStats['available'] }} / {{ $roomStats['total'] }}</h4>
                    <span class="small text-muted">{{ $roomStats['occupied'] }} Terisi • {{ $roomStats['dirty'] }} Perlu Bersih</span>
                </div>
                <div class="p-2 bg-teal-subtle text-primary-teal rounded-3" style="background-color: #f0fdfa;">
                    <i class="bi bi-door-open fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section: Kalender Okupansi & Ketersediaan Kamar (Slot, Penuh, Akan Checkout) -->
@include('dashboard.partials.occupancy_calendar')

<!-- Section: Antrean Verifikasi Pembayaran (Fokus Kasir & Owner) -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-shield-exclamation text-warning me-2"></i>Antrean Verifikasi Pembayaran Masuk
        </h5>
        <a href="{{ route('dashboard.payments.index') }}" class="btn btn-sm btn-outline-secondary">
            Lihat Semua Antrean <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>Kode / No. Invoice</th>
                    <th>Penyewa</th>
                    <th>Nominal Transfer</th>
                    <th>Metode & Bank</th>
                    <th>Waktu Masuk</th>
                    <th>Bukti</th>
                    <th class="text-end">Aksi Verifikasi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingPayments as $p)
                <tr>
                    <td>
                        <span class="fw-bold text-dark">{{ $p->payment_code }}</span><br>
                        <span class="small text-muted">{{ $p->invoice->invoice_number ?? '-' }}</span>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark">{{ $p->user->name ?? '-' }}</span><br>
                        <span class="small text-muted">{{ $p->user->phone ?? '-' }}</span>
                    </td>
                    <td>
                        <strong class="text-success fs-6">Rp {{ number_format($p->amount, 0, ',', '.') }}</strong>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ strtoupper($p->payment_method) }}</span>
                        @if($p->sender_bank)
                            <span class="small text-muted d-block">{{ $p->sender_bank }} a.n {{ $p->sender_name }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="small text-muted">{{ $p->created_at->format('d M Y, H:i') }} WIB</span>
                    </td>
                    <td>
                        @if($p->proof_photo)
                            <a href="{{ route('dashboard.payments.proof', $p->id) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-image"></i> Buka Foto
                            </a>
                        @else
                            <span class="badge bg-secondary">Tanpa Foto</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('dashboard.payments.show', $p->id) }}" class="btn btn-sm btn-primary-teal">
                            <i class="bi bi-check2-circle"></i> Periksa & Tindak
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-check2-all fs-3 text-success d-block mb-1"></i>
                        Tidak ada pembayaran yang menunggu verifikasi saat ini. Semua beres!
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Kamar & Housekeeping Status (Fokus Staf & Manager) -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-door-closed-fill text-primary-teal me-2"></i>Status Kesiapan & Kebersihan Kamar
        </h5>
        <span class="small text-muted">Total {{ $roomStats['total'] }} Kamar</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>No. Kamar</th>
                    <th>Lantai</th>
                    <th>Tipe Kamar</th>
                    <th>Status Saat Ini</th>
                    <th class="text-end">Ubah Status Cepat (Housekeeping)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rooms as $r)
                <tr>
                    <td><strong class="fs-6 text-dark">{{ $r->room_number }}</strong></td>
                    <td>Lantai {{ $r->floor }}</td>
                    <td>{{ $r->roomType->name ?? '-' }}</td>
                    <td>
                        @if($r->status->value === 'available')
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Tersedia (Siap)</span>
                        @elseif($r->status->value === 'occupied')
                            <span class="badge bg-primary"><i class="bi bi-person-fill"></i> Terisi</span>
                        @elseif($r->status->value === 'dirty')
                            <span class="badge bg-warning text-dark"><i class="bi bi-stars"></i> Kotor (Perlu Dibersihkan)</span>
                        @else
                            <span class="badge bg-danger"><i class="bi bi-tools"></i> Dalam Perbaikan</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form action="{{ route('dashboard.rooms.status', $r->id) }}" method="POST" class="d-inline-flex gap-2 justify-content-end">
                            @csrf
                            <select name="status" class="form-select form-select-sm" style="width: auto;">
                                <option value="available" {{ $r->status->value === 'available' ? 'selected' : '' }}>Tersedia</option>
                                <option value="dirty" {{ $r->status->value === 'dirty' ? 'selected' : '' }}>Kotor</option>
                                <option value="maintenance" {{ $r->status->value === 'maintenance' ? 'selected' : '' }}>Perbaikan</option>
                            </select>
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Simpan</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-3 text-muted">Belum ada data kamar terdaftar.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rooms->hasPages())
    <div class="card-footer bg-white py-2">
        {{ $rooms->links() }}
    </div>
    @endif
</div>
@endsection
