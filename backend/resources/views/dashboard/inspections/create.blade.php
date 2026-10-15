@extends('layouts.app')

@section('title', 'Jadwalkan Inspeksi Kamar')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="{{ route('dashboard.inspections.index') }}" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar Inspeksi
                </a>
                <h4 class="fw-bold text-dark mt-1">Jadwalkan Inspeksi Kamar Baru</h4>
            </div>
        </div>

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('dashboard.inspections.store') }}">
                    @csrf

                    <!-- Pilihan Properti -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Properti Cabang <span class="text-danger">*</span></label>
                        <select name="property_id" id="propertySelect" class="form-select @error('property_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Properti --</option>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}" {{ old('property_id') == $prop->id ? 'selected' : '' }} data-rooms='@json($prop->rooms)'>
                                    {{ $prop->name }} ({{ $prop->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('property_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Pilihan Kamar -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Kamar Fisik <span class="text-danger">*</span></label>
                        <select name="room_id" id="roomSelect" class="form-select @error('room_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Properti Terlebih Dahulu --</option>
                        </select>
                        @error('room_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <!-- Tipe Inspeksi -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Jenis Inspeksi <span class="text-danger">*</span></label>
                            <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="check_in" {{ old('type') == 'check_in' ? 'selected' : '' }}>Check-In (Masuk / Awal Sewa)</option>
                                <option value="periodic" {{ old('type') == 'periodic' ? 'selected' : '' }}>Berkala (Pemeriksaan Rutin Bulanan)</option>
                                <option value="check_out" {{ old('type') == 'check_out' ? 'selected' : '' }}>Check-Out (Keluar / Akhir Sewa)</option>
                                <option value="maintenance" {{ old('type') == 'maintenance' ? 'selected' : '' }}>Pemeliharaan / Pasca Perbaikan</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tanggal Pelaksanaan -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
                            <input type="date" name="scheduled_date" class="form-control @error('scheduled_date') is-invalid @enderror" value="{{ old('scheduled_date', now()->toDateString()) }}" required>
                            @error('scheduled_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Petugas Pemeriksa -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Petugas Pemeriksa (Staf / Manager)</label>
                        <select name="inspector_id" class="form-select @error('inspector_id') is-invalid @enderror">
                            <option value="">-- Tetapkan Petugas Nanti / Sesuai Pembuat --</option>
                            @foreach($staffUsers as $st)
                                <option value="{{ $st->id }}" {{ old('inspector_id') == $st->id ? 'selected' : '' }}>
                                    {{ $st->name }} ({{ $st->role->label() }})
                                </option>
                            @endforeach
                        </select>
                        @error('inspector_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Catatan / Instruksi Petugas -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Catatan / Instruksi Tambahan</label>
                        <textarea name="inspector_notes" class="form-control" rows="3" placeholder="Contoh: Periksa kebocoran kran air dan remote AC cadangan...">{{ old('inspector_notes') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('dashboard.inspections.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-teal text-white px-4">
                            <i class="bi bi-save me-1"></i> Simpan Jadwal Inspeksi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const propSelect = document.getElementById('propertySelect');
    const roomSelect = document.getElementById('roomSelect');

    function updateRooms() {
        const selectedOpt = propSelect.options[propSelect.selectedIndex];
        roomSelect.innerHTML = '<option value="">-- Pilih Kamar --</option>';

        if (selectedOpt && selectedOpt.dataset.rooms) {
            const rooms = JSON.parse(selectedOpt.dataset.rooms);
            rooms.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = `Kamar ${r.room_number} (Lantai ${r.floor}) - Status: ${r.status}`;
                if ('{{ old('room_id', $selectedRoomId) }}' == r.id) {
                    opt.selected = true;
                }
                roomSelect.appendChild(opt);
            });
        }
    }

    propSelect.addEventListener('change', updateRooms);
    if (propSelect.value) {
        updateRooms();
    }
</script>
@endpush
@endsection
