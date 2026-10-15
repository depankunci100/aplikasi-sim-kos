@extends('layouts.app')

@section('title', 'Detail Pembacaan Meter #' . $reading->id)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('dashboard.utilities.readings.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Pembacaan Meter #{{ $reading->id }}</h4>
            <span class="text-muted small">
                {{ $reading->property->name ?? '-' }} &bull;
                {{ $reading->room ? 'Kamar ' . $reading->room->room_number : 'Meter Bersama' }} &bull;
                Tanggal: {{ $reading->reading_date->format('d/m/Y') }}
            </span>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <!-- Jika Masih Pending Review -->
        @if($reading->status->value === 'pending_review' && !auth()->user()->isTenant() && !auth()->user()->isStaff())
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalReject">
                <i class="bi bi-x-circle me-1"></i> Tolak Pembacaan
            </button>
            <form action="{{ route('dashboard.utilities.readings.review', $reading->id) }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check-circle me-1"></i> Setujui Pembacaan
                </button>
            </form>
        @endif

        <!-- Jika Disetujui & Belum Ditagihkan -->
        @if($reading->status->value === 'approved' && !$reading->is_billed && !auth()->user()->isTenant() && !auth()->user()->isStaff())
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCorrect">
                <i class="bi bi-pencil-square me-1"></i> Koreksi Nilai
            </button>
            <button type="button" class="btn btn-primary-teal btn-sm" data-bs-toggle="modal" data-bs-target="#modalGenerateInvoice">
                <i class="bi bi-receipt me-1"></i> Terbitkan Tagihan (Invoice)
            </button>
        @endif

        @if($reading->is_billed && $reading->invoice)
            <a href="{{ route('dashboard.invoices.show', $reading->invoice_id) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-receipt me-1"></i> Lihat Invoice (#{{ $reading->invoice->invoice_number }})
            </a>
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Kolom Rincian Perhitungan -->
    <div class="col-lg-7">
        <div class="card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <h6 class="fw-bold text-dark mb-0">Rincian Pembacaan & Biaya</h6>
                <div>
                    @if($reading->status->value === 'approved')
                        <span class="badge bg-success-subtle text-success fs-6">Disetujui</span>
                    @elseif($reading->status->value === 'pending_review')
                        <span class="badge bg-warning-subtle text-warning fs-6">Menunggu Review</span>
                    @elseif($reading->status->value === 'rejected')
                        <span class="badge bg-danger-subtle text-danger fs-6">Ditolak</span>
                    @else
                        <span class="badge bg-light text-dark border fs-6">Draf</span>
                    @endif

                    @if($reading->is_billed)
                        <span class="badge bg-primary fs-6 ms-1">Billed (Sudah Ditagihkan)</span>
                    @endif
                </div>
            </div>

            @if($reading->status->value === 'rejected')
            <div class="alert alert-danger py-2 px-3 small mb-3">
                <strong>Alasan Penolakan:</strong> {{ $reading->rejection_reason }}
            </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Meter Terkait:</span>
                    <strong class="text-dark">{{ $reading->meter->meter_number ?? '-' }}</strong>
                    <div class="small text-muted">{{ $reading->utility_type->label() }}</div>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Kamar & Penyewa:</span>
                    <strong class="text-dark">{{ $reading->room ? 'Kamar ' . $reading->room->room_number : 'Meter Bersama' }}</strong>
                    <div class="small text-muted">{{ $reading->booking?->user?->name ?? 'Penyewa Kamar' }}</div>
                </div>
            </div>

            <!-- Kalkulasi Metrik -->
            <div class="p-3 bg-light rounded border mb-3">
                <div class="row text-center g-2 align-items-center">
                    <div class="col-4">
                        <span class="text-muted small d-block">Sebelumnya / Baseline</span>
                        <span class="fs-5 fw-bold text-secondary">{{ number_format($reading->previous_meter, 2) }}</span>
                    </div>
                    <div class="col-4 border-start border-end">
                        <span class="text-muted small d-block">Saat Ini</span>
                        <span class="fs-5 fw-bold text-dark">{{ number_format($reading->current_meter, 2) }}</span>
                    </div>
                    <div class="col-4">
                        <span class="text-muted small d-block">Pemakaian Bersih</span>
                        <span class="fs-4 fw-bold text-teal">{{ number_format($reading->usage, 2) }}</span>
                        <span class="small text-muted">{{ $reading->meter->unit ?? '' }}</span>
                    </div>
                </div>
            </div>

            <!-- Kalkulasi Biaya -->
            <ul class="list-group list-group-flush small mb-3">
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Tarif Berlaku (Snapshot):</span>
                    <strong class="text-dark">Rp {{ number_format($reading->rate_per_unit, 0, ',', '.') }} / {{ $reading->meter->unit ?? '' }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Periode Pemakaian:</span>
                    <span class="text-dark">{{ $reading->period_start ? $reading->period_start->format('d/m/Y') : '-' }} s/d {{ $reading->period_end ? $reading->period_end->format('d/m/Y') : '-' }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Petugas Pencatat:</span>
                    <span class="text-dark">{{ $reading->recorder->name ?? '-' }} ({{ $reading->created_at->format('d/m/Y H:i') }})</span>
                </li>
                @if($reading->reviewer)
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-muted">Ditinjau Oleh:</span>
                    <span class="text-dark">{{ $reading->reviewer->name }} ({{ $reading->reviewed_at ? $reading->reviewed_at->format('d/m/Y H:i') : '-' }})</span>
                </li>
                @endif
                <li class="list-group-item d-flex justify-content-between px-0 fs-6 bg-light py-2 px-2 mt-2 rounded">
                    <strong class="text-dark">Total Biaya Tagihan:</strong>
                    <strong class="text-primary-teal fs-5">Rp {{ number_format($reading->total_cost, 0, ',', '.') }}</strong>
                </li>
            </ul>

            @if($reading->notes)
            <div class="small text-muted p-2 bg-light rounded">
                <strong>Catatan:</strong> {{ $reading->notes }}
            </div>
            @endif
        </div>

        <!-- Audit Trail Timeline -->
        <div class="card p-4">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Jejak Audit (Audit Trail)</h6>
            <div class="timeline">
                @forelse($reading->logs as $log)
                <div class="d-flex gap-3 mb-3 pb-2 border-bottom">
                    <div class="text-teal fs-5">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-dark text-uppercase">{{ $log->action }}</span>
                            <span class="text-muted small" style="font-size: 0.75rem;">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="small text-muted">{{ $log->notes }}</div>
                        @if($log->user)
                            <div class="small text-muted" style="font-size: 0.75rem;">Aktor: {{ $log->user->name }}</div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-muted small">Belum ada catatan log tambahan.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Kolom Foto Bukti Fisik -->
    <div class="col-lg-5">
        <div class="card p-4 h-100">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Foto Bukti Fisik Meter</h6>
            @if($reading->proof_photo)
                <div class="text-center mb-3">
                    <img src="{{ route('dashboard.utilities.readings.proof', $reading->id) }}" alt="Foto Bukti Meter" class="img-fluid rounded border shadow-sm" style="max-height: 420px; object-fit: contain;">
                </div>
                <div class="d-grid">
                    <a href="{{ route('dashboard.utilities.readings.proof', $reading->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka Foto Resolusi Penuh
                    </a>
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-camera-slash fs-1 text-secondary opacity-50 d-block mb-2"></i>
                    Tidak ada lampiran foto bukti untuk pembacaan ini.
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal Penolakan -->
<div class="modal fade" id="modalReject" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('dashboard.utilities.readings.review', $reading->id) }}" method="POST">
                @csrf
                <input type="hidden" name="status" value="rejected">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark">Tolak Pembacaan Meter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        Pastikan Anda mencantumkan alasan penolakan yang jelas agar staf pencatat dapat melakukan pemeriksaan ulang.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" rows="3" class="form-control" placeholder="Contoh: Foto bukti buram atau angka tidak sesuai display meter" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pembacaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Koreksi -->
<div class="modal fade" id="modalCorrect" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('dashboard.utilities.readings.correct', $reading->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark">Koreksi Nilai Pembacaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        Setiap koreksi akan disimpan permanen dalam jejak audit beserta nilai lama, nilai baru, dan nama aktor koreksi.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nilai Pembacaan Sebelumnya</label>
                        <input type="text" class="form-control bg-light" value="{{ number_format($reading->previous_meter, 2) }} {{ $reading->meter->unit ?? '' }}" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nilai Koreksi Baru <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="current_meter" class="form-control" value="{{ (float)$reading->current_meter }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alasan Koreksi <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="2" class="form-control" placeholder="Contoh: Koreksi salah ketik digit terakhir saat input di lapangan" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-teal">Simpan Koreksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Generate Invoice -->
<div class="modal fade" id="modalGenerateInvoice" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('dashboard.utilities.readings.generate-invoice', $reading->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark">Terbitkan Tagihan Utilitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success small mb-3">
                        <strong>Pratinjau Tagihan:</strong><br>
                        Biaya: <strong>Rp {{ number_format($reading->total_cost, 0, ',', '.') }}</strong> ({{ $reading->usage }} {{ $reading->meter->unit ?? '' }} @ Rp {{ number_format($reading->rate_per_unit, 0, ',', '.') }})<br>
                        Penyewa: <strong>{{ $reading->booking?->user?->name ?? 'Penyewa Terdaftar' }}</strong>
                    </div>

                    @if(count($eligibleInvoices) > 0)
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Metode Penerbitan Tagihan</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="billing_mode" id="modeNew" value="new" checked onclick="document.getElementById('selectExistingInvoice').style.display = 'none';">
                            <label class="form-check-label small" for="modeNew">
                                Buat Invoice Utilitas Baru (Terpisah)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="billing_mode" id="modeExisting" value="existing" onclick="document.getElementById('selectExistingInvoice').style.display = 'block';">
                            <label class="form-check-label small" for="modeExisting">
                                Lampirkan ke Invoice Sewa yang Sedang Berjalan
                            </label>
                        </div>
                    </div>

                    <div class="mb-3" id="selectExistingInvoice" style="display: none;">
                        <label class="form-label small fw-semibold">Pilih Invoice Tujuan</label>
                        <select name="target_invoice_id" class="form-select">
                            <option value="">-- Pilih Invoice --</option>
                            @foreach($eligibleInvoices as $inv)
                                <option value="{{ $inv->id }}">
                                    #{{ $inv->invoice_number }} &mdash; Total Saat Ini: Rp {{ number_format($inv->total_amount, 0, ',', '.') }} (Sisa: Rp {{ number_format($inv->remaining_balance, 0, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Metode Penerbitan</label>
                        <input type="text" class="form-control bg-light" value="Buat Invoice Khusus Utilitas Baru" disabled>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-teal">
                        <i class="bi bi-receipt me-1"></i> Terbitkan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
