@extends('layouts.app')

@section('title', 'Detail Pengeluaran ' . $expense->expense_number . ' - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.expenses.index') }}" class="text-decoration-none">Pengeluaran</a></li>
                <li class="breadcrumb-item active">{{ $expense->expense_number }}</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <h3 class="fw-bold mb-0">{{ $expense->expense_number }}</h3>
            <span class="badge bg-{{ $expense->approval_status->badgeClass() }} fs-6">
                {{ $expense->approval_status->label() }}
            </span>
            <span class="badge bg-{{ $expense->payment_status->badgeClass() }} fs-6">
                {{ $expense->payment_status->label() }}
            </span>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2 flex-wrap">
        @if($expense->isDraft())
            <a href="{{ route('dashboard.expenses.edit', $expense->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil me-1"></i>Edit Draft
            </a>
            <form action="{{ route('dashboard.expenses.submit', $expense->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary-teal btn-sm" onclick="return confirm('Ajukan pengeluaran ini untuk persetujuan?')">
                    <i class="bi bi-send-check me-1"></i>Ajukan Persetujuan
                </button>
            </form>
        @endif

        @if($expense->isSubmitted())
            @php
                $canApprove = (auth()->user()->isSuperAdmin() || auth()->user()->isOwner() || auth()->user()->isManager());
                $isSelf = (auth()->user()->id === $expense->user_id && !auth()->user()->isSuperAdmin());
            @endphp

            @if($canApprove && !$isSelf)
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalApprove">
                    <i class="bi bi-check-circle me-1"></i>Setujui
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalReject">
                    <i class="bi bi-x-circle me-1"></i>Tolak
                </button>
            @elseif($isSelf)
                <span class="badge bg-warning bg-opacity-10 text-dark border p-2">
                    <i class="bi bi-info-circle me-1"></i>Menunggu persetujuan peninjau independen (Segregation of Duties).
                </span>
            @endif
        @endif

        @if($expense->isApproved() && !$expense->isPaid())
            <button type="button" class="btn btn-primary-teal btn-sm" data-bs-toggle="modal" data-bs-target="#modalRecordPayment">
                <i class="bi bi-cash-stack me-1"></i>Catat Pembayaran
            </button>
        @endif

        <a href="{{ route('dashboard.expenses.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Details -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle text-primary-teal me-2"></i>Informasi Pengeluaran</h6>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small fw-semibold d-block">Cabang Properti</label>
                        <span class="fw-bold text-dark">{{ $expense->property->name ?? '-' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-semibold d-block">Kategori</label>
                        <span class="badge bg-light text-dark border">{{ $expense->expenseCategory?->name ?? ucfirst($expense->category) }}</span>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small fw-semibold d-block">Judul Pengeluaran</label>
                        <span class="fs-5 fw-bold text-dark">{{ $expense->title }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-semibold d-block">Vendor / Penerima</label>
                        <span>{{ $expense->vendor_name ?: 'Tidak ditentukan' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-semibold d-block">Tanggal Pengeluaran</label>
                        <span><i class="bi bi-calendar3 me-1"></i>{{ $expense->expense_date?->format('d M Y') }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-semibold d-block">Metode Bayar Rencana</label>
                        <span class="text-capitalize">{{ str_replace('_', ' ', $expense->payment_method ?? 'cash') }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-semibold d-block">Diajukan Oleh</label>
                        <span>{{ $expense->user->name ?? '-' }} ({{ $expense->created_at->format('d M Y H:i') }})</span>
                    </div>

                    @if($expense->approved_by)
                    <div class="col-sm-6">
                        <label class="text-muted small fw-semibold d-block">Disetujui Oleh</label>
                        <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>{{ $expense->approver->name ?? '-' }} ({{ $expense->approved_at?->format('d M Y H:i') }})</span>
                    </div>
                    @endif

                    @if($expense->rejection_reason)
                    <div class="col-12">
                        <div class="alert alert-danger mb-0">
                            <strong>Alasan Penolakan:</strong> {{ $expense->rejection_reason }}
                        </div>
                    </div>
                    @endif

                    @if($expense->notes)
                    <div class="col-12">
                        <label class="text-muted small fw-semibold d-block">Catatan Tambahan</label>
                        <div class="p-2 bg-light rounded text-secondary">{{ $expense->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payments Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-credit-card-2-front text-primary-teal me-2"></i>Histori Pembayaran Realisasi</h6>
                @if($expense->isApproved() && !$expense->isPaid())
                    <button type="button" class="btn btn-sm btn-primary-teal" data-bs-toggle="modal" data-bs-target="#modalRecordPayment">
                        <i class="bi bi-plus-circle me-1"></i>Catat Pembayaran
                    </button>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">No. Bukti Bayar</th>
                                <th>Tanggal</th>
                                <th>Metode</th>
                                <th>Nominal</th>
                                <th>Dicatat Oleh</th>
                                <th class="text-end pe-3">Bukti</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expense->payments as $pmt)
                            <tr>
                                <td class="ps-3 fw-bold text-dark font-monospace">{{ $pmt->payment_number }}</td>
                                <td>{{ $pmt->payment_date->format('d M Y') }}</td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $pmt->payment_method) }}</td>
                                <td class="fw-bold text-success">Rp {{ number_format($pmt->amount, 0, ',', '.') }}</td>
                                <td>{{ $pmt->recorder->name ?? '-' }}</td>
                                <td class="text-end pe-3">
                                    @if($pmt->proof_file)
                                        <a href="{{ route('dashboard.expenses.payment-proof', ['id' => $expense->id, 'paymentId' => $pmt->id]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-file-earmark-image"></i>
                                        </a>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada pembayaran yang dicatat.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Audit Trail Logs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary-teal me-2"></i>Audit Trail & Linimasa Aktivitas</h6>
            </div>
            <div class="card-body pt-0">
                <div class="timeline">
                    @forelse($expense->logs as $log)
                    <div class="d-flex gap-3 pb-3 border-bottom mb-3">
                        <div class="text-primary-teal"><i class="bi bi-circle-fill" style="font-size: 8px;"></i></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-dark">{{ ucfirst($log->action) }}</span>
                                <small class="text-muted">{{ $log->created_at->format('d M Y H:i:s') }}</small>
                            </div>
                            <div class="small text-muted">{{ $log->notes }}</div>
                            <div class="small text-secondary">Oleh: {{ $log->user->name ?? 'Sistem' }}</div>
                        </div>
                    </div>
                    @empty
                    <div class="text-muted small">Belum ada riwayat aktivitas.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Financial Summary & Receipt Preview -->
    <div class="col-lg-4">
        <!-- Nominal Summary Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-cash-coin text-primary-teal me-2"></i>Ringkasan Finansial</h6>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Total Tagihan</span>
                    <span class="fw-bold text-dark fs-5">Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Total Terbayar</span>
                    <span class="fw-bold text-success fs-5">Rp {{ number_format($expense->paid_amount, 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Sisa Belum Dibayar</span>
                    <span class="fw-bold text-danger fs-5">Rp {{ number_format($expense->remaining_balance, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Receipt / Nota Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt-cutoff text-primary-teal me-2"></i>Bukti Nota / Kuitansi</h6>
            </div>
            <div class="card-body pt-0 text-center">
                @if($expense->receipt_photo)
                    <div class="mb-3">
                        <i class="bi bi-file-earmark-pdf fs-1 text-primary-teal d-block"></i>
                        <small class="text-muted d-block">Bukti tersimpan secara privat & aman.</small>
                    </div>
                    <a href="{{ route('dashboard.expenses.receipt', $expense->id) }}" target="_blank" class="btn btn-outline-primary-teal btn-sm w-100">
                        <i class="bi bi-download me-1"></i>Lihat / Unduh Dokumen
                    </a>
                @else
                    <div class="py-4 text-muted">
                        <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i>
                        <span>Tidak ada bukti kuitansi yang dilampirkan.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal Approve -->
<div class="modal fade modal-confirm" id="modalApprove" tabindex="-1" aria-labelledby="modalApproveLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('dashboard.expenses.approve', $expense->id) }}" method="POST" class="w-100">
            @csrf
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalApproveLabel">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i> Setujui Pengeluaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Harap pastikan data pengeluaran berikut telah diverifikasi kebenarannya sebelum memberikan persetujuan:</p>
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">No. Pengeluaran:</span>
                            <span class="fw-bold font-monospace text-dark">{{ $expense->expense_number }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Cabang Properti:</span>
                            <span class="fw-semibold text-dark">{{ $expense->property->name ?? '-' }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Vendor / Penerima:</span>
                            <span class="text-dark">{{ $expense->vendor_name ?: '-' }}</span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="text-muted small fw-bold">Nominal Pengeluaran:</span>
                            <span class="fs-5 fw-bold text-success">Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Catatan Persetujuan (Opsional)</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Contoh: Disetujui sesuai pagu anggaran operasional bulanan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm px-3">
                        <i class="bi bi-check2 me-1"></i>Setujui Pengeluaran
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reject -->
<div class="modal fade modal-confirm" id="modalReject" tabindex="-1" aria-labelledby="modalRejectLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('dashboard.expenses.reject', $expense->id) }}" method="POST" class="w-100">
            @csrf
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="modalRejectLabel">
                        <i class="bi bi-x-circle-fill text-danger fs-5"></i> Tolak Pengeluaran Operasional
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Tolak pengajuan pengeluaran <strong>{{ $expense->expense_number }}</strong> senilai <strong>Rp {{ number_format($expense->amount, 0, ',', '.') }}</strong>. Harap cantumkan alasan penolakan secara jelas:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control form-control-sm @error('rejection_reason') is-invalid @enderror"
                                  rows="3" required placeholder="Contoh: Bukti kuitansi buram atau nominal melebihi anggaran cabang..."></textarea>
                        @error('rejection_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">
                        <i class="bi bi-x-circle me-1"></i>Tolak Pengajuan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Record Payment -->
<div class="modal fade modal-confirm" id="modalRecordPayment" tabindex="-1" aria-labelledby="modalRecordPaymentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('dashboard.expenses.pay', $expense->id) }}" method="POST" enctype="multipart/form-data" class="w-100">
            @csrf
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalRecordPaymentLabel">
                        <i class="bi bi-cash-stack text-primary-teal fs-5"></i> Catat Realisasi Pembayaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Total Tagihan:</span>
                            <span class="fw-semibold text-dark">Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Sudah Dibayar:</span>
                            <span class="text-success fw-semibold">Rp {{ number_format($expense->paid_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="text-muted small fw-bold">Sisa Pembayaran:</span>
                            <span class="fs-5 fw-bold text-danger">Rp {{ number_format($expense->remaining_balance, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nominal Pembayaran (Rp) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" max="{{ $expense->remaining_balance }}" name="amount"
                               class="form-control form-control-sm @error('amount') is-invalid @enderror" value="{{ $expense->remaining_balance }}" required>
                        <div class="form-text small">Dapat berupa pelunasan penuh atau sebagian (pembayaran parsial).</div>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Pembayaran <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control form-control-sm @error('payment_date') is-invalid @enderror"
                               value="{{ date('Y-m-d') }}" required>
                        @error('payment_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Metode Pembayaran <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select form-select-sm @error('payment_method') is-invalid @enderror" required>
                            <option value="cash">Tunai / Kas Kecil (Petty Cash)</option>
                            <option value="bank_transfer">Transfer Bank</option>
                            <option value="qris">QRIS / E-Wallet</option>
                            <option value="other">Lainnya</option>
                        </select>
                        @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bukti Pembayaran / Slip Transfer (Opsional)</label>
                        <input type="file" name="proof_file" class="form-control form-control-sm @error('proof_file') is-invalid @enderror"
                               accept="image/jpeg,image/png,image/webp,application/pdf">
                        @error('proof_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Catatan</label>
                        <textarea name="notes" class="form-control form-control-sm @error('notes') is-invalid @enderror"
                                  rows="2" placeholder="Keterangan tambahan..."></textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-teal btn-sm px-3">
                        <i class="bi bi-save me-1"></i>Simpan Pembayaran
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
