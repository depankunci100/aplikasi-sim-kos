@extends('layouts.public')

@section('title', 'Status Pemesanan ' . $booking->booking_code)

@section('content')
<div class="container">
    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <!-- Header Status Card -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="p-4 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #115e59 100%);">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <span class="text-white-50 small d-block">Nomor Referensi Pemesanan:</span>
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="fw-bold mb-0 text-white">{{ $booking->booking_code }}</h3>
                                <button type="button" class="btn btn-sm btn-light bg-opacity-25 text-white border-0 py-0 px-2" onclick="navigator.clipboard.writeText('{{ $booking->booking_code }}'); alert('Kode booking disalin ke clipboard!');" title="Salin Kode">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-{{ $booking->status->badgeClass() }} fs-6 px-3 py-2 border shadow-sm">
                                {{ $booking->status->label() }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Status Banner / Expiry Alert -->
                    @if($booking->status === \App\Enums\BookingStatus::PENDING)
                        <div class="alert alert-warning border-warning d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-clock-history fs-4 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Pemesanan Menunggu Konfirmasi / Pembayaran</h6>
                                <p class="small mb-0 text-secondary">
                                    Mohon selesaikan kewajiban pembayaran uang muka atau tunggu peninjauan oleh tim pengelola. 
                                    @if($booking->expires_at)
                                        Batas waktu penahanan kamar: <strong class="text-dark">{{ $booking->expires_at->isoFormat('D MMMM Y, HH:mm') }} WIB</strong>.
                                    @endif
                                </p>
                            </div>
                        </div>
                    @elseif($booking->status === \App\Enums\BookingStatus::CONFIRMED)
                        <div class="alert alert-info border-info d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-check-circle-fill fs-4 text-info"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Pemesanan Telah Terkonfirmasi!</h6>
                                <p class="small mb-0 text-secondary">
                                    Kamar Anda telah diamankan. Silakan datang pada tanggal masuk untuk proses check-in dan serah terima kunci kamar bersama petugas kos.
                                </p>
                            </div>
                        </div>
                    @elseif($booking->status === \App\Enums\BookingStatus::ACTIVE)
                        <div class="alert alert-success border-success d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-house-check-fill fs-4 text-success"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Masa Sewa Sedang Aktif</h6>
                                <p class="small mb-0 text-secondary">
                                    Anda telah resmi check-in pada {{ $booking->actual_check_in?->isoFormat('D MMMM Y, HH:mm') }} WIB. Selamat menikmati hunian Anda!
                                </p>
                            </div>
                        </div>
                    @elseif($booking->status === \App\Enums\BookingStatus::REJECTED)
                        <div class="alert alert-danger border-danger d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-x-octagon-fill fs-4 text-danger"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Permohonan Pemesanan Ditolak</h6>
                                <p class="small mb-0 text-secondary">
                                    Alasan: <strong>{{ $booking->rejection_reason ?: 'Kamar tidak memenuhi syarat atau kuota telah penuh.' }}</strong>
                                </p>
                            </div>
                        </div>
                    @elseif($booking->status === \App\Enums\BookingStatus::CANCELLED)
                        <div class="alert alert-secondary border d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-dash-circle-fill fs-4 text-secondary"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Pemesanan Dibatalkan</h6>
                                <p class="small mb-0 text-muted">
                                    Alasan: {{ $booking->cancellation_reason ?: 'Dibatalkan atas permintaan pemesan atau pengelola.' }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Detail Kamar & Properti -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <span class="text-muted small d-block">Properti & Kamar:</span>
                                <h6 class="fw-bold mb-1 text-dark">{{ $booking->property->name }}</h6>
                                <p class="text-muted small mb-0">
                                    Kamar {{ $booking->room->room_number }} ({{ $booking->room->roomType->name }}) &bull; Lantai {{ $booking->room->floor }}
                                </p>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-geo-alt me-1"></i> {{ $booking->property->address }}, {{ $booking->property->city }}
                                </small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100">
                                <span class="text-muted small d-block">Jadwal Sewa:</span>
                                <h6 class="fw-bold mb-1 text-dark">
                                    {{ $booking->start_date->isoFormat('D MMM Y') }} &ndash; {{ $booking->end_date->isoFormat('D MMM Y') }}
                                </h6>
                                <p class="text-muted small mb-0">
                                    Model: <span class="badge bg-secondary-subtle text-dark border">{{ ucfirst($booking->rental_type->value) }}</span>
                                </p>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-person me-1"></i> Atas Nama: {{ $booking->customer_name }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Rincian Biaya & Invoice -->
                    <h5 class="fw-bold text-dark mb-3">Rincian Biaya & Tagihan</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Komponen Biaya</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Biaya Sewa Kamar</td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($booking->rate_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td>
                                        Uang Jaminan (Deposit)
                                        <small class="text-muted d-block" style="font-size: 0.75rem;">*Dipisahkan dari pendapatan sewa, dapat dikembalikan saat check-out.</small>
                                    </td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($booking->deposit_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="table-light">
                                    <td class="fw-bold">Total Biaya Pemesanan</td>
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

                    <!-- Informasi Invoice Terkait -->
                    @if($booking->invoices->isNotEmpty())
                    <h6 class="fw-bold text-dark mb-2">Status Pembayaran Invoice</h6>
                    <div class="list-group mb-4">
                        @foreach($booking->invoices as $inv)
                        <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <span class="fw-bold">{{ $inv->invoice_number }}</span>
                                <small class="text-muted d-block">Jatuh Tempo: {{ $inv->due_date?->isoFormat('D MMM Y') }}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-{{ $inv->status->badgeClass() }} mb-1">
                                    {{ $inv->status->label() }}
                                </span>
                                <div class="small text-muted">
                                    Terbayar: Rp {{ number_format($inv->paid_amount, 0, ',', '.') }} / Rp {{ number_format($inv->total_amount, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    <!-- Petunjuk & Tautan Pembagian Status Aman -->
                    <div class="p-3 bg-secondary-subtle rounded-3 small">
                        <h6 class="fw-bold mb-1"><i class="bi bi-shield-lock me-1"></i> Informasi Privasi & Tautan Aman</h6>
                        <p class="mb-2 text-secondary">
                            Simpan tautan halaman ini untuk memeriksa pembaruan status pemesanan Anda tanpa perlu login:
                        </p>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control bg-white" value="{{ url()->current() }}" readonly id="shareUrlInput">
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('shareUrlInput').value); alert('Tautan status pemesanan disalin!');">
                                <i class="bi bi-copy"></i> Salin Tautan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
