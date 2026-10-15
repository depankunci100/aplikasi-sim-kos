@extends('layouts.app')

@section('title', 'Jadwalkan Tugas Kebersihan - SIM Kos & Guesthouse')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="{{ route('dashboard.housekeeping.index') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0">Jadwalkan Tugas Kebersihan (Housekeeping)</h4>
                <p class="text-muted small mb-0">Buat penugasan pembersihan kamar atau area bersama dengan checklist otomatis.</p>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="{{ route('dashboard.housekeeping.store') }}" method="POST">
                    @csrf

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Properti <span class="text-danger">*</span></label>
                            <select name="property_id" id="propertySelect" class="form-select @error('property_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Properti --</option>
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}" {{ old('property_id') == $prop->id ? 'selected' : '' }}
                                        data-rooms='@json($prop->rooms)'
                                        data-users='@json($prop->users)'>
                                        {{ $prop->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('property_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipe Tugas Kebersihan <span class="text-danger">*</span></label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                @foreach(\App\Enums\HousekeepingType::cases() as $type)
                                    <option value="{{ $type->value }}" {{ old('type', 'room_cleaning') == $type->value ? 'selected' : '' }}>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kamar (Opsional)</label>
                            <select name="room_id" id="roomSelect" class="form-select @error('room_id') is-invalid @enderror">
                                <option value="">-- Pilih Kamar (atau kosongkan untuk area umum) --</option>
                            </select>
                            <small class="text-muted">Pilih kamar jika tugas ditujukan untuk unit tertentu.</small>
                            @error('room_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Area Bersama / Catatan Lokasi</label>
                            <input type="text" name="area_name" class="form-control @error('area_name') is-invalid @enderror"
                                   placeholder="Contoh: Lobby Utama, Dapur Lantai 2, Selasar" value="{{ old('area_name') }}">
                            <small class="text-muted">Wajib jika tidak memilih kamar khusus.</small>
                            @error('area_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Prioritas</label>
                            <select name="priority" class="form-select @error('priority') is-invalid @enderror">
                                <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Rendah (Low)</option>
                                <option value="normal" {{ old('priority', 'normal') == 'normal' ? 'selected' : '' }}>Normal</option>
                                <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>Tinggi (High)</option>
                                <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Mendesak (Urgent)</option>
                            </select>
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tanggal Pelaksanaan</label>
                            <input type="date" name="scheduled_date" class="form-control @error('scheduled_date') is-invalid @enderror"
                                   value="{{ old('scheduled_date', date('Y-m-d')) }}">
                            @error('scheduled_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sesi Waktu (Slot)</label>
                            <select name="scheduled_slot" class="form-select @error('scheduled_slot') is-invalid @enderror">
                                <option value="Pagi (08:00 - 11:00)" {{ old('scheduled_slot') == 'Pagi (08:00 - 11:00)' ? 'selected' : '' }}>Pagi (08:00 - 11:00)</option>
                                <option value="Siang (11:00 - 14:00)" {{ old('scheduled_slot') == 'Siang (11:00 - 14:00)' ? 'selected' : '' }}>Siang (11:00 - 14:00)</option>
                                <option value="Sore (14:00 - 17:00)" {{ old('scheduled_slot') == 'Sore (14:00 - 17:00)' ? 'selected' : '' }}>Sore (14:00 - 17:00)</option>
                                <option value="Malam (17:00 - 20:00)" {{ old('scheduled_slot') == 'Malam (17:00 - 20:00)' ? 'selected' : '' }}>Malam (17:00 - 20:00)</option>
                            </select>
                            @error('scheduled_slot')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tugaskan Petugas Kebersihan</label>
                        <select name="assigned_to" id="assigneeSelect" class="form-select @error('assigned_to') is-invalid @enderror">
                            <option value="">-- Tugaskan Nanti (Status: Scheduled) --</option>
                        </select>
                        <small class="text-muted">Daftar staf yang terhubung dengan properti yang dipilih.</small>
                        @error('assigned_to')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Instruksi Khusus / Catatan</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3"
                                  placeholder="Contoh: Harap ganti sprei putih baru, bersihkan kaca balkon, dan semprot disinfektan.">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('dashboard.housekeeping.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary-teal px-4">
                            <i class="bi bi-calendar-check me-1"></i>Jadwalkan Tugas
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const propertySelect = document.getElementById('propertySelect');
    const roomSelect = document.getElementById('roomSelect');
    const assigneeSelect = document.getElementById('assigneeSelect');
    const selectedRoomId = '{{ old('room_id', '') }}';
    const selectedAssigneeId = '{{ old('assigned_to', '') }}';

    function updateOptions() {
        const selected = propertySelect.options[propertySelect.selectedIndex];
        roomSelect.innerHTML = '<option value="">-- Pilih Kamar (atau kosongkan untuk area umum) --</option>';
        assigneeSelect.innerHTML = '<option value="">-- Tugaskan Nanti (Status: Scheduled) --</option>';

        if (!selected || !selected.value) return;

        const rooms = JSON.parse(selected.getAttribute('data-rooms') || '[]');
        rooms.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = `Kamar ${r.room_number} (${r.status})`;
            if (selectedRoomId && r.id == selectedRoomId) {
                opt.selected = true;
            }
            roomSelect.appendChild(opt);
        });

        const users = JSON.parse(selected.getAttribute('data-users') || '[]');
        users.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = `${u.name} (${u.pivot ? u.pivot.role : 'Staff'})`;
            if (selectedAssigneeId && u.id == selectedAssigneeId) {
                opt.selected = true;
            }
            assigneeSelect.appendChild(opt);
        });
    }

    propertySelect.addEventListener('change', updateOptions);
    if (propertySelect.value) {
        updateOptions();
    }
});
</script>
@endsection
