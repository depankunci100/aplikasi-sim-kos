@extends('layouts.app')

@section('title', 'Detail Settlement Check-Out ' . $settlement->settlement_code)

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.deposits.index') }}" class="text-teal text-decoration-none">Uang Jaminan</a></li>
                <li class="breadcrumb-item"><a href="{{ route('dashboard.deposits.settlements.index') }}" class="text-teal text-decoration-none">Penyelesaian Check-Out</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $settlement->settlement_code }}</li>
            </ol>
        </nav>
        <a href="{{ route('dashboard.deposits.settlements.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    <!-- Header Banner -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden">
        <div class="card-body p-4 bg-white border-start border-{{ $settlement->status->badgeClass() }} border-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0">{{ $settlement->settlement_code }}</h4>
                        <span class="badge bg-{{ $settlement->status->badgeClass() }} fs-6 px-3 py-1">
                            {{ $settlement->status->label() }}
                        </span>
                    </div>
                    <p class="text-muted small mb-0">
                        Pemesanan: <strong class="text-dark">{{ $settlement->booking?->booking_code }}</strong> &bull;
                        Tgl Check-Out: <strong>{{ $settlement->check_out_date?->isoFormat('D MMMM Y') }}</strong> &bull;
                        Kamar {{ $settlement->booking?->room?->room_number }} ({{ $settlement->property?->name }})
                    </p>
                </div>

                <div class="d-flex gap-2">
                    @if($settlement->isApproved() && auth()->user()->canManageFinances())
                    <button type="button" class="btn btn-success d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#finalizeRefundModal">
                        <i class="bi bi-cash-stack"></i>
                        <span>Finalisasi Check-Out & Refund</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($settlement->isDisputed())
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>Penyewa Mengajukan Sengketa (Dispute):</strong> {{ $settlement->tenant_objection_notes ?: 'Penyewa mengajukan keberatan terhadap usulan denda kerusakan.' }}
        </div>
    </div>
    @endif

    <div class="row g-4">
        <!-- Kolom Kiri: Rincian Keuangan & Daftar Potongan -->
        <div class="col-lg-7">
            <!-- Card Rincian Kalkulasi Settlement -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-calculator text-teal me-2"></i> Rekapitulasi Nilai Pengembalian</h6>
                </div>
                <div class="card-body p-3">
                    <table class="table table-bordered align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 55%;">Saldo Deposit Penyewa (Tersedia)</td>
                                <td class="text-end fw-semibold">Rp {{ number_format($settlement->deposit_balance_before, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="table-danger-subtle">
                                <td class="text-danger fw-semibold">Total Potongan Denda Kerusakan / Tunggakan</td>
                                <td class="text-end fw-bold text-danger">-Rp {{ number_format($settlement->total_deductions, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="table-light">
                                <td class="fw-bold fs-6">Sisa Hak Pengembalian (Net Refund)</td>
                                <td class="text-end fw-bold fs-5 text-teal">
                                    Rp {{ number_format($settlement->net_refund_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card Daftar Potongan Denda -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-check text-teal me-2"></i> Rincian Usulan Potongan Denda</h6>
                    <span class="badge bg-secondary-subtle text-secondary">{{ $settlement->deductions->count() }} Item</span>
                </div>
                <div class="card-body p-0 mt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th class="ps-3 py-2">Item Potongan</th>
                                    <th>Nominal Diajukan</th>
                                    <th>Nominal Disahkan</th>
                                    <th>Status Persetujuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($settlement->deductions as $d)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark">{{ $d->title }}</div>
                                        <small class="text-muted d-block">{{ $d->description ?: '-' }}</small>
                                        @if($d->is_disputed)
                                            <span class="badge bg-danger-subtle text-danger small mt-1">Disengketakan</span>
                                            @if($d->dispute_reason)
                                                <small class="text-danger d-block fst-italic">{{ $d->dispute_reason }}</small>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-muted">Rp {{ number_format($d->proposed_amount, 0, ',', '.') }}</td>
                                    <td class="fw-bold text-dark">Rp {{ number_format($d->approved_amount, 0, ',', '.') }}</td>
                                    <td>
                                        @if($d->is_approved)
                                            <span class="badge bg-success-subtle text-success border">Disetujui</span>
                                        @elseif($d->is_disputed)
                                            <span class="badge bg-danger-subtle text-danger border">Keberatan</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">Dibatalkan</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Tidak ada potongan denda kerusakan. Deposit dapat dikembalikan penuh.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Aksi Penyewa / Sengketa / Bukti Selesai -->
        <div class="col-lg-5">
            <!-- Aksi Keputusan Penyewa (Jika status PENDING_TENANT_APPROVAL) -->
            @if($settlement->isPendingApproval())
            <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-warning border-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-check text-warning me-2"></i> Respon Persetujuan Penyewa</h6>
                </div>
                <div class="card-body p-3">
                    <p class="text-muted small mb-3">
                        Sebagai penyewa, Anda dipersilakan meninjau usulan potongan denda di samping. Anda dapat menyetujui atau mengajukan keberatan.
                    </p>
                    <form action="{{ route('dashboard.deposits.settlements.tenant_decision', $settlement->id) }}" method="POST">
                        @csrf
                        <div class="d-flex gap-2 mb-3">
                            <button type="submit" name="decision" value="approve" class="btn btn-sm btn-success w-50">
                                <i class="bi bi-check-circle me-1"></i> Setujui Rincian
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger w-50" data-bs-toggle="collapse" data-bs-target="#disputeCollapse">
                                <i class="bi bi-x-circle me-1"></i> Ajukan Keberatan
                            </button>
                        </div>

                        <div class="collapse" id="disputeCollapse">
                            <div class="p-3 bg-light rounded-3">
                                <label class="form-label small fw-semibold text-muted">Jelaskan Keberatan Anda <span class="text-danger">*</span></label>
                                <textarea name="objection_notes" rows="3" class="form-control form-control-sm mb-2" placeholder="Sebutkan alasan atau kondisi sebelum check-in..."></textarea>
                                <button type="submit" name="decision" value="dispute" class="btn btn-sm btn-danger w-100">
                                    Kirim Keberatan (Dispute)
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <!-- Form Penyelesaian Sengketa (Jika status DISPUTED) -->
            @if($settlement->isDisputed() && auth()->user()->canManageFinances())
            <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-danger border-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-shaded text-danger me-2"></i> Otorisasi Penyelesaian Sengketa</h6>
                </div>
                <div class="card-body p-3">
                    <p class="text-muted small mb-3">
                        Sesuaikan nilai denda akhir yang disahkan oleh manajemen setelah mempertimbangkan komplain penyewa.
                    </p>
                    <form action="{{ route('dashboard.deposits.settlements.resolve', $settlement->id) }}" method="POST">
                        @csrf
                        @foreach($settlement->deductions as $ded)
                        <div class="mb-3 p-2 bg-light rounded">
                            <label class="form-label small fw-semibold text-dark mb-1">{{ $ded->title }}</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Rp</span>
                                <input type="number" step="1000" min="0" name="approved_amounts[{{ $ded->id }}]" value="{{ $ded->approved_amount }}" class="form-control" required>
                            </div>
                        </div>
                        @endforeach

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Catatan Keputusan Manajemen <span class="text-danger">*</span></label>
                            <textarea name="resolution_notes" rows="3" class="form-control form-control-sm" placeholder="Dasar keputusan diskon/penyesuaian denda..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-sm btn-danger w-100">
                            <i class="bi bi-check2-all me-1"></i> Sahkan Keputusan Sengketa
                        </button>
                    </form>
                </div>
            </div>
            @endif

            <!-- Info Penyelesaian Check-Out Selesai (Jika status SETTLED) -->
            @if($settlement->isSettled())
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-success-subtle border-start border-success border-4">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-2"></i> Check-Out Telah Selesai (Settled)</h6>
                    <p class="small text-muted mb-2">
                        Pengembalian uang jaminan telah dicatat keluar dan masa sewa telah ditutup permanen.
                    </p>
                    <table class="table table-sm table-borderless small mb-0">
                        <tr>
                            <td class="text-muted">Metode Refund</td>
                            <td class="fw-semibold text-dark text-capitalize">{{ $settlement->refund_method ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tujuan / Rekening</td>
                            <td class="fw-semibold text-dark">{{ $settlement->refund_destination ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">No. Bukti / Ref</td>
                            <td class="text-dark">{{ $settlement->refund_payment_reference ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Waktu Eksekusi</td>
                            <td class="text-dark">{{ $settlement->refunded_at?->isoFormat('D MMM Y, HH:mm') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Petugas</td>
                            <td class="text-dark">{{ $settlement->refunder?->name ?? 'Staf' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif

            <!-- Info Inspeksi Terkait (Phase 3) -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clipboard-check text-teal me-2"></i> Inspeksi Kamar (Phase 3)</h6>
                </div>
                <div class="card-body p-3 small">
                    @if($settlement->inspection)
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Kode Inspeksi</td>
                                <td class="fw-semibold text-dark">{{ $settlement->inspection->inspection_code }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Kondisi Umum</td>
                                <td class="text-capitalize fw-semibold text-dark">{{ $settlement->inspection->overall_condition ?: 'Selesai' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Catatan Petugas</td>
                                <td class="text-muted fst-italic">{{ $settlement->inspection->inspector_notes ?: '-' }}</td>
                            </tr>
                        </table>
                    @else
                        <p class="text-muted mb-0">Tidak ada inspeksi check-out yang dihubungkan.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Finalisasi Check-Out & Refund -->
<div class="modal fade" id="finalizeRefundModal" tabindex="-1" aria-labelledby="finalizeRefundModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="finalizeRefundModalLabel">
                    <i class="bi bi-check2-circle text-success me-2"></i> Finalisasi Check-Out & Refund
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard.deposits.settlements.finalize', $settlement->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Aksi ini akan mencatat pembayaran pengembalian uang jaminan (Rp {{ number_format($settlement->net_refund_amount, 0, ',', '.') }}), menutup masa sewa booking menjadi <strong>Selesai (Completed)</strong>, dan mengalihkan status kamar menjadi <strong>Kotor (Perlu Housekeeping)</strong>.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Metode Pengembalian <span class="text-danger">*</span></label>
                        <select name="refund_method" class="form-select form-select-sm" required>
                            <option value="bank_transfer">Transfer Bank</option>
                            <option value="cash">Tunai (Cash)</option>
                            <option value="e-wallet">E-Wallet (GoPay, OVO, Dana)</option>
                        </select>
                    </div>

                    @if((float)$settlement->net_refund_amount > 0)
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Tujuan / Nomor Rekening Penerima <span class="text-danger">*</span></label>
                        <input type="text" name="refund_destination" class="form-control form-control-sm" value="{{ $settlement->refund_destination }}" placeholder="Contoh: BCA 123456789 a/n Budi" required>
                    </div>
                    @else
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Tidak ada sisa uang jaminan yang perlu dikembalikan (Saldo Refund: Rp 0). Check-out akan langsung menyelesaikan pemesanan dan mengalihkan status kamar ke Kotor (DIRTY).
                    </div>
                    <input type="hidden" name="refund_destination" value="{{ $settlement->refund_destination ?: 'Tanpa Refund' }}">
                    @endif

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nomor Referensi Transfer / Catatan (Opsional)</label>
                        <input type="text" name="refund_payment_reference" class="form-control form-control-sm" placeholder="Nomor referensi / bukti bayar...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success px-3">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi & Selesaikan Check-Out
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
