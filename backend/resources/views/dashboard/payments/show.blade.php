@extends('layouts.app')

@section('title', 'Pemeriksaan Pembayaran ' . $payment->payment_code)

@section('content')
<div class="mb-4">
    <a href="{{ route('dashboard.payments.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
        <i class="bi bi-arrow-left"></i> Kembali ke Antrean
    </a>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold text-dark mb-0">Pemeriksaan Pembayaran: {{ $payment->payment_code }}</h3>
            <span class="text-muted small">Diajukan pada {{ $payment->created_at->format('d F Y, H:i:s') }} WIB</span>
        </div>
        <div>
            @if($payment->status === 'pending')
                <span class="badge badge-status-pending fs-6 px-3 py-2"><i class="bi bi-hourglass-split"></i> Menunggu Keputusan Kasir</span>
            @elseif($payment->status === 'verified')
                <span class="badge badge-status-verified fs-6 px-3 py-2"><i class="bi bi-check-circle-fill"></i> Sudah Disetujui (Lunas/Dicatat)</span>
            @else
                <span class="badge badge-status-rejected fs-6 px-3 py-2"><i class="bi bi-x-circle-fill"></i> Ditolak</span>
            @endif
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Rincian Tagihan & Nominal -->
    <div class="col-lg-6">
        <!-- Kartu Rincian Transaksi -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle text-primary-teal me-2"></i>Rincian Pengajuan Pembayaran</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width: 40%;">Nominal Ditransfer:</td>
                        <td><strong class="fs-4 text-success">Rp {{ number_format($payment->amount, 0, ',', '.') }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Metode Pembayaran:</td>
                        <td><span class="badge bg-light text-dark border">{{ strtoupper($payment->payment_method) }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Bank / E-Wallet Pengirim:</td>
                        <td><strong>{{ $payment->sender_bank ?? '-' }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Nama Rekening Pengirim:</td>
                        <td>{{ $payment->sender_name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Penyewa (Pengaju):</td>
                        <td>
                            <strong>{{ $payment->user->name ?? '-' }}</strong><br>
                            <span class="small text-muted">{{ $payment->user->email }} • {{ $payment->user->phone }}</span>
                        </td>
                    </tr>
                    @if($payment->verified_by)
                    <tr>
                        <td class="text-muted">Diverifikasi Oleh:</td>
                        <td>
                            <strong class="text-dark">{{ $payment->verifier->name ?? '-' }}</strong>
                            <div class="small text-muted">{{ $payment->verified_at?->format('d/m/Y H:i') }} WIB</div>
                        </td>
                    </tr>
                    @endif
                    @if($payment->rejection_reason)
                    <tr>
                        <td class="text-danger fw-semibold">Alasan Penolakan:</td>
                        <td class="text-danger"><em>"{{ $payment->rejection_reason }}"</em></td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        <!-- Kartu Rincian Invoice Terkait -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt text-primary-teal me-2"></i>Status Tagihan Terkait</h5>
                <a href="{{ route('dashboard.invoices.show', $payment->invoice_id) }}" class="btn btn-sm btn-outline-primary">Lihat Invoice Lengkap</a>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="text-muted small">Nomor Invoice:</span>
                    <div class="fw-bold fs-6">{{ $payment->invoice->invoice_number }}</div>
                    <span class="small text-muted">Jatuh Tempo: {{ $payment->invoice->due_date?->format('d M Y') }}</span>
                </div>
                <div class="p-3 bg-light rounded-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Kewajiban Tagihan:</span>
                        <strong>Rp {{ number_format($payment->invoice->total_amount, 0, ',', '.') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Sudah Diverifikasi:</span>
                        <strong class="text-success">Rp {{ number_format($payment->invoice->paid_amount, 0, ',', '.') }}</strong>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-dark">Sisa Saldo Tagihan:</span>
                        <strong class="fs-5 text-danger">Rp {{ number_format($payment->invoice->remaining_balance, 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Tindakan Verifikasi (Jika Status Masih Pending) -->
        @if($payment->status === 'pending')
        <div class="card border-primary-teal">
            <div class="card-header bg-primary-teal text-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i>Tindakan Keputusan Kasir</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <!-- Tombol Setujui -->
                    <div class="col-12">
                        <form action="{{ route('dashboard.payments.verify', $payment->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin data mutasi bank sudah cocok dan ingin MENYETUJUI pembayaran ini?');">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Catatan Verifikasi (Opsional):</label>
                                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Cocok dengan mutasi BCA jam 14:15">
                            </div>
                            <button type="submit" class="btn btn-success w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-check-circle-fill fs-5"></i> Setujui Pembayaran (Lunas/Update Saldo)
                            </button>
                        </form>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>

                    <!-- Tombol Tolak -->
                    <div class="col-12">
                        <button class="btn btn-outline-danger w-100 py-2 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#rejectCollapse">
                            <i class="bi bi-x-circle me-1"></i> Tolak Pengajuan Pembayaran Ini...
                        </button>
                        <div class="collapse mt-3" id="rejectCollapse">
                            <div class="p-3 border rounded bg-light">
                                <form action="{{ route('dashboard.payments.reject', $payment->id) }}" method="POST" onsubmit="return confirm('Tolak pembayaran ini? Alasan penolakan akan dikirimkan kepada penyewa.');">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-danger">Alasan Penolakan (Wajib Diisi):</label>
                                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Contoh: Bukti transfer buram / nominal tidak sesuai / dana belum masuk di rekening koran..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-danger btn-sm w-100">
                                        Konfirmasi Tolak Pembayaran
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Kolom Kanan: Pratinjau Foto Bukti Transfer -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-image text-primary-teal me-2"></i>Foto Bukti Transfer Fisik</h5>
                @if($payment->proof_photo)
                <a href="{{ route('dashboard.payments.proof', $payment->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka Ukuran Asli
                </a>
                @endif
            </div>
            <div class="card-body text-center p-3 d-flex align-items-center justify-content-center bg-light" style="min-height: 420px;">
                @if($payment->proof_photo)
                    <div class="position-relative w-100">
                        <img src="{{ route('dashboard.payments.proof', $payment->id) }}" 
                             alt="Bukti Transfer {{ $payment->payment_code }}" 
                             class="img-fluid rounded border shadow-sm" 
                             style="max-height: 540px; object-fit: contain;">
                        <p class="text-muted small mt-2 mb-0">Klik "Buka Ukuran Asli" untuk memperbesar struk.</p>
                    </div>
                @else
                    <div class="text-muted p-5">
                        <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary"></i>
                        Penyewa tidak melampirkan berkas bukti transfer.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
