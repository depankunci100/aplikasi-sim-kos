@extends('layouts.app')

@section('title', 'Manajemen Kebersihan (Housekeeping) - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-stars text-primary-teal me-2"></i>Jadwal & Tugas Kebersihan</h3>
        <p class="text-muted mb-0">Kelola pembersihan kamar, turnover tamu, dan area bersama dengan checklist terstandar.</p>
    </div>
    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner') || auth()->user()->hasRole('staff') || auth()->user()->hasRole('receptionist'))
    <button type="button" class="btn btn-primary-teal d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#createHousekeepingDrawer" aria-controls="createHousekeepingDrawer">
        <i class="bi bi-plus-circle"></i>
        <span>Jadwalkan Tugas</span>
    </button>
    @endif
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light p-3 text-primary-teal">
                    <i class="bi bi-stars fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Total Tugas</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                    <i class="bi bi-calendar-event fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Dijadwalkan</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['scheduled'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Dikerjakan</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['in_progress'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm kpi-card">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                    <i class="bi bi-patch-check fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Verifikasi</div>
                    <div class="fs-4 fw-bold text-dark">{{ $counts['pending_verification'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard.housekeeping.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="No. tugas atau area..." value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Properti</label>
                <select name="property_id" class="form-select form-select-sm">
                    <option value="">Semua Properti</option>
                    @foreach($properties as $prop)
                        <option value="{{ $prop->id }}" {{ ($filters['property_id'] ?? '') == $prop->id ? 'selected' : '' }}>
                            {{ $prop->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Tipe Tugas</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    @foreach(\App\Enums\HousekeepingType::cases() as $type)
                        <option value="{{ $type->value }}" {{ ($filters['type'] ?? '') == $type->value ? 'selected' : '' }}>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    @foreach(\App\Enums\HousekeepingStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ ($filters['status'] ?? '') == $st->value ? 'selected' : '' }}>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Tanggal Jadwal</label>
                <input type="date" name="scheduled_date" class="form-control form-control-sm" value="{{ $filters['scheduled_date'] ?? '' }}">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary-teal btn-sm flex-grow-1" title="Filter"><i class="bi bi-filter me-1"></i>Terapkan Filter</button>
                <a href="{{ route('dashboard.housekeeping.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Housekeeping Tasks Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">No. Tugas</th>
                        <th>Properti & Lokasi</th>
                        <th>Tipe Kebersihan</th>
                        <th>Jadwal Pelaksanaan</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th>Progress Checklist</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                    <tr>
                        <td class="ps-3 fw-bold text-dark font-monospace">
                            {{ $task->task_number }}
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $task->property->name ?? '-' }}</div>
                            <small class="text-muted">
                                @if($task->room)
                                    <i class="bi bi-door-closed me-1"></i>Kamar {{ $task->room->room_number }}
                                @else
                                    <i class="bi bi-geo-alt me-1"></i>{{ $task->area_name ?? 'Area Bersama' }}
                                @endif
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $task->type?->label() ?? ucfirst(str_replace('_', ' ', $task->type?->value ?? '')) }}
                            </span>
                            @if($task->priority->value === 'urgent')
                                <span class="badge bg-danger ms-1">Urgent</span>
                            @elseif($task->priority->value === 'high')
                                <span class="badge bg-warning text-dark ms-1">Tinggi</span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $task->scheduled_date ? $task->scheduled_date->format('d M Y') : '-' }}</div>
                            <small class="text-muted">{{ $task->scheduled_slot ?? 'Fleksibel' }}</small>
                        </td>
                        <td>
                            @if($task->assignee)
                                <div class="d-flex align-items-center gap-1">
                                    <i class="bi bi-person-badge text-muted"></i>
                                    <span>{{ $task->assignee->name }}</span>
                                </div>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Belum Ditugaskan</span>
                            @endif
                        </td>
                        <td>
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
                            <span class="badge {{ $badgeClass }}">{{ $task->status->label() }}</span>
                        </td>
                        <td>
                            @php
                                $checklist = $task->checklist ?? [];
                                $total = count($checklist);
                                $done = count(array_filter($checklist, fn($item) => !empty($item['is_done'])));
                                $pct = $total > 0 ? round(($done / $total) * 100) : 0;
                            @endphp
                            <div class="d-flex align-items-center gap-2" style="min-width: 120px;">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar {{ $pct === 100 ? 'bg-success' : 'bg-primary-teal' }}" style="width: {{ $pct }}%"></div>
                                </div>
                                <small class="text-muted font-monospace">{{ $done }}/{{ $total }}</small>
                            </div>
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('dashboard.housekeeping.show', $task->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-stars fs-1 d-block mb-2 text-secondary"></i>
                            <p class="mb-0">Belum ada tugas kebersihan yang sesuai filter.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tasks->hasPages())
    <div class="card-footer bg-white border-0 py-3">
        {{ $tasks->links() }}
    </div>
    @endif
</div>

<!-- Right-Side Drawer: Jadwalkan Tugas Kebersihan -->
@if(auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner') || auth()->user()->hasRole('staff') || auth()->user()->hasRole('receptionist'))
<div class="offcanvas offcanvas-end drawer-md" tabindex="-1" id="createHousekeepingDrawer" aria-labelledby="createHousekeepingDrawerLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2" id="createHousekeepingDrawerLabel">
            <i class="bi bi-stars text-primary-teal"></i> Jadwalkan Tugas Kebersihan
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <form action="{{ route('dashboard.housekeeping.store') }}" method="POST" class="d-flex flex-column h-100">
        @csrf
        <input type="hidden" name="_drawer_id" value="createHousekeepingDrawer">

        <div class="offcanvas-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold small">Properti <span class="text-danger">*</span></label>
                    <select name="property_id" id="drawerHousekeepingPropSelect" class="form-select form-select-sm @error('property_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Properti --</option>
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ old('property_id') == $prop->id ? 'selected' : '' }}
                                data-rooms='@json($prop->rooms)'
                                data-users='@json($prop->users)'>
                                {{ $prop->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small">Tipe Tugas Kebersihan <span class="text-danger">*</span></label>
                    <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror" required>
                        @foreach(\App\Enums\HousekeepingType::cases() as $type)
                            <option value="{{ $type->value }}" {{ old('type', 'room_cleaning') == $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Kamar (Opsional)</label>
                    <select name="room_id" id="drawerHousekeepingRoomSelect" class="form-select form-select-sm @error('room_id') is-invalid @enderror">
                        <option value="">-- Pilih Kamar --</option>
                    </select>
                    <div class="form-text small">Kosongkan jika untuk area umum.</div>
                    @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Area Bersama</label>
                    <input type="text" name="area_name" class="form-control form-control-sm @error('area_name') is-invalid @enderror"
                           placeholder="Contoh: Lobby, Koridor Lt. 2" value="{{ old('area_name') }}">
                    <div class="form-text small">Wajib jika kamar kosong.</div>
                    @error('area_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Prioritas</label>
                    <select name="priority" class="form-select form-select-sm @error('priority') is-invalid @enderror">
                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Rendah</option>
                        <option value="normal" {{ old('priority', 'normal') == 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>Tinggi</option>
                        <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Mendesak</option>
                    </select>
                    @error('priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Tanggal Pelaksanaan</label>
                    <input type="date" name="scheduled_date" class="form-control form-control-sm @error('scheduled_date') is-invalid @enderror"
                           value="{{ old('scheduled_date', date('Y-m-d')) }}">
                    @error('scheduled_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small">Sesi Waktu</label>
                    <select name="scheduled_slot" class="form-select form-select-sm @error('scheduled_slot') is-invalid @enderror">
                        <option value="Pagi (08:00 - 11:00)" {{ old('scheduled_slot') == 'Pagi (08:00 - 11:00)' ? 'selected' : '' }}>Pagi (08:00 - 11:00)</option>
                        <option value="Siang (11:00 - 14:00)" {{ old('scheduled_slot') == 'Siang (11:00 - 14:00)' ? 'selected' : '' }}>Siang (11:00 - 14:00)</option>
                        <option value="Sore (14:00 - 17:00)" {{ old('scheduled_slot') == 'Sore (14:00 - 17:00)' ? 'selected' : '' }}>Sore (14:00 - 17:00)</option>
                        <option value="Malam (17:00 - 20:00)" {{ old('scheduled_slot') == 'Malam (17:00 - 20:00)' ? 'selected' : '' }}>Malam (17:00 - 20:00)</option>
                    </select>
                    @error('scheduled_slot') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small">Tugaskan Petugas</label>
                    <select name="assigned_to" id="drawerHousekeepingAssigneeSelect" class="form-select form-select-sm @error('assigned_to') is-invalid @enderror">
                        <option value="">-- Tugaskan Nanti (Scheduled) --</option>
                    </select>
                    @error('assigned_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold small">Instruksi Khusus / Catatan</label>
                    <textarea name="notes" class="form-control form-control-sm @error('notes') is-invalid @enderror" rows="2"
                              placeholder="Rincian catatan untuk petugas...">{{ old('notes') }}</textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="offcanvas-footer">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-primary-teal btn-sm px-3">
                <i class="bi bi-calendar-check me-1"></i> Jadwalkan Tugas
            </button>
        </div>
    </form>
</div>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const propSelect = document.getElementById('drawerHousekeepingPropSelect');
        const roomSelect = document.getElementById('drawerHousekeepingRoomSelect');
        const assigneeSelect = document.getElementById('drawerHousekeepingAssigneeSelect');

        if (propSelect && roomSelect && assigneeSelect) {
            function updatePropDependencies() {
                const selectedOpt = propSelect.options[propSelect.selectedIndex];
                roomSelect.innerHTML = '<option value="">-- Pilih Kamar (atau kosongkan untuk area umum) --</option>';
                assigneeSelect.innerHTML = '<option value="">-- Tugaskan Nanti (Status: Scheduled) --</option>';

                if (!selectedOpt) return;

                if (selectedOpt.dataset.rooms) {
                    try {
                        const rooms = JSON.parse(selectedOpt.dataset.rooms);
                        rooms.forEach(room => {
                            const opt = document.createElement('option');
                            opt.value = room.id;
                            opt.textContent = 'Kamar ' + room.room_number + (room.name ? ' (' + room.name + ')' : '');
                            roomSelect.appendChild(opt);
                        });
                    } catch(e) {
                        console.error('Error parse rooms', e);
                    }
                }

                if (selectedOpt.dataset.users) {
                    try {
                        const users = JSON.parse(selectedOpt.dataset.users);
                        users.forEach(u => {
                            const opt = document.createElement('option');
                            opt.value = u.id;
                            opt.textContent = u.name + (u.role ? ' (' + u.role + ')' : '');
                            assigneeSelect.appendChild(opt);
                        });
                    } catch(e) {
                        console.error('Error parse users', e);
                    }
                }
            }

            propSelect.addEventListener('change', updatePropDependencies);
            updatePropDependencies();
        }
    });
</script>
@endpush
@endsection
