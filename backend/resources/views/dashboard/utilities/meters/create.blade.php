@extends('layouts.app')

@section('title', 'Pendaftaran Meter Utilitas Baru')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="mb-4 d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.utilities.meters.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h4 class="fw-bold mb-0 text-dark">Daftarkan Meter Fisik Baru</h4>
        </div>

        <div class="card p-4">
            <form action="{{ route('dashboard.utilities.meters.store') }}" method="POST">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Properti <span class="text-danger">*</span></label>
                        <select name="property_id" id="propertySelect" class="form-select @error('property_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Properti --</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->id }}" {{ old('property_id') == $property->id ? 'selected' : '' }}>
                                    {{ $property->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Kamar (Opsional)</label>
                        <select name="room_id" id="roomSelect" class="form-select @error('room_id') is-invalid @enderror">
                            <option value="">-- Kosongkan untuk Meter Bersama/Komunal --</option>
                            @foreach($properties as $property)
                                @foreach($property->rooms as $room)
                                    <option value="{{ $room->id }}" data-property="{{ $property->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                                        {{ $property->name }} - Kamar {{ $room->room_number }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                        <div class="form-text small">Pilih kamar untuk meter individual kamar.</div>
                        @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Jenis Utilitas <span class="text-danger">*</span></label>
                        <select name="utility_type" id="utilityTypeSelect" class="form-select @error('utility_type') is-invalid @enderror" required>
                            <option value="electricity" {{ old('utility_type') === 'electricity' ? 'selected' : '' }}>Listrik (kWh)</option>
                            <option value="water" {{ old('utility_type') === 'water' ? 'selected' : '' }}>Air (m³)</option>
                        </select>
                        @error('utility_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Satuan Pembacaan <span class="text-danger">*</span></label>
                        <input type="text" name="unit" id="unitInput" class="form-control @error('unit') is-invalid @enderror" value="{{ old('unit', 'kWh') }}" required>
                        @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nomor / Seri Meter Fisik <span class="text-danger">*</span></label>
                        <input type="text" name="meter_number" class="form-control @error('meter_number') is-invalid @enderror" placeholder="Contoh: PLN-140229871" value="{{ old('meter_number') }}" required>
                        @error('meter_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Tipe Meter <span class="text-danger">*</span></label>
                        <select name="meter_type" class="form-select @error('meter_type') is-invalid @enderror" required>
                            <option value="individual" {{ old('meter_type', 'individual') === 'individual' ? 'selected' : '' }}>Individual (Per Kamar)</option>
                            <option value="shared" {{ old('meter_type') === 'shared' ? 'selected' : '' }}>Bersama (Komunal / Fasilitas Umum)</option>
                        </select>
                        @error('meter_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Pembacaan Awal / Baseline <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="initial_reading" class="form-control @error('initial_reading') is-invalid @enderror" value="{{ old('initial_reading', '0.00') }}" required>
                        <div class="form-text small">Angka awal pada fisik meteran saat pertama kali dipasang atau dicatat.</div>
                        @error('initial_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Kapasitas Maksimum (Rollover)</label>
                        <input type="number" step="0.01" name="max_reading" class="form-control @error('max_reading') is-invalid @enderror" placeholder="Kosongkan jika standar" value="{{ old('max_reading') }}">
                        <div class="form-text small">Kapasitas maksimal sebelum angka meter berputar kembali ke 0.</div>
                        @error('max_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Tanggal Pemasangan</label>
                        <input type="date" name="installed_at" class="form-control @error('installed_at') is-invalid @enderror" value="{{ old('installed_at', date('Y-m-d')) }}">
                        @error('installed_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Status Operasional</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Aktif (Siap Dicatat)</option>
                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Nonaktif / Rusak</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small">Catatan Tambahan</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Contoh: Lokasi box meter di sebelah pintu masuk kamar">{{ old('notes') }}</textarea>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="{{ route('dashboard.utilities.meters.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary-teal px-4">
                            <i class="bi bi-save me-1"></i> Simpan Meter Baru
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const propSelect = document.getElementById('propertySelect');
        const roomSelect = document.getElementById('roomSelect');
        const utilitySelect = document.getElementById('utilityTypeSelect');
        const unitInput = document.getElementById('unitInput');

        // Ganti satuan otomatis berdasarkan utilitas
        utilitySelect.addEventListener('change', function () {
            unitInput.value = this.value === 'electricity' ? 'kWh' : 'm3';
        });

        // Filter kamar berdasarkan properti terpilih
        function filterRooms() {
            const selectedProp = propSelect.value;
            Array.from(roomSelect.options).forEach(opt => {
                if (!opt.value) return; // opsi placeholder
                const optProp = opt.getAttribute('data-property');
                opt.style.display = (!selectedProp || optProp === selectedProp) ? '' : 'none';
            });
        }

        propSelect.addEventListener('change', filterRooms);
        filterRooms();
    });
</script>
@endpush
@endsection
