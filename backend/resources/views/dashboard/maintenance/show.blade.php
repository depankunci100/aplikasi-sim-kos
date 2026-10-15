@extends('layouts.app')

@section('title', "Detail Perbaikan {$maintenance->ticket_number} - SIM Kos & Guesthouse")

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('dashboard.maintenance.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0">Tiket #{{ $maintenance->ticket_number }}</h4>
                @php
                    $statusBadge = match($maintenance->status->value) {
                        'reported' => 'bg-secondary text-white',
                        'triaged' => 'bg-info text-dark',
                        'assigned' => 'bg-primary text-white',
                        'in_progress' => 'bg-warning text-dark',
                        'pending_verification' => 'bg-purple text-white bg-opacity-75',
                        'completed' => 'bg-success text-white',
                        'cancelled' => 'bg-dark text-white',
                        'rejected' => 'bg-danger text-white',
                        default => 'bg-light text-dark',
                    };
                @endphp
                <span class="badge {{ $statusBadge }} fs-6">{{ $maintenance->status->label() }}</span>
                @if($maintenance->is_overdue)
                    <span class="badge bg-danger"><i class="bi bi-clock-history"></i> Overdue (Terlambat)</span>
                @endif
            </div>
            <p class="text-muted small mb-0">{{ $maintenance->title }}</p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2">
        @if(!in_array($maintenance->status->value, ['completed', 'cancelled', 'rejected']))
            @if(!auth()->user()->isTenant())
                <!-- Tombol Triase -->
                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#triageModal">
                    <i class="bi bi-pencil-square me-1"></i> Triase
                </button>

                <!-- Tombol Tugaskan -->
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal">
                    <i class="bi bi-person-plus me-1"></i> Tugaskan
                </button>
            @endif

            <!-- Tombol Mulai Kerja -->
            @if(in_array($maintenance->status->value, ['reported', 'triaged', 'assigned']) && !auth()->user()->isTenant())
                <form action="{{ route('dashboard.maintenance.start', $maintenance->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Mulai pengerjaan perbaikan ini sekarang?')">
                        <i class="bi bi-play-circle me-1"></i> Mulai Kerja
                    </button>
                </form>
            @endif

            <!-- Tombol Serahkan Hasil (Teknisi) -->
            @if($maintenance->status->value === 'in_progress' && !auth()->user()->isTenant())
                <button type="button" class="btn btn-primary-teal btn-sm" data-bs-toggle="modal" data-bs-target="#submitVerificationModal">
                    <i class="bi bi-check2-circle me-1"></i> Serahkan Hasil Perbaikan
                </button>
            @endif

            <!-- Tombol Verifikasi Selesai -->
            @if($maintenance->status->value === 'pending_verification')
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#verifyModal">
                    <i class="bi bi-patch-check-fill me-1"></i> Verifikasi & Selesai
                </button>
            @endif

            <!-- Tombol Tolak / Batalkan -->
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectCancelModal">
                <i class="bi bi-x-circle me-1"></i> Batalkan / Tolak
            </button>
        @endif
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Informasi Utama -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle me-2 text-primary-teal"></i>Rincian Kerusakan & Perbaikan</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="text-muted small">Properti</label>
                        <div class="fw-semibold text-dark">{{ $maintenance->property->name }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Lokasi / Kamar</label>
                        <div class="fw-semibold text-dark">
                            @if($maintenance->room)
                                Kamar {{ $maintenance->room->room_number }} ({{ $maintenance->room->roomType?->name ?? 'Kamar' }})
                            @else
                                <span class="badge bg-light text-dark border">Area Bersama</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Kategori & Prioritas</label>
                        <div>
                            <span class="badge bg-light text-dark border">{{ $maintenance->category->label() }}</span>
                            <span class="badge bg-secondary text-white">{{ $maintenance->priority->label() }}</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="text-muted small">Pelapor</label>
                        <div class="fw-semibold text-dark">{{ $maintenance->reporter->name }}</div>
                        <div class="small text-muted">{{ $maintenance->created_at->format('d M Y, H:i') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Teknisi Bertugas</label>
                        <div class="fw-semibold text-dark">{{ $maintenance->assignee?->name ?? 'Belum Ditugaskan' }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Batas Waktu (SLA)</label>
                        <div class="fw-semibold {{ $maintenance->is_overdue ? 'text-danger' : 'text-dark' }}">
                            {{ $maintenance->sla_due_at ? $maintenance->sla_due_at->format('d M Y, H:i') : '-' }}
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="text-muted small">Deskripsi Masalah</label>
                        <div class="p-3 bg-light rounded text-dark">{{ $maintenance->description }}</div>
                    </div>

                    @if($maintenance->diagnosis_notes)
                    <div class="col-12">
                        <label class="text-muted small">Catatan Diagnosis Teknisi</label>
                        <div class="p-3 bg-light rounded text-dark border-start border-4 border-info">
                            {{ $maintenance->diagnosis_notes }}
                        </div>
                    </div>
                    @endif

                    @if($maintenance->action_taken)
                    <div class="col-12">
                        <label class="text-muted small">Tindakan Perbaikan yang Telah Dilakukan</label>
                        <div class="p-3 bg-light rounded text-dark border-start border-4 border-success">
                            {{ $maintenance->action_taken }}
                        </div>
                    </div>
                    @endif

                    @if($maintenance->verification_notes)
                    <div class="col-12">
                        <label class="text-muted small">Catatan Verifikasi</label>
                        <div class="p-3 bg-light rounded text-dark border-start border-4 border-primary">
                            {{ $maintenance->verification_notes }}
                            <div class="small text-muted mt-1">Diverifikasi oleh: {{ $maintenance->verifier?->name }} ({{ $maintenance->verified_at?->format('d M Y, H:i') }})</div>
                        </div>
                    </div>
                    @endif

                    @if($maintenance->rejection_reason || $maintenance->cancellation_reason)
                    <div class="col-12">
                        <label class="text-danger small fw-semibold">Alasan Penolakan / Pembatalan</label>
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded border border-danger">
                            {{ $maintenance->rejection_reason ?? $maintenance->cancellation_reason }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Foto Bukti Sebelum & Sesudah -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-images me-2 text-primary-teal"></i>Dokumentasi Foto Perbaikan</h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="small fw-bold text-muted mb-3"><i class="bi bi-camera me-1"></i>Foto Kondisi Awal (Sebelum)</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse($maintenance->photos_before ?? [] as $photo)
                                <a href="{{ route('dashboard.maintenance.photo', ['id' => $maintenance->id, 'path' => $photo]) }}" target="_blank">
                                    <img src="{{ route('dashboard.maintenance.photo', ['id' => $maintenance->id, 'path' => $photo]) }}" class="img-thumbnail" style="width: 130px; height: 130px; object-fit: cover;">
                                </a>
                            @empty
                                <div class="text-muted small fst-italic">Tidak ada foto kondisi awal.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="small fw-bold text-muted mb-3"><i class="bi bi-check-circle me-1"></i>Foto Hasil Perbaikan (Sesudah)</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse($maintenance->photos_after ?? [] as $photo)
                                <a href="{{ route('dashboard.maintenance.photo', ['id' => $maintenance->id, 'path' => $photo]) }}" target="_blank">
                                    <img src="{{ route('dashboard.maintenance.photo', ['id' => $maintenance->id, 'path' => $photo]) }}" class="img-thumbnail" style="width: 130px; height: 130px; object-fit: cover;">
                                </a>
                            @empty
                                <div class="text-muted small fst-italic">Belum ada foto setelah perbaikan.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Trail Log -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2 text-primary-teal"></i>Riwayat Audit & Perubahan Status</h6>
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
                            @forelse($maintenance->logs as $log)
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

    <!-- Kolom Kanan: Rincian Biaya & Komponen Finansial -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-cash-stack me-2 text-primary-teal"></i>Rincian Biaya Perbaikan</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Biaya Tenaga Kerja:</span>
                    <span class="fw-semibold">Rp {{ number_format((float) $maintenance->labor_cost, 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Biaya Bahan / Material:</span>
                    <span class="fw-semibold">Rp {{ number_format((float) $maintenance->material_cost, 0, ',', '.') }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span class="fw-bold text-dark">Total Biaya:</span>
                    <span class="fw-bold text-primary-teal fs-5">Rp {{ number_format((float) $maintenance->total_cost, 0, ',', '.') }}</span>
                </div>

                @if(!empty($maintenance->spare_parts))
                    <div class="mt-3">
                        <label class="small text-muted fw-semibold">Suku Cadang / Material yang Digunakan:</label>
                        <div class="small p-2 bg-light rounded text-dark">
                            @if(is_array($maintenance->spare_parts))
                                <ul class="mb-0 ps-3">
                                    @foreach($maintenance->spare_parts as $sp)
                                        <li>{{ is_array($sp) ? json_encode($sp) : $sp }}</li>
                                    @endforeach
                                </ul>
                            @else
                                {{ $maintenance->spare_parts }}
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if($maintenance->complaint)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-chat-left-dots me-2 text-primary-teal"></i>Tertaut ke Keluhan</h6>
            </div>
            <div class="card-body">
                <div class="fw-semibold text-dark">Tiket #{{ $maintenance->complaint->ticket_number }}</div>
                <p class="small text-muted mb-2">{{ $maintenance->complaint->title }}</p>
                <a href="{{ route('dashboard.complaints.show', $maintenance->complaint->id) }}" class="btn btn-outline-primary btn-sm w-100">
                    Buka Detail Keluhan
                </a>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Modal Triase -->
<div class="modal fade" id="triageModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.maintenance.triage', $maintenance->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Triase Tiket Perbaikan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori Kerusakan</label>
                        <select name="category" class="form-select">
                            @foreach(\App\Enums\MaintenanceCategory::cases() as $cat)
                                <option value="{{ $cat->value }}" {{ $maintenance->category === $cat ? 'selected' : '' }}>{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Prioritas</label>
                        <select name="priority" class="form-select">
                            @foreach(\App\Enums\MaintenancePriority::cases() as $pri)
                                <option value="{{ $pri->value }}" {{ $maintenance->priority === $pri ? 'selected' : '' }}>{{ $pri->label() }} (SLA: {{ $pri->slaHours() }} Jam)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jadwal Perbaikan</label>
                        <input type="date" name="scheduled_date" class="form-control" value="{{ $maintenance->scheduled_date?->format('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Triase</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan analisis awal"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-teal">Simpan Triase</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Penugasan Teknisi -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.maintenance.assign', $maintenance->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tugaskan Teknisi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Teknisi / Staf</label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">-- Pilih Staf Teknisi --</option>
                            @foreach($availableStaff as $stf)
                                <option value="{{ $stf->id }}" {{ $maintenance->assigned_to == $stf->id ? 'selected' : '' }}>
                                    {{ $stf->name }} ({{ strtoupper($stf->pivot->role ?? 'staff') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jadwal Tanggal</label>
                        <input type="date" name="scheduled_date" class="form-control" value="{{ $maintenance->scheduled_date?->format('Y-m-d') ?? now()->toDateString() }}">
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

<!-- Modal Serahkan Hasil Perbaikan -->
<div class="modal fade" id="submitVerificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('dashboard.maintenance.submit-verification', $maintenance->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Serahkan Hasil Perbaikan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tindakan Perbaikan yang Dilakukan <span class="text-danger">*</span></label>
                        <textarea name="action_taken" class="form-control" rows="3" placeholder="Contoh: Mengganti fitting lampu baru, memasang saklar baru, dan menguji kelistrikan." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Diagnosis Tambahan</label>
                        <textarea name="diagnosis_notes" class="form-control" rows="2" placeholder="Penyebab kerusakan"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Suku Cadang / Material</label>
                        <input type="text" name="spare_parts" class="form-control" placeholder="Contoh: 1x Fitting Lampu Broco, 1x Bohlam LED 15W">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Biaya Tenaga Kerja (Rp)</label>
                            <input type="number" step="1000" name="labor_cost" class="form-control" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Biaya Material / Komponen (Rp)</label>
                            <input type="number" step="1000" name="material_cost" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Unggah Foto Hasil Perbaikan (Sesudah)</label>
                        <input type="file" name="photos_after[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-teal">Serahkan untuk Verifikasi</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Verifikasi & Selesai -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.maintenance.verify', $maintenance->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Verifikasi & Setujui Selesai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Pastikan kondisi fisik hasil perbaikan telah dicek dan berfungsi normal.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Verifikasi <span class="text-danger">*</span></label>
                        <textarea name="verification_notes" class="form-control" rows="3" placeholder="Contoh: Lampu telah menyala terang, saklar bekerja dengan baik, area kerja bersih." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Setujui & Selesaikan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tolak / Batalkan -->
<div class="modal fade" id="rejectCancelModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.maintenance.reject-cancel', $maintenance->id) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Batalkan atau Tolak Tiket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Tindakan <span class="text-danger">*</span></label>
                        <select name="action" class="form-select" required>
                            <option value="cancel">Batalkan Tiket</option>
                            <option value="reject">Tolak Laporan Kerusakan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alasan Penolakan / Pembatalan <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Tuliskan alasan jelas mengapa tiket ini ditolak/dibatalkan" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger">Kirim Tindakan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
