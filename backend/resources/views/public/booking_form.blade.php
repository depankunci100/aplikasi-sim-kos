@extends('layouts.public')

@section('title', 'Form Pemesanan Kamar ' . $room->room_number)

@section('content')
<div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('public.catalog') }}" class="text-teal text-decoration-none">Katalog</a></li>
            <li class="breadcrumb-item"><a href="{{ route('public.rooms.show', $room->id) }}" class="text-teal text-decoration-none">Kamar {{ $room->room_number }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Pengajuan Sewa</li>
        </ol>
    </nav>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold text-dark mb-1">Pengajuan Pemesanan Kamar</h4>
                            <p class="text-muted small mb-0">Isi data calon penghuni di bawah ini secara akurat untuk verifikasi pengelola.</p>
                        </div>
                        <span class="badge bg-teal-subtle text-teal border px-3 py-2 fw-semibold">
                            Kamar {{ $room->room_number }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Ringkasan Kamar & Periode -->
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Properti:</span>
                                <span class="fw-bold text-dark">{{ $room->property->name }}</span>
                                <small class="text-muted d-block">{{ $room->property->address }}</small>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Periode Sewa:</span>
                                <span class="fw-bold text-dark">
                                    {{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }} &ndash; {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}
                                </span>
                                <small class="text-muted d-block">Durasi: {{ $pricing['days'] }} hari ({{ ucfirst($rentalType) }})</small>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-muted">Total Tagihan Awal: <strong class="text-dark">Rp {{ number_format($pricing['total_amount'], 0, ',', '.') }}</strong></span>
                            @if($pricing['dp_required'])
                                <span class="badge badge-dp px-2 py-1">Wajib DP: Rp {{ number_format($pricing['down_payment_amount'], 0, ',', '.') }}</span>
                            @else
                                <span class="badge bg-success-subtle text-success px-2 py-1">Tanpa Uang Muka (DP)</span>
                            @endif
                        </div>
                    </div>

                    <!-- Form Pengajuan -->
                    <form action="{{ route('public.booking.submit') }}" method="POST" id="bookingForm" onsubmit="handleBookingSubmit(this);">
                        @csrf
                        <input type="hidden" name="room_id" value="{{ $room->id }}">
                        <input type="hidden" name="rental_type" value="{{ $rentalType }}">
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">

                        <h5 class="fw-bold text-dark mb-3">Data Calon Penghuni (Guest)</h5>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="guest_name" class="form-label small fw-semibold text-muted">Nama Lengkap Sesuai KTP <span class="text-danger">*</span></label>
                                <input type="text" name="guest_name" id="guest_name" class="form-control @error('guest_name') is-invalid @enderror" value="{{ old('guest_name') }}" placeholder="Contoh: Budi Santoso" required>
                                @error('guest_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="guest_phone" class="form-label small fw-semibold text-muted">Nomor WhatsApp / HP Aktif <span class="text-danger">*</span></label>
                                <input type="tel" name="guest_phone" id="guest_phone" class="form-control @error('guest_phone') is-invalid @enderror" value="{{ old('guest_phone') }}" placeholder="Contoh: 081234567890" required>
                                @error('guest_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="guest_email" class="form-label small fw-semibold text-muted">Alamat Email Aktif <span class="text-danger">*</span></label>
                                <input type="email" name="guest_email" id="guest_email" class="form-control @error('guest_email') is-invalid @enderror" value="{{ old('guest_email') }}" placeholder="Contoh: budi@gmail.com" required>
                                @error('guest_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="guest_id_card" class="form-label small fw-semibold text-muted">Nomor NIK / KTP (Opsional)</label>
                                <input type="text" name="guest_id_card" id="guest_id_card" class="form-control @error('guest_id_card') is-invalid @enderror" value="{{ old('guest_id_card') }}" placeholder="16 digit NIK KTP">
                                @error('guest_id_card')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label small fw-semibold text-muted">Catatan Khusus atau Permintaan Tambahan (Opsional)</label>
                                <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="Contoh: Rencana check-in sore hari sekitar jam 16:00 WIB">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Persetujuan Aturan -->
                        <div class="p-3 bg-light rounded-3 mb-4">
                            <div class="form-check">
                                <input class="form-check-input @error('agree_terms') is-invalid @enderror" type="checkbox" name="agree_terms" id="agree_terms" value="1" {{ old('agree_terms') ? 'checked' : '' }} required>
                                <label class="form-check-label small" for="agree_terms">
                                    Saya menyetujui <a href="#" class="text-teal" data-bs-toggle="modal" data-bs-target="#termsModal">Peraturan Properti & Kebijakan SIM Kos</a> serta menyatakan bahwa seluruh data yang diisi adalah benar.
                                </label>
                                @error('agree_terms')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('public.rooms.show', $room->id) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Kembali
                            </a>
                            <button type="submit" id="btnSubmitBooking" class="btn btn-teal px-4 py-2 fw-semibold">
                                <i class="bi bi-check2-circle me-1"></i> Ajukan Pemesanan Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Aturan Properti -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="termsModalLabel">Aturan & Kebijakan Sewa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body small text-secondary">
                <p class="fw-semibold text-dark">Kebijakan Pemesanan:</p>
                <ul>
                    <li>Pemesanan awal berstatus <em>Pending</em> menunggu konfirmasi staf atau pembayaran uang muka (DP).</li>
                    <li>Jika properti menerapkan kebijakan Uang Muka (DP), tagihan harus dibayar sebelum masa berlaku hold kedaluwarsa.</li>
                    <li>Uang jaminan (deposit) dicatat terpisah dan akan dikembalikan saat penyewa check-out setelah inspeksi kamar selesai tanpa kerusakan.</li>
                </ul>
                <p class="fw-semibold text-dark">Peraturan Lingkungan:</p>
                <div class="p-2 bg-light rounded">
                    {!! nl2br(e($room->property->rules ?: "Dilarang membuat kegaduhan di atas pukul 22:00 WIB.\nMenjaga kebersihan fasilitas bersama.\nDilarang membawa barang berbahaya atau terlarang.")) !!}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-teal" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function handleBookingSubmit(form) {
        var btn = document.getElementById('btnSubmitBooking');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memproses Pemesanan...';
    }
</script>
@endpush
