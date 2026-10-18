@extends('layouts.app')

@section('title', 'Detail Pemesanan ' . $booking->booking_code)

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.bookings.index') }}" class="text-teal text-decoration-none">Pemesanan</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $booking->booking_code }}</li>
            </ol>
        </nav>
        <a href="{{ route('dashboard.bookings.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    <!-- Header Banner & Action Buttons -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden">
        <div class="card-body p-4 bg-white border-start border-{{ $booking->status->badgeClass() }} border-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0">{{ $booking->booking_code }}</h4>
                        <span class="badge bg-{{ $booking->status->badgeClass() }} fs-6 px-3 py-1">
                            {{ $booking->status->label() }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">
                        Diajukan pada {{ $booking->created_at->isoFormat('D MMMM Y, HH:mm') }} WIB &bull; 
                        Model: <strong class="text-dark text-capitalize">{{ $booking->rental_type->value }}</strong>
                    </p>
                </div>

                <!-- Action Buttons Toolbar -->
                <div class="d-flex flex-wrap gap-2">
                    @if(auth()->user()->hasRole('super_admin', 'owner', 'manager', 'receptionist'))
                        @if($booking->canConfirm())
                            <button type="button" class="btn btn-sm btn-teal" data-bs-toggle="modal" data-bs-target="#confirmBookingModal">
                                <i class="bi bi-check2-circle me-1"></i> Konfirmasi Booking
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectBookingModal">
                                <i class="bi bi-x-circle me-1"></i> Tolak
                            </button>
                        @endif

                        @if($booking->canCheckIn())
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#checkInModal">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Proses Check-In
                            </button>
                        @endif

                        @if($booking->canCheckOut())
                            @php
                                $activeSettlement = $booking->settlements()->whereNotIn('status', [\App\Enums\SettlementStatus::CANCELLED])->first();
                            @endphp
                            @if($activeSettlement)
                                <a href="{{ route('dashboard.deposits.settlements.show', $activeSettlement->id) }}" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-receipt me-1"></i> Lihat Settlement Check-Out
                                </a>
                            @else
                                <a href="{{ route('dashboard.deposits.settlements.index', ['booking_id' => $booking->id]) }}" class="btn btn-sm btn-warning text-dark fw-semibold">
                                    <i class="bi bi-box-arrow-right me-1"></i> Proses Check-Out
                                </a>
                            @endif
                        @endif

                        @if($booking->canCancel())
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cancelBookingModal">
                                <i class="bi bi-slash-circle me-1"></i> Batalkan
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Data Penyewa & Properti -->
        <div class="col-lg-6">
            <!-- Card Penyewa / Tamu -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person text-teal me-2"></i> Data Calon Penghuni</h6>
                    @if($booking->isGuest())
                        <span class="badge bg-secondary-subtle text-secondary">Tamu Publik (Guest)</span>
                    @else
                        <span class="badge bg-primary-subtle text-primary">Penyewa Terdaftar</span>
                    @endif
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted small" style="width: 35%;">Nama Lengkap</td>
                            <td class="fw-semibold text-dark">{{ $booking->customer_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">No. Telepon / WA</td>
                            <td class="fw-semibold text-dark">
                                @if($booking->customer_phone)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $booking->customer_phone) }}" target="_blank" class="text-teal text-decoration-none">
                                        <i class="bi bi-whatsapp me-1"></i> {{ $booking->customer_phone }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Email</td>
                            <td class="text-dark">{{ $booking->customer_email ?: '-' }}</td>
                        </tr>
                        @if($booking->guest_id_card)
                        <tr>
                            <td class="text-muted small">No. KTP / NIK</td>
                            <td class="text-dark">{{ $booking->guest_id_card }}</td>
                        </tr>
                        @endif
                        @if($booking->notes)
                        <tr>
                            <td class="text-muted small">Catatan Pemesan</td>
                            <td class="text-secondary small fst-italic">{{ $booking->notes }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            <!-- Card Kamar & Properti -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-door-open text-teal me-2"></i> Kamar & Properti</h6>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted small" style="width: 35%;">Properti</td>
                            <td class="fw-semibold text-dark">{{ $booking->property?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Alamat</td>
                            <td class="text-dark small">{{ $booking->property ? $booking->property->address . ', ' . $booking->property->city : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Nomor Kamar</td>
                            <td class="fw-bold text-dark">Kamar {{ $booking->room?->room_number ?? '-' }} (Lantai {{ $booking->room?->floor ?? '-' }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Tipe Kamar</td>
                            <td class="text-dark">{{ $booking->room?->roomType?->name ?? '-' }} (Maks {{ $booking->room?->roomType?->max_occupants ?? '-' }} org)</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Status Kamar Saat Ini</td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    {{ $booking->room?->status?->label() ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Card Audit & Catatan Lifecycle -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-teal me-2"></i> Jejak Audit & Riwayat</h6>
                </div>
                <div class="card-body p-3 small">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <span class="text-muted">Diajukan:</span> 
                            <strong>{{ $booking->created_at->isoFormat('D MMM Y, HH:mm') }}</strong>
                        </li>
                        @if($booking->confirmed_by)
                        <li class="mb-2 text-info">
                            <i class="bi bi-check-circle me-1"></i> Dikonfirmasi oleh: 
                            <strong>{{ $booking->confirmedByUser?->name ?? 'Staf' }}</strong>
                        </li>
                        @endif
                        @if($booking->checked_in_by)
                        <li class="mb-2 text-success">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Check-in oleh: 
                            <strong>{{ $booking->checkedInByUser?->name ?? 'Petugas' }}</strong> 
                            ({{ $booking->actual_check_in?->isoFormat('D MMM Y, HH:mm') }})
                        </li>
                        @endif
                        @if($booking->rejection_reason)
                        <li class="mb-2 text-danger">
                            <i class="bi bi-x-circle me-1"></i> Alasan Penolakan: 
                            <strong>{{ $booking->rejection_reason }}</strong>
                        </li>
                        @endif
                        @if($booking->cancellation_reason)
                        <li class="mb-2 text-secondary">
                            <i class="bi bi-slash-circle me-1"></i> Alasan Pembatalan: 
                            <strong>{{ $booking->cancellation_reason }}</strong>
                            @if($booking->cancelledByUser)
                                (oleh {{ $booking->cancelledByUser->name }})
                            @endif
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Rincian Keuangan & Invoices -->
        <div class="col-lg-6">
            <!-- Card Rincian Biaya -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-cash-stack text-teal me-2"></i> Rincian Biaya & Uang Muka</h6>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Biaya Sewa Kamar</td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($booking->rate_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">
                                        Uang Jaminan (Deposit)
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">*Terpisah dari sewa, dapat dikembalikan</small>
                                    </td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($booking->deposit_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-light">
                                    <td class="fw-bold">Total Nilai Pemesanan</td>
                                    <td class="text-end fw-bold text-teal fs-6">Rp {{ number_format($booking->total_amount, 0, ',', '.') }}</td>
                                </tr>
                                @if($booking->down_payment_amount > 0)
                                <tr class="table-primary-subtle">
                                    <td class="fw-semibold text-primary">Kewajiban Uang Muka (DP)</td>
                                    <td class="text-end fw-bold text-primary">Rp {{ number_format($booking->down_payment_amount, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Card Tagihan (Invoice) & Pembayaran -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt text-teal me-2"></i> Tagihan (Invoice) Terbit</h6>
                </div>
                <div class="card-body p-3">
                    @forelse($booking->invoices as $inv)
                        <div class="p-3 border rounded-3 mb-3 bg-light">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <a href="{{ route('dashboard.invoices.show', $inv->id) }}" class="fw-bold text-teal text-decoration-none">
                                        {{ $inv->invoice_number }}
                                    </a>
                                    <small class="text-muted d-block">Jatuh Tempo: {{ $inv->due_date?->isoFormat('D MMM Y') }}</small>
                                </div>
                                <span class="badge bg-{{ $inv->status->badgeClass() }}">
                                    {{ $inv->status->label() }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small mb-2">
                                <span class="text-muted">Total: Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</span>
                                <span class="text-success fw-bold">Terbayar: Rp {{ number_format($inv->paid_amount, 0, ',', '.') }}</span>
                            </div>

                            <!-- Daftar Pembayaran Terverifikasi -->
                            @if($inv->payments->isNotEmpty())
                                <div class="border-top pt-2 mt-2">
                                    <span class="text-muted very-small d-block fw-semibold mb-1" style="font-size: 0.75rem;">Riwayat Pembayaran:</span>
                                    @foreach($inv->payments as $p)
                                    <div class="d-flex justify-content-between small text-secondary">
                                        <span>{{ $p->payment_method }} ({{ $p->created_at->isoFormat('D MMM, HH:mm') }})</span>
                                        <span>
                                            Rp {{ number_format($p->amount, 0, ',', '.') }} 
                                            <span class="badge bg-{{ $p->status === 'verified' ? 'success' : ($p->status === 'pending' ? 'warning' : 'danger') }} py-0">
                                                {{ ucfirst($p->status) }}
                                            </span>
                                        </span>
                                    </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-2 text-end">
                                <a href="{{ route('dashboard.invoices.show', $inv->id) }}" class="btn btn-sm btn-outline-teal py-0 px-2 small">
                                    <i class="bi bi-receipt me-1"></i> Buka Tagihan
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum ada tagihan terbit.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Booking -->
<div class="modal fade" id="confirmBookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.bookings.confirm', $booking->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Konfirmasi Pemesanan Kamar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Apakah Anda yakin ingin mengonfirmasi pemesanan <strong>{{ $booking->booking_code }}</strong> untuk Kamar {{ $booking->room->room_number }}? Kamar akan dikunci untuk periode sewa ini.
                    </p>
                    <div class="mb-3">
                        <label for="confirm_notes" class="form-label small fw-semibold text-muted">Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" id="confirm_notes" rows="2" class="form-control form-control-sm" placeholder="Contoh: Pembayaran DP manual terverifikasi di kasir..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-teal px-3">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi Booking
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tolak Booking -->
<div class="modal fade" id="rejectBookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.bookings.reject', $booking->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger">Tolak Pemesanan Kamar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Penolakan akan membatalkan reservasi dan tagihan terkait. Alasan penolakan wajib diisi untuk transparansi.
                    </p>
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label small fw-semibold text-muted">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-control form-control-sm" placeholder="Contoh: Kamar sedang dialokasikan untuk pemeliharaan rutin..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3">
                        <i class="bi bi-x-lg me-1"></i> Tolak Pemesanan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Batalkan Booking -->
<div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.bookings.cancel', $booking->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-secondary">Batalkan Pemesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Pemesanan yang dibatalkan akan melepaskan kamar pada rentang tanggal sewa ini.
                    </p>
                    <div class="mb-3">
                        <label for="cancellation_reason" class="form-label small fw-semibold text-muted">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <textarea name="cancellation_reason" id="cancellation_reason" rows="3" class="form-control form-control-sm" placeholder="Contoh: Calon penghuni membatalkan rencana kedatangan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-sm btn-secondary px-3">
                        <i class="bi bi-slash-circle me-1"></i> Batalkan Pemesanan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Check-In -->
<div class="modal fade" id="checkInModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.bookings.checkin', $booking->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-success">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Proses Check-In Penghuni
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light rounded-3 mb-3 small">
                        <div class="fw-bold mb-1">{{ $booking->customer_name }}</div>
                        <div class="text-muted">Kamar {{ $booking->room->room_number }} &bull; {{ $booking->property->name }}</div>
                        <div class="text-muted">Jadwal: {{ $booking->start_date->isoFormat('D MMM Y') }} &ndash; {{ $booking->end_date->isoFormat('D MMM Y') }}</div>
                    </div>
                    <p class="small text-secondary mb-0">
                        Dengan memproses check-in:
                        <ul class="small text-secondary mt-1 mb-0">
                            <li>Status pemesanan akan berubah menjadi <strong>Aktif (Sedang Ditempati)</strong>.</li>
                            <li>Status kamar akan otomatis diperbarui menjadi <strong>Terisi (Occupied)</strong>.</li>
                            <li>Waktu kedatangan aktual dicatat dalam sistem.</li>
                        </ul>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success px-3">
                        <i class="bi bi-check2-circle me-1"></i> Konfirmasi Check-In
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
