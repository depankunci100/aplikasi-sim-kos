@extends('layouts.app')

@section('title', 'Detail Inspeksi - ' . $inspection->inspection_code)

@section('content')
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <a href="{{ route('dashboard.inspections.index') }}" class="text-decoration-none text-muted small">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Inspeksi
        </a>
        <div class="d-flex align-items-center gap-2 mt-1">
            <h4 class="fw-bold text-dark mb-0">{{ $inspection->inspection_code }}</h4>
            <span class="badge {{ $inspection->status->badgeClass() }}">{{ $inspection->status->label() }}</span>
        </div>
        <div class="text-muted small mt-1">
            {{ $inspection->type->label() }} • Kamar {{ $inspection->room->room_number }} • {{ $inspection->property->name }}
        </div>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2 flex-wrap">
        @if($inspection->isScheduled())
        <form method="POST" action="{{ route('dashboard.inspections.start', $inspection->id) }}">
            @csrf
            <button type="submit" class="btn btn-primary shadow-sm">
                <i class="bi bi-play-fill me-1"></i> Mulai Pelaksanaan
            </button>
        </form>
        @endif

        @if($inspection->isInProgress())
        <button type="button" class="btn btn-success text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#completeModal">
            <i class="bi bi-check2-circle me-1"></i> Selesaikan Inspeksi
        </button>
        @endif

        <a href="{{ route('dashboard.inspections.compare', $inspection->id) }}" class="btn btn-outline-teal">
            <i class="bi bi-arrow-left-right me-1"></i> Bandingkan Kondisi
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">
    <!-- Left Column: Checklist & Evidence -->
    <div class="col-lg-8">
        <!-- Checklist Items Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-check2-square text-primary-teal me-2"></i>Checklist Kondisi & Aset Kamar
                </h6>
                <span class="badge bg-light text-muted border">{{ count($inspection->items) }} Item Checklist</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted text-uppercase">
                        <tr>
                            <th>Item & Kategori</th>
                            <th>Kondisi Fisik</th>
                            <th>Catatan Temuan</th>
                            <th>Perbaikan / Biaya</th>
                            @if($inspection->isInProgress())
                            <th class="text-end">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inspection->items as $item)
                        <tr>
                            <td>
                                <span class="fw-bold text-dark">{{ $item->item_name }}</span>
                                <div class="small text-muted">{{ ucfirst($item->category) }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $item->condition->badgeClass() }}">
                                    {{ $item->condition->label() }}
                                </span>
                            </td>
                            <td>
                                <span class="small">{{ $item->notes ?? '-' }}</span>
                            </td>
                            <td>
                                @if($item->repair_required)
                                    <span class="badge bg-danger-subtle text-danger border border-danger">Perlu Perbaikan</span>
                                    <div class="small fw-semibold text-danger">Rp {{ number_format($item->estimated_cost, 0, ',', '.') }}</div>
                                @else
                                    <span class="text-muted small">Normal / Tidak Ada</span>
                                @endif
                            </td>
                            @if($inspection->isInProgress())
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item->id }}">
                                    <i class="bi bi-pencil"></i> Ubah
                                </button>
                            </td>
                            @endif
                        </tr>

                        <!-- Modal Edit Item -->
                        @if($inspection->isInProgress())
                        <div class="modal fade" id="editItemModal{{ $item->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('dashboard.inspections.items.update', ['id' => $inspection->id, 'itemId' => $item->id]) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold">Update: {{ $item->item_name }}</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Kondisi Fisik</label>
                                                <select name="condition" class="form-select" required>
                                                    <option value="good" {{ $item->condition->value == 'good' ? 'selected' : '' }}>Baik / Normal</option>
                                                    <option value="fair" {{ $item->condition->value == 'fair' ? 'selected' : '' }}>Cukup / Aus Wajar</option>
                                                    <option value="poor" {{ $item->condition->value == 'poor' ? 'selected' : '' }}>Kurang Baik</option>
                                                    <option value="damaged" {{ $item->condition->value == 'damaged' ? 'selected' : '' }}>Rusak</option>
                                                    <option value="missing" {{ $item->condition->value == 'missing' ? 'selected' : '' }}>Hilang</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Catatan / Detail Temuan</label>
                                                <textarea name="notes" class="form-control" rows="2" placeholder="Catatan kerusakan atau nomor seri...">{{ $item->notes }}</textarea>
                                            </div>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" name="repair_required" id="repairSwitch{{ $item->id }}" {{ $item->repair_required ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="repairSwitch{{ $item->id }}">Tandai Perlu Perbaikan / Kompensasi</label>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Estimasi Biaya Perbaikan (Rp)</label>
                                                <input type="number" name="estimated_cost" class="form-control" value="{{ (int)$item->estimated_cost }}" min="0" step="1000">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-teal btn-sm text-white">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Belum ada item checklist pada inspeksi ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bukti Foto / Media Evidence Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-camera text-primary-teal me-2"></i>Foto Bukti & Dokumentasi Kamar
                </h6>
                @if($inspection->isInProgress() || $inspection->isScheduled())
                <button type="button" class="btn btn-sm btn-outline-teal" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                    <i class="bi bi-upload me-1"></i> Unggah Foto Bukti
                </button>
                @endif
            </div>
            <div class="card-body p-3">
                @if($inspection->media->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-images fs-2 d-block mb-1"></i>
                        <span>Belum ada foto bukti yang dilampirkan.</span>
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($inspection->media as $media)
                        <div class="col-6 col-md-4">
                            <div class="card h-100 border">
                                <a href="{{ route('dashboard.inspections.media.stream', ['id' => $inspection->id, 'mediaId' => $media->id]) }}" target="_blank">
                                    <img src="{{ route('dashboard.inspections.media.stream', ['id' => $inspection->id, 'mediaId' => $media->id]) }}" class="card-img-top object-fit-cover" style="height: 140px;" alt="{{ $media->caption }}">
                                </a>
                                <div class="card-body p-2">
                                    <div class="small fw-semibold text-truncate">{{ $media->caption ?? $media->file_name }}</div>
                                    @if($media->item)
                                        <span class="badge bg-light text-muted border text-truncate d-inline-block mw-100">{{ $media->item->item_name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Info, Tenant Feedback, & Audit Trail -->
    <div class="col-lg-4">
        <!-- Status Persetujuan Penyewa Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-person-check text-primary-teal me-2"></i>Status Persetujuan Penyewa
                </h6>
            </div>
            <div class="card-body p-3">
                @if($inspection->isApproved())
                    <div class="p-3 bg-success-subtle text-success rounded-3 border border-success mb-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <strong class="small">Telah Disetujui Penyewa</strong>
                        </div>
                        <div class="small text-muted">{{ $inspection->tenant_decision_at?->format('d M Y, H:i') }} WIB</div>
                        @if($inspection->tenant_feedback)
                            <hr class="my-2 border-success-subtle">
                            <div class="small"><em>"{{ $inspection->tenant_feedback }}"</em></div>
                        @endif
                    </div>
                @elseif($inspection->isObjected())
                    <div class="p-3 bg-danger-subtle text-danger rounded-3 border border-danger mb-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                            <strong class="small">Penyewa Mengajukan Keberatan</strong>
                        </div>
                        <div class="small text-muted">{{ $inspection->tenant_decision_at?->format('d M Y, H:i') }} WIB</div>
                        <hr class="my-2 border-danger-subtle">
                        <div class="small"><strong>Alasan Keberatan:</strong><br>{{ $inspection->tenant_feedback }}</div>
                    </div>
                @elseif($inspection->isCompleted())
                    <div class="p-3 bg-warning-subtle text-warning-emphasis rounded-3 border border-warning mb-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-clock-history fs-5"></i>
                            <strong class="small">Menunggu Konfirmasi Penyewa</strong>
                        </div>
                        <div class="small">Laporan telah diteruskan ke aplikasi mobile penyewa untuk disetujui.</div>
                    </div>
                @else
                    <div class="text-muted small">
                        Penyewa akan dapat memverifikasi laporan setelah inspeksi diselesaikan oleh petugas.
                    </div>
                @endif

                <div class="small mt-3">
                    <div class="text-muted">Penyewa Terdaftar:</div>
                    <div class="fw-bold text-dark">{{ $inspection->tenant->name ?? 'Kamar Belum Berpenghuni' }}</div>
                    <div class="text-muted">{{ $inspection->tenant->phone ?? '-' }}</div>
                </div>
            </div>
        </div>

        <!-- Detail Informasi Inspeksi -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-info-circle text-primary-teal me-2"></i>Informasi Operasional
                </h6>
            </div>
            <div class="card-body p-3 small">
                <div class="mb-2">
                    <span class="text-muted d-block">Petugas Lapangan:</span>
                    <strong class="text-dark">{{ $inspection->inspector->name ?? 'Belum Ditugaskan' }}</strong>
                </div>
                <div class="mb-2">
                    <span class="text-muted d-block">Jadwal Tanggal:</span>
                    <strong class="text-dark">{{ $inspection->scheduled_date->format('d M Y') }}</strong>
                </div>
                @if($inspection->started_at)
                <div class="mb-2">
                    <span class="text-muted d-block">Waktu Pelaksanaan:</span>
                    <span>{{ $inspection->started_at->format('d M Y H:i') }} @if($inspection->completed_at) s/d {{ $inspection->completed_at->format('H:i') }} WIB @endif</span>
                </div>
                @endif
                @if($inspection->inspector_notes)
                <div class="mb-2">
                    <span class="text-muted d-block">Catatan Petugas:</span>
                    <p class="mb-0 text-dark bg-light p-2 rounded">{{ $inspection->inspector_notes }}</p>
                </div>
                @endif
                @if($inspection->baselineInspection)
                <div class="mt-3 pt-2 border-top">
                    <span class="text-muted d-block">Inspeksi Baseline (Awal):</span>
                    <a href="{{ route('dashboard.inspections.show', $inspection->baseline_inspection_id) }}" class="text-teal fw-semibold">
                        {{ $inspection->baselineInspection->inspection_code }} ({{ $inspection->baselineInspection->type->label() }})
                    </a>
                </div>
                @endif
            </div>
        </div>

        <!-- Audit Trail Log Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-clock-history text-primary-teal me-2"></i>Riwayat Audit (Audit Trail)
                </h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-unstyled mb-0 small">
                    @forelse($inspection->logs as $log)
                    <li class="mb-3 position-relative ps-3 border-start border-2 border-teal">
                        <div class="fw-semibold text-dark">{{ $log->description }}</div>
                        <div class="text-muted" style="font-size: 11px;">
                            {{ $log->user->name ?? 'Sistem' }} • {{ $log->created_at->format('d M Y H:i:s') }}
                        </div>
                    </li>
                    @empty
                    <li class="text-muted text-center py-2">Belum ada riwayat audit log.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Selesaikan Inspeksi -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('dashboard.inspections.complete', $inspection->id) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Selesaikan Laporan Inspeksi</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kesimpulan Kondisi Keseluruhan <span class="text-danger">*</span></label>
                        <select name="overall_condition" class="form-select" required>
                            <option value="good">Baik (Siap Huni / Bersih)</option>
                            <option value="fair">Cukup (Aus Wajar / Pembersihan Ringan)</option>
                            <option value="poor">Kurang Baik / Rusak (Perlu Perbaikan / Kompensasi)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Rangkuman untuk Penyewa & Pengelola</label>
                        <textarea name="inspector_notes" class="form-control" rows="3" placeholder="Rangkuman temuan fisik, kunci diserahkan, meteran listrik, dll...">{{ $inspection->inspector_notes }}</textarea>
                    </div>
                    <div class="small text-muted bg-light p-2 rounded">
                        <i class="bi bi-info-circle me-1"></i> Setelah diselesaikan, laporan ini otomatis dapat diakses oleh penyewa via aplikasi mobile untuk diverifikasi atau diajukan keberatan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm text-white">Selesaikan & Kirim Laporan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Upload Foto Bukti -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('dashboard.inspections.media.upload', $inspection->id) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Unggah Foto Bukti Inspeksi</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Berkas Foto <span class="text-danger">*</span></label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        <div class="form-text">Maksimal 5MB (Format JPG, PNG, WEBP, PDF).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Terkait Item Checklist (Opsional)</label>
                        <select name="inspection_item_id" class="form-select">
                            <option value="">-- Foto Umum Kamar / Keseluruhan --</option>
                            @foreach($inspection->items as $item)
                                <option value="{{ $item->id }}">{{ $item->item_name }} ({{ $item->condition->label() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Keterangan / Caption Foto</label>
                        <input type="text" name="caption" class="form-control" placeholder="Contoh: Goresan pada pintu kamar mandi...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm text-white">Unggah Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
