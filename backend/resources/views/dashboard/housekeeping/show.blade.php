@extends('layouts.app')

@section('title', 'Detail Tugas Kebersihan #' . $task->task_number . ' - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <a href="{{ route('dashboard.housekeeping.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0">Tugas #{{ $task->task_number }}</h4>
                @php
                    $badgeClass = match($task->status->value) {
                        'scheduled' => 'bg-secondary',
                        'assigned' => 'bg-info text-dark',
                        'in_progress' => 'bg-primary',
                        'pending_verification' => 'bg-warning text-dark',
                        'completed' => 'bg-success',
                        'cancelled' => 'bg-danger',
                        default => 'bg-light text-dark',
                    };
                @endphp
                <span class="badge {{ $badgeClass }} fs-6">{{ $task->status->label() }}</span>
                @if($task->priority->value === 'urgent')
                    <span class="badge bg-danger">Urgent</span>
                @elseif($task->priority->value === 'high')
                    <span class="badge bg-warning text-dark">High</span>
                @endif
            </div>
            <p class="text-muted small mb-0">{{ $task->property->name }} &bull; {{ $task->room ? 'Kamar ' . $task->room->room_number : ($task->area_name ?? 'Area Bersama') }}</p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex gap-2">
        @if(in_array($task->status->value, ['scheduled', 'assigned']) && (auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner') || auth()->user()->hasRole('staff')))
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="bi bi-person-plus me-1"></i>{{ $task->assigned_to ? 'Tugaskan Ulang' : 'Tugaskan Staf' }}
            </button>
        @endif

        @if($task->status->value === 'assigned' && ($task->assigned_to === auth()->id() || auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner') || auth()->user()->hasRole('staff')))
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#startModal">
                <i class="bi bi-play-circle me-1"></i>Mulai Bersihkan
            </button>
        @endif

        @if($task->status->value === 'pending_verification' && (auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner')))
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#verifyModal">
                <i class="bi bi-check2-circle me-1"></i>Verifikasi Hasil
            </button>
        @endif

        @if(!in_array($task->status->value, ['completed', 'cancelled']) && (auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner')))
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal">
                <i class="bi bi-x-circle me-1"></i>Batalkan
            </button>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <!-- Left Column: Details & Checklist -->
    <div class="col-lg-8">
        <!-- Main Information Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Informasi Tugas</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Tipe Kebersihan</label>
                        <div class="fw-semibold">{{ $task->type->label() }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Jadwal Pelaksanaan</label>
                        <div class="fw-semibold">
                            {{ $task->scheduled_date ? $task->scheduled_date->format('d F Y') : '-' }}
                            <span class="text-muted small">({{ $task->scheduled_slot ?? 'Fleksibel' }})</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Petugas Pelaksana</label>
                        <div class="fw-semibold">
                            @if($task->assignee)
                                <i class="bi bi-person-fill text-muted me-1"></i>{{ $task->assignee->name }}
                            @else
                                <span class="text-muted">Belum ada petugas</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Dibuat Oleh</label>
                        <div class="fw-semibold">{{ $task->assigner->name ?? 'Sistem' }} &bull; {{ $task->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                    @if($task->notes)
                    <div class="col-12">
                        <label class="text-muted small">Catatan / Instruksi</label>
                        <div class="p-3 bg-light rounded text-secondary">{{ $task->notes }}</div>
                    </div>
                    @endif
                    @if($task->damaged_or_missing_items)
                    <div class="col-12">
                        <label class="text-danger small fw-bold"><i class="bi bi-exclamation-octagon me-1"></i>Laporan Barang Rusak / Hilang</label>
                        <div class="p-3 bg-danger-subtle text-danger-emphasis rounded border border-danger-subtle">
                            {{ $task->damaged_or_missing_items }}
                        </div>
                    </div>
                    @endif
                    @if($task->verifier)
                    <div class="col-12">
                        <div class="p-3 bg-success-subtle text-success-emphasis rounded border border-success-subtle">
                            <div class="fw-bold"><i class="bi bi-shield-check me-1"></i>Diverifikasi Oleh: {{ $task->verifier->name }} ({{ $task->verified_at?->format('d/m/Y H:i') }})</div>
                            <div class="small mt-1">{{ $task->verification_notes ?? 'Pekerjaan dinyatakan sesuai standar kebersihan.' }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Checklist Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-check2-square text-primary-teal me-2"></i>Checklist Kebersihan</h5>
                @php
                    $checklist = $task->checklist ?? [];
                    $total = count($checklist);
                    $done = count(array_filter($checklist, fn($item) => !empty($item['is_done'])));
                @endphp
                <span class="badge bg-primary-subtle text-primary">{{ $done }}/{{ $total }} Selesai</span>
            </div>
            <div class="card-body">
                @if($task->status->value === 'in_progress' && ($task->assigned_to === auth()->id() || auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner') || auth()->user()->hasRole('staff')))
                <!-- Form Submit Verification saat In Progress -->
                <form action="{{ route('dashboard.housekeeping.submitVerification', $task->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="list-group mb-4">
                        @foreach($checklist as $item)
                        <label class="list-group-item d-flex align-items-center gap-3">
                            <input class="form-check-input flex-shrink-0" type="checkbox" name="checklist_done[]" value="{{ $item['id'] }}" {{ !empty($item['is_done']) ? 'checked' : '' }}>
                            <span>
                                <div class="fw-semibold">{{ $item['task'] ?? '' }}</div>
                                <small class="text-muted">{{ ucfirst($item['category'] ?? 'Umum') }}</small>
                            </span>
                        </label>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Temuan Barang Rusak / Hilang (Bila Ada)</label>
                        <textarea name="damaged_or_missing_items" class="form-control" rows="2" placeholder="Contoh: Remote AC pecah, handuk hilang 1 pcs">{{ old('damaged_or_missing_items', $task->damaged_or_missing_items) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan Pengerjaan Petugas</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan kebersihan tambahan...">{{ old('notes', $task->notes) }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Foto Bukti Setelah Pembersihan (After Photos)</label>
                        <input type="file" name="photos_after[]" class="form-control" multiple accept="image/*">
                        <small class="text-muted">Maksimal 5 MB per foto (JPEG, PNG, WEBP).</small>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold">
                        <i class="bi bi-arrow-up-right-circle me-1"></i>Serahkan untuk Verifikasi Supervisor
                    </button>
                </form>
                @else
                <!-- Read-only checklist -->
                <div class="list-group list-group-flush">
                    @forelse($checklist as $item)
                    <div class="list-group-item px-0 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            @if(!empty($item['is_done']))
                                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                <span class="text-decoration-line-through text-muted">{{ $item['task'] }}</span>
                            @else
                                <i class="bi bi-circle text-muted fs-5"></i>
                                <span>{{ $item['task'] }}</span>
                            @endif
                        </div>
                        <span class="badge bg-light text-muted border">{{ ucfirst($item['category'] ?? 'Umum') }}</span>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">Tidak ada item checklist.</p>
                    @endforelse
                </div>
                @endif
            </div>
        </div>

        <!-- Photos Evidence Gallery -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-camera text-primary-teal me-2"></i>Bukti Foto Sebelum & Sesudah</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6 border-end">
                        <h6 class="fw-bold text-muted mb-3"><i class="bi bi-clock-history me-1"></i>Kondisi Sebelum (Before)</h6>
                        @if(!empty($task->photos_before))
                            <div class="row g-2">
                                @foreach($task->photos_before as $photo)
                                <div class="col-6">
                                    <a href="{{ route('dashboard.housekeeping.photo', ['id' => $task->id, 'path' => $photo]) }}" target="_blank">
                                        <img src="{{ route('dashboard.housekeeping.photo', ['id' => $task->id, 'path' => $photo]) }}"
                                             class="img-fluid rounded border shadow-sm" style="height: 120px; width: 100%; object-fit: cover;" alt="Foto Sebelum">
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted small fst-italic">Belum ada foto sebelum pembersihan.</p>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted mb-3"><i class="bi bi-check-circle me-1"></i>Kondisi Sesudah (After)</h6>
                        @if(!empty($task->photos_after))
                            <div class="row g-2">
                                @foreach($task->photos_after as $photo)
                                <div class="col-6">
                                    <a href="{{ route('dashboard.housekeeping.photo', ['id' => $task->id, 'path' => $photo]) }}" target="_blank">
                                        <img src="{{ route('dashboard.housekeeping.photo', ['id' => $task->id, 'path' => $photo]) }}"
                                             class="img-fluid rounded border shadow-sm" style="height: 120px; width: 100%; object-fit: cover;" alt="Foto Sesudah">
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted small fst-italic">Belum ada foto setelah pembersihan.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Audit Trail & Timestamps -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock me-2"></i>Waktu & Progres</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Jadwal:</span>
                        <span class="fw-semibold">{{ $task->scheduled_date?->format('d/m/Y') ?? '-' }}</span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Dimulai Pada:</span>
                        <span class="fw-semibold">{{ $task->started_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Selesai Dikerjakan:</span>
                        <span class="fw-semibold">{{ $task->completed_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted">Diverifikasi:</span>
                        <span class="fw-semibold">{{ $task->verified_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Activity Audit Trail -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Riwayat Aktivitas</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush small">
                    @forelse($task->logs as $log)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold text-dark">{{ $log->user->name ?? 'Sistem' }}</span>
                            <span class="text-muted font-monospace">{{ $log->created_at->format('d/m H:i') }}</span>
                        </div>
                        <div class="text-secondary">{{ $log->notes }}</div>
                    </div>
                    @empty
                    <div class="p-3 text-muted text-center">Belum ada catatan riwayat.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tugaskan Staf -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.housekeeping.assign', $task->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tugaskan Petugas Kebersihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Pilih Staf Kebersihan</label>
                    <select name="assigned_to" class="form-select" required>
                        <option value="">-- Pilih Staf --</option>
                        @foreach($availableStaff as $staff)
                            <option value="{{ $staff->id }}" {{ $task->assigned_to == $staff->id ? 'selected' : '' }}>
                                {{ $staff->name }} ({{ $staff->pivot->role ?? 'Staff' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-primary-teal">Simpan Penugasan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Mulai Tugas -->
<div class="modal fade" id="startModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.housekeeping.start', $task->id) }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Mulai Tugas Kebersihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Status tugas akan berubah menjadi <span class="badge bg-primary">In Progress</span>.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Upload Foto Sebelum Pembersihan (Opsional)</label>
                    <input type="file" name="photos_before[]" class="form-control" multiple accept="image/*">
                    <small class="text-muted">Foto kondisi awal ruangan sebelum dibersihkan.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Mulai Sekarang</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Verifikasi Tugas -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.housekeeping.verify', $task->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Verifikasi Hasil Kebersihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Pastikan checklist dan foto hasil pembersihan sudah diperiksa dengan saksama sesuai standar SOP properti.</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Catatan Evaluasi / Verifikasi <span class="text-danger">*</span></label>
                    <textarea name="verification_notes" class="form-control" rows="3" required placeholder="Contoh: Kamar telah dicek rapi, bersih, dan harum. Sprei telah diganti."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">Verifikasi & Selesai</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Batalkan Tugas -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.housekeeping.cancel', $task->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title text-danger fw-bold">Batalkan Tugas Kebersihan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Alasan Pembatalan <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Tulis alasan pembatalan tugas..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-danger">Batalkan Tugas</button>
            </div>
        </form>
    </div>
</div>
@endsection
