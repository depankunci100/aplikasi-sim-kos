@extends('layouts.app')

@section('title', 'Ajukan Tiket Perbaikan - SIM Kos & Guesthouse')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="{{ route('dashboard.maintenance.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold mb-0">Ajukan Tiket Perbaikan (Maintenance)</h4>
                <p class="text-muted small mb-0">Isi formulir berikut untuk melaporkan kerusakan atau kebutuhan perbaikan.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="{{ route('dashboard.maintenance.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Properti <span class="text-danger">*</span></label>
                            <select name="property_id" id="propertySelect" class="form-select @error('property_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Properti --</option>
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}" {{ old('property_id') == $prop->id ? 'selected' : '' }} data-rooms='@json($prop->rooms)'>
                                        {{ $prop->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kamar (Opsional)</label>
                            <select name="room_id" id="roomSelect" class="form-select @error('room_id') is-invalid @enderror">
                                <option value="">Area Bersama / Seluruh Properti</option>
                            </select>
                            <div class="form-text">Biarkan kosong jika kerusakan berada pada fasilitas umum kos.</div>
                            @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kategori Kerusakan <span class="text-danger">*</span></label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                @foreach(\App\Enums\MaintenanceCategory::cases() as $cat)
                                    <option value="{{ $cat->value }}" {{ old('category') == $cat->value ? 'selected' : '' }}>
                                        {{ $cat->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tingkat Prioritas <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Rendah (SLA 72 Jam)</option>
                                <option value="normal" {{ (old('priority', 'normal')) == 'normal' ? 'selected' : '' }}>Normal (SLA 48 Jam)</option>
                                <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>Tinggi (SLA 24 Jam)</option>
                                <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Darurat / Urgent (SLA 6 Jam)</option>
                            </select>
                            @error('priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Judul Masalah / Kerusakan <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" placeholder="Contoh: Lampu kamar mandi mati & saklar longgar" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi Lengkap <span class="text-danger">*</span></label>
                            <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Jelaskan detail kerusakan, lokasi tepat, dan kondisi saat ini..." required>{{ old('description') }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Foto Kondisi Awal (Maks. 5MB, format JPG/PNG/WebP)</label>
                            <input type="file" name="photos_before[]" class="form-control @error('photos_before') is-invalid @enderror" multiple accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Unggah foto bukti kerusakan agar mempermudah teknisi dalam diagnosis awal.</div>
                            @error('photos_before') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ route('dashboard.maintenance.index') }}" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary-teal px-4">Kirim Laporan Perbaikan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const propSelect = document.getElementById('propertySelect');
        const roomSelect = document.getElementById('roomSelect');

        function updateRooms() {
            const selectedOpt = propSelect.options[propSelect.selectedIndex];
            roomSelect.innerHTML = '<option value="">Area Bersama / Seluruh Properti</option>';

            if (!selectedOpt || !selectedOpt.dataset.rooms) return;

            const oldRoomId = "{{ old('room_id') }}";
            try {
                const rooms = JSON.parse(selectedOpt.dataset.rooms);
                rooms.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = `Kamar ${r.room_number}`;
                    if (oldRoomId && String(r.id) === String(oldRoomId)) {
                        opt.selected = true;
                    }
                    roomSelect.appendChild(opt);
                });
            } catch (e) {}
        }

        propSelect.addEventListener('change', updateRooms);
        if (propSelect.value) updateRooms();
    });
</script>
@endpush
@endsection
