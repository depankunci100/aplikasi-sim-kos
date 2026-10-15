@extends('layouts.app')

@section('title', "Keluhan #{$complaint->ticket_number} - SIM Kos & Guesthouse")

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('dashboard.complaints.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0">Tiket #{{ $complaint->ticket_number }}</h4>
                @php
                    $stBadge = match($complaint->status->value) {
                        'submitted' => 'bg-secondary text-white',
                        'acknowledged' => 'bg-info text-dark',
                        'in_progress' => 'bg-warning text-dark',
                        'resolved' => 'bg-success text-white',
                        'closed' => 'bg-dark text-white',
                        'reopened' => 'bg-danger text-white',
                        'rejected' => 'bg-danger text-white bg-opacity-75',
                        default => 'bg-light text-dark',
                    };
                @endphp
                <span class="badge {{ $stBadge }} fs-6">{{ $complaint->status->label() }}</span>
            </div>
            <p class="text-muted small mb-0">{{ $complaint->title }}</p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2">
        @if(!auth()->user()->isTenant() && !in_array($complaint->status->value, ['closed', 'rejected']))
            @if($complaint->status->value === 'submitted')
                <form action="{{ route('dashboard.complaints.acknowledge', $complaint->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-eye-check me-1"></i> Terima & Tinjau
                    </button>
                </form>
            @endif

            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="bi bi-person-plus me-1"></i> Tugaskan
            </button>

            @if(!$complaint->maintenance_request_id)
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#linkMaintenanceModal">
                    <i class="bi bi-tools me-1"></i> Buat Tiket Perbaikan
                </button>
            @endif

            @if($complaint->status->value !== 'resolved')
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#resolveModal">
                    <i class="bi bi-check-circle me-1"></i> Tandai Selesai
                </button>
            @endif

            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-circle me-1"></i> Tolak Keluhan
            </button>
        @endif

        @if($complaint->status->value === 'resolved' && (auth()->user()->id === $complaint->user_id || auth()->user()->isSuperAdmin()))
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#confirmCloseModal">
                <i class="bi bi-hand-thumbs-up me-1"></i> Konfirmasi Selesai
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#reopenModal">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Buka Kembali
            </button>
        @endif
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Detail & Percakapan -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle me-2 text-primary-teal"></i>Detail Pengaduan</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small">Properti</label>
                        <div class="fw-semibold text-dark">{{ $complaint->property->name }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Lokasi / Kamar</label>
                        <div class="fw-semibold text-dark">
                            @if($complaint->room)
                                Kamar {{ $complaint->room->room_number }} ({{ $complaint->room->roomType?->name ?? 'Kamar' }})
                            @else
                                <span class="badge bg-light text-dark border">Fasilitas Umum</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Kategori & Prioritas</label>
                        <div>
                            <span class="badge bg-light text-dark border">{{ $complaint->category->label() }}</span>
                            <span class="badge bg-secondary text-white">{{ $complaint->priority->label() }}</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="text-muted small">Pelapor (Tenant)</label>
                        <div class="fw-semibold text-dark">{{ $complaint->user->name }}</div>
                        <div class="small text-muted">{{ $complaint->created_at->format('d M Y, H:i') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Penanggung Jawab</label>
                        <div class="fw-semibold text-dark">{{ $complaint->assignee?->name ?? 'Belum Ditugaskan' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Respon Pertama</label>
                        <div class="fw-semibold text-dark">
                            {{ $complaint->first_responded_at ? $complaint->first_responded_at->format('d M Y, H:i') : 'Belum Direspon' }}
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="text-muted small">Deskripsi Keluhan</label>
                        <div class="p-3 bg-light rounded text-dark">{{ $complaint->description }}</div>
                    </div>

                    @if(!empty($complaint->photos))
                    <div class="col-12">
                        <label class="text-muted small mb-2 d-block">Foto Bukti Lampiran</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($complaint->photos as $photo)
                                <a href="{{ route('dashboard.complaints.photo', ['id' => $complaint->id, 'path' => $photo]) }}" target="_blank">
                                    <img src="{{ route('dashboard.complaints.photo', ['id' => $complaint->id, 'path' => $photo]) }}" class="img-thumbnail" style="width: 120px; height: 120px; object-fit: cover;">
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($complaint->resolution_notes)
                    <div class="col-12">
                        <label class="text-muted small">Catatan Penyelesaian Petugas</label>
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded border border-success">
                            {{ $complaint->resolution_notes }}
                            <div class="small text-muted mt-1">Diselesaikan pada: {{ $complaint->resolved_at?->format('d M Y, H:i') }}</div>
                        </div>
                    </div>
                    @endif

                    @if($complaint->reopen_reason)
                    <div class="col-12">
                        <label class="text-danger small fw-semibold">Alasan Dibuka Kembali oleh Penyewa</label>
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded border border-danger">
                            {{ $complaint->reopen_reason }}
                            <div class="small text-muted mt-1">Dibuka pada: {{ $complaint->reopened_at?->format('d M Y, H:i') }}</div>
                        </div>
                    </div>
                    @endif

                    @if($complaint->rejection_reason)
                    <div class="col-12">
                        <label class="text-danger small fw-semibold">Alasan Penolakan</label>
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded border border-danger">
                            {{ $complaint->rejection_reason }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Thread Komentar / Diskusi -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-chat-dots me-2 text-primary-teal"></i>Percakapan & Tanggapan</h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3 mb-4">
                    @forelse($complaint->comments as $c)
                    <div class="p-3 rounded {{ $c->is_internal ? 'bg-warning bg-opacity-10 border border-warning' : ($c->user_id === $complaint->user_id ? 'bg-light ms-4' : 'bg-teal-subtle me-4') }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold text-dark">
                                {{ $c->user->name }}
                                @if($c->is_internal)
                                    <span class="badge bg-warning text-dark ms-1"><i class="bi bi-lock me-1"></i>Catatan Internal Staf</span>
                                @elseif($c->user_id === $complaint->user_id)
                                    <span class="badge bg-light text-dark border ms-1">Penyewa</span>
                                @else
                                    <span class="badge bg-primary-teal ms-1">Petugas</span>
                                @endif
                            </span>
                            <span class="small text-muted">{{ $c->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="text-dark">{{ $c->comment }}</div>
                        @if(!empty($c->attachments))
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @foreach($c->attachments as $att)
                                <a href="{{ route('dashboard.complaints.photo', ['id' => $complaint->id, 'path' => $att]) }}" target="_blank">
                                    <img src="{{ route('dashboard.complaints.photo', ['id' => $complaint->id, 'path' => $att]) }}" class="img-thumbnail" style="width: 80px; height: 80px; object-fit: cover;">
                                </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @empty
                    <div class="text-center py-4 text-muted fst-italic">Belum ada tanggapan atau komentar.</div>
                    @endforelse
                </div>

                @if(!in_array($complaint->status->value, ['closed', 'rejected']))
                <!-- Form Tambah Komentar -->
                <form action="{{ route('dashboard.complaints.comments', $complaint->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <textarea name="comment" rows="3" class="form-control" placeholder="Tuliskan tanggapan atau pembaruan status..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <input type="file" name="attachments[]" class="form-control form-control-sm" multiple accept="image/jpeg,image/png,image/webp">
                            @if(!auth()->user()->isTenant())
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="internalCheck">
                                <label class="form-check-label small text-muted" for="internalCheck">
                                    Catatan Internal (Hanya Staf)
                                </label>
                            </div>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary-teal btn-sm px-3">
                            <i class="bi bi-send me-1"></i> Kirim
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>

        <!-- Audit Trail Log -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2 text-primary-teal"></i>Riwayat Audit Keluhan</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Waktu</th>
                                <th>Aktor</th>
                                <th>Aksi</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($complaint->logs as $log)
                            <tr>
                                <td class="ps-3 small text-muted">{{ $log->created_at->format('d M Y, H:i') }}</td>
                                <td class="fw-semibold text-dark">{{ $log->user?->name ?? 'Sistem' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ strtoupper($log->action) }}</span></td>
                                <td class="small">{{ $log->notes }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">Belum ada riwayat audit.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Tiket Maintenance & Feedback -->
    <div class="col-lg-4">
        @if($complaint->maintenanceRequest)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-tools me-2 text-primary-teal"></i>Tiket Perbaikan Tertaut</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark font-monospace">{{ $complaint->maintenanceRequest->ticket_number }}</span>
                    <span class="badge bg-info text-dark">{{ $complaint->maintenanceRequest->status->label() }}</span>
                </div>
                <p class="small text-muted mb-3">{{ $complaint->maintenanceRequest->title }}</p>
                <div class="d-flex justify-content-between mb-2 small text-muted">
                    <span>Teknisi:</span>
                    <span class="fw-semibold text-dark">{{ $complaint->maintenanceRequest->assignee?->name ?? 'Belum Ditugaskan' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-3 small text-muted">
                    <span>Biaya Total:</span>
                    <span class="fw-semibold text-dark">Rp {{ number_format((float) $complaint->maintenanceRequest->total_cost, 0, ',', '.') }}</span>
                </div>
                <a href="{{ route('dashboard.maintenance.show', $complaint->maintenanceRequest->id) }}" class="btn btn-outline-primary btn-sm w-100">
                    Buka Detail Maintenance
                </a>
            </div>
        </div>
        @endif

        @if($complaint->rating)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-star-fill text-warning me-2"></i>Penilaian Penyewa</h6>
            </div>
            <div class="card-body">
                <div class="text-warning fs-4 mb-2">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="bi bi-star{{ $i <= $complaint->rating ? '-fill' : '' }}"></i>
                    @endfor
                </div>
                <p class="text-muted small fst-italic mb-0">"{{ $complaint->tenant_feedback ?? 'Tidak ada ulasan teks.' }}"</p>
                <div class="small text-muted mt-2">Dikonfirmasi oleh {{ $complaint->closer?->name }} ({{ $complaint->closed_at?->format('d M Y, H:i') }})</div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Modal Tugaskan -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.complaints.assign', $complaint->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tugaskan Penanganan Keluhan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Petugas / Staf</label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">-- Pilih Staf --</option>
                            @foreach($availableStaff as $stf)
                                <option value="{{ $stf->id }}" {{ $complaint->assigned_to == $stf->id ? 'selected' : '' }}>
                                    {{ $stf->name }} ({{ strtoupper($stf->pivot->role ?? 'staff') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tugaskan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tautkan ke Maintenance -->
<div class="modal fade" id="linkMaintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.complaints.link-maintenance', $complaint->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Buat Tiket Perbaikan dari Keluhan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Keluhan ini memerlukan perbaikan fisik. Sistem akan membuat tiket perbaikan tertaut tanpa membuat duplikat.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori Kerusakan</label>
                        <select name="category" class="form-select" required>
                            @foreach(\App\Enums\MaintenanceCategory::cases() as $cat)
                                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Prioritas</label>
                        <select name="priority" class="form-select" required>
                            @foreach(\App\Enums\MaintenancePriority::cases() as $pri)
                                <option value="{{ $pri->value }}" {{ $complaint->priority->value == $pri->value ? 'selected' : '' }}>{{ $pri->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-teal">Buat & Tautkan Maintenance</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tandai Selesai -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.complaints.resolve', $complaint->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tandai Keluhan Selesai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Penyelesaian <span class="text-danger">*</span></label>
                        <textarea name="resolution_notes" class="form-control" rows="3" placeholder="Jelaskan tindakan yang telah diambil untuk mengatasi keluhan ini..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Selesaikan Keluhan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Selesai (Tenant) -->
<div class="modal fade" id="confirmCloseModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.complaints.confirm-reopen', $complaint->id) }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="confirm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Konfirmasi Penyelesaian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Apakah masalah Anda telah terselesaikan dengan memuaskan? Berikan penilaian untuk pelayanan kami.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rating Kepuasan (1 - 5 Bintang)</label>
                        <select name="rating" class="form-select">
                            <option value="5">⭐⭐⭐⭐⭐ 5 - Sangat Puas</option>
                            <option value="4">⭐⭐⭐⭐ 4 - Puas</option>
                            <option value="3">⭐⭐⭐ 3 - Cukup</option>
                            <option value="2">⭐⭐ 2 - Kurang Puas</option>
                            <option value="1">⭐ 1 - Sangat Kecewa</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kritik / Saran (Opsional)</label>
                        <textarea name="feedback" class="form-control" rows="2" placeholder="Tuliskan ulasan Anda..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success">Konfirmasi & Tutup Tiket</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Buka Kembali (Tenant) -->
<div class="modal fade" id="reopenModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.complaints.confirm-reopen', $complaint->id) }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="reopen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Buka Kembali Keluhan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alasan Membuka Kembali <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Jelaskan mengapa masalah belum tuntas..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Buka Kembali Tiket</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tolak -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.complaints.reject', $complaint->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tolak Keluhan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Tuliskan alasan penolakan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Keluhan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
