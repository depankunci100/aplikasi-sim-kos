@extends('layouts.app')

@section('title', 'Edit Konfigurasi Meter ' . $meter->meter_number)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="mb-4 d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.utilities.meters.show', $meter->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h4 class="fw-bold mb-0 text-dark">Edit Konfigurasi Meter</h4>
        </div>

        <div class="card p-4">
            <form action="{{ route('dashboard.utilities.meters.update', $meter->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Properti</label>
                        <input type="text" class="form-control bg-light" value="{{ $meter->property->name ?? '-' }}" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Kamar (Opsional)</label>
                        <select name="room_id" class="form-select @error('room_id') is-invalid @enderror">
                            <option value="">-- Kosongkan untuk Meter Bersama/Komunal --</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}" {{ old('room_id', $meter->room_id) == $room->id ? 'selected' : '' }}>
                                    Kamar {{ $room->room_number }} (Lantai {{ $room->floor }})
                                </option>
                            @endforeach
                        </select>
                        @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Jenis Utilitas</label>
                        <input type="text" class="form-control bg-light" value="{{ $meter->utility_type->label() }}" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Satuan Pembacaan <span class="text-danger">*</span></label>
                        <input type="text" name="unit" class="form-control @error('unit') is-invalid @enderror" value="{{ old('unit', $meter->unit) }}" required>
                        @error('unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nomor / Seri Meter Fisik <span class="text-danger">*</span></label>
                        <input type="text" name="meter_number" class="form-control @error('meter_number') is-invalid @enderror" value="{{ old('meter_number', $meter->meter_number) }}" required>
                        @error('meter_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Kapasitas Maksimum (Rollover)</label>
                        <input type="number" step="0.01" name="max_reading" class="form-control @error('max_reading') is-invalid @enderror" value="{{ old('max_reading', $meter->max_reading) }}">
                        @error('max_reading') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Tanggal Pemasangan</label>
                        <input type="date" name="installed_at" class="form-control @error('installed_at') is-invalid @enderror" value="{{ old('installed_at', $meter->installed_at ? $meter->installed_at->format('Y-m-d') : '') }}">
                        @error('installed_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Status Operasional</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ old('is_active', $meter->is_active ? '1' : '0') == '1' ? 'selected' : '' }}>Aktif</option>
                            <option value="0" {{ old('is_active', $meter->is_active ? '1' : '0') == '0' ? 'selected' : '' }}>Nonaktif / Rusak</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small">Catatan Tambahan</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $meter->notes) }}</textarea>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="{{ route('dashboard.utilities.meters.show', $meter->id) }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary-teal px-4">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
