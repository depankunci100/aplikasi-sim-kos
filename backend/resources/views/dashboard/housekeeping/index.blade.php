@extends('layouts.app')

@section('title', 'Manajemen Kebersihan (Housekeeping) - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-stars text-primary-teal me-2"></i>Jadwal & Tugas Kebersihan</h3>
        <p class="text-muted mb-0">Kelola pembersihan kamar, turnover tamu, dan area bersama dengan checklist terstandar.</p>
    </div>
    @if(auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager') || auth()->user()->hasRole('owner') || auth()->user()->hasRole('staff') || auth()->user()->hasRole('receptionist'))
    <a href="{{ route('dashboard.housekeeping.create') }}" class="btn btn-primary-teal d-flex align-items-center gap-2">
        <i class="bi bi-plus-circle"></i>
        <span>Jadwalkan Tugas</span>
    </a>
    @endif
</div>

<!-- Filter Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" action="{{ route('dashboard.housekeeping.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
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
            <div class="col-md-2">
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
            <div class="col-md-2">
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
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Tanggal Jadwal</label>
                <input type="date" name="scheduled_date" class="form-control form-control-sm" value="{{ $filters['scheduled_date'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary-teal btn-sm flex-grow-1" title="Filter"><i class="bi bi-filter me-1"></i>Filter</button>
                <a href="{{ route('dashboard.housekeeping.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
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
@endsection
