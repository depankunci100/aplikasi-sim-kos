@extends('layouts.app')

@section('title', 'Pencatatan Nilai Meter')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="mb-4 d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.utilities.readings.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h4 class="fw-bold mb-0 text-dark">Catat Pembacaan Meter</h4>
        </div>

        <div class="card p-4">
            <form action="{{ route('dashboard.utilities.readings.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Pilih Meter Fisik <span class="text-danger">*</span></label>
                        <select name="meter_id" id="meterSelect" class="form-select @error('meter_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Meter Fisik --</option>
                            @foreach($meters as $meter)
                                <option value="{{ $meter->id }}"
                                    data-unit="{{ $meter->unit }}"
                                    data-current="{{ $meter->current_reading }}"
                                    data-initial="{{ $meter->initial_reading }}"
                                    data-prop="{{ $meter->property->name ?? '' }}"
                                    data-room="{{ $meter->room ? 'Kamar ' . $meter->room->room_number : 'Meter Bersama' }}"
                                    data-type="{{ $meter->utility_type->label() }}"
                                    {{ (old('meter_id', $selectedMeter->id ?? null) == $meter->id) ? 'selected' : '' }}>
                                    {{ $meter->meter_number }} &mdash; {{ $meter->property->name ?? '' }} ({{ $meter->room ? 'Kamar ' . $meter->room->room_number : 'Komunal' }}) [{{ $meter->utility_type->label() }}]
                                </option>
                            @endforeach
                        </select>
                        @error('meter_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <!-- Card Info Dinamis Meter -->
                    <div class="col-12" id="meterInfoCard" style="display: none;">
                        <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small d-block" id="infoPropertyRoom">-</span>
                                <span class="fw-bold text-dark fs-6" id="infoType">-</span>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small d-block">Pembacaan Sebelumnya / Baseline:</span>
                                <span class="fw-bold fs-5 text-teal" id="infoPreviousReading">0.00</span>
                                <span class="small text-muted" id="infoUnit">kWh</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Nilai Pembacaan Saat Ini <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="current_meter" id="currentMeterInput" class="form-control @error('current_meter') is-invalid @enderror" placeholder="Contoh: 1542.50" value="{{ old('current_meter') }}" required>
                            <span class="input-group-text" id="unitBadge">kWh</span>
                        </div>
                        <div class="form-text small" id="meterDiffFeedback">Masukkan angka yang tertera pada display fisik meter.</div>
                        @error('current_meter') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Tanggal Pembacaan <span class="text-danger">*</span></label>
                        <input type="date" name="reading_date" class="form-control @error('reading_date') is-invalid @enderror" value="{{ old('reading_date', date('Y-m-d')) }}" required>
                        @error('reading_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Periode Awal (Opsional)</label>
                        <input type="date" name="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start') }}">
                        @error('period_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Periode Akhir (Opsional)</label>
                        <input type="date" name="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end') }}">
                        @error('period_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small">Foto Bukti Fisik Meter (Privat)</label>
                        <input type="file" name="proof_photo" class="form-control @error('proof_photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text small">Unggah foto display angka meteran fisik (format JPG, PNG, atau WebP, maks 5 MB). File disimpan aman di server privat.</div>
                        @error('proof_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small">Status Awal</label>
                        <select name="status" class="form-select">
                            <option value="pending_review" {{ old('status', 'pending_review') === 'pending_review' ? 'selected' : '' }}>Menunggu Review (Direkomendasikan)</option>
                            @if(in_array(auth()->user()->role->value, ['super_admin', 'owner', 'manager']))
                            <option value="approved" {{ old('status') === 'approved' ? 'selected' : '' }}>Langsung Disetujui (Otoritas Manajer/Owner)</option>
                            @endif
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small">Catatan Pemeriksaan</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Contoh: Kondisi segel meter aman, tidak ada kebocoran">{{ old('notes') }}</textarea>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="{{ route('dashboard.utilities.readings.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary-teal px-4" id="submitBtn">
                            <i class="bi bi-save me-1"></i> Simpan Pembacaan
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
        const meterSelect = document.getElementById('meterSelect');
        const meterInfoCard = document.getElementById('meterInfoCard');
        const infoPropRoom = document.getElementById('infoPropertyRoom');
        const infoType = document.getElementById('infoType');
        const infoPrev = document.getElementById('infoPreviousReading');
        const infoUnit = document.getElementById('infoUnit');
        const unitBadge = document.getElementById('unitBadge');
        const currentInput = document.getElementById('currentMeterInput');
        const diffFeedback = document.getElementById('meterDiffFeedback');
        const submitBtn = document.getElementById('submitBtn');

        let prevValue = 0;

        function updateMeterInfo() {
            const opt = meterSelect.options[meterSelect.selectedIndex];
            if (!opt || !opt.value) {
                meterInfoCard.style.display = 'none';
                return;
            }

            const current = parseFloat(opt.getAttribute('data-current')) || parseFloat(opt.getAttribute('data-initial')) || 0;
            const unit = opt.getAttribute('data-unit') || 'kWh';
            const prop = opt.getAttribute('data-prop');
            const room = opt.getAttribute('data-room');
            const type = opt.getAttribute('data-type');

            prevValue = current;
            infoPropRoom.textContent = prop + ' • ' + room;
            infoType.textContent = type;
            infoPrev.textContent = current.toFixed(2);
            infoUnit.textContent = unit;
            unitBadge.textContent = unit;

            meterInfoCard.style.display = 'block';
            validateReading();
        }

        function validateReading() {
            const currentVal = parseFloat(currentInput.value);
            if (isNaN(currentVal) || !meterSelect.value) {
                diffFeedback.className = 'form-text small';
                diffFeedback.textContent = 'Masukkan angka yang tertera pada display fisik meter.';
                submitBtn.disabled = false;
                return;
            }

            if (currentVal < prevValue) {
                diffFeedback.className = 'form-text small text-danger fw-bold';
                diffFeedback.textContent = 'Peringatan: Nilai baru (' + currentVal.toFixed(2) + ') lebih kecil dari pembacaan sebelumnya (' + prevValue.toFixed(2) + '). Harus sama atau lebih besar!';
                submitBtn.disabled = true;
            } else {
                const usage = (currentVal - prevValue).toFixed(2);
                diffFeedback.className = 'form-text small text-success fw-semibold';
                diffFeedback.textContent = 'Pemakaian terhitung: ' + usage + ' ' + unitBadge.textContent;
                submitBtn.disabled = false;
            }
        }

        meterSelect.addEventListener('change', updateMeterInfo);
        currentInput.addEventListener('input', validateReading);

        if (meterSelect.value) {
            updateMeterInfo();
        }
    });
</script>
@endpush
@endsection
