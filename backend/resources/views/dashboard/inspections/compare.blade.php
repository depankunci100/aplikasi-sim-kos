@extends('layouts.app')

@section('title', 'Perbandingan Kondisi Kamar - ' . $current->inspection_code)

@section('content')
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <a href="{{ route('dashboard.inspections.show', $current->id) }}" class="text-decoration-none text-muted small">
            <i class="bi bi-arrow-left"></i> Kembali ke Detail Inspeksi
        </a>
        <h4 class="fw-bold text-dark mt-1">Perbandingan Kondisi Kamar (Awal vs Akhir)</h4>
        <p class="text-muted mb-0">Bandingkan kondisi aset dan fasilitas kamar antara dua waktu inspeksi (misal Check-In vs Check-Out) untuk menghitung selisih kerusakan atau potongan deposit.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <form method="GET" action="{{ route('dashboard.inspections.compare', $current->id) }}" class="d-inline-flex gap-2">
            <select name="baseline_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Pilih Inspeksi Acuan (Baseline) --</option>
                @foreach($availableBaselines as $b)
                    <option value="{{ $b->id }}" {{ ($baseline && $baseline->id == $b->id) ? 'selected' : '' }}>
                        {{ $b->inspection_code }} ({{ $b->type->label() }} - {{ $b->scheduled_date->format('d/m/Y') }})
                    </option>
                @endforeach
            </select>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Inspeksi Saat Ini (Current) -->
    <div class="col-md-6">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary-subtle py-2">
                <span class="badge bg-primary">Inspeksi Berjalan (Current)</span>
            </div>
            <div class="card-body p-3 small">
                <h5 class="fw-bold text-dark">{{ $current->inspection_code }}</h5>
                <div>Tipe: <strong>{{ $current->type->label() }}</strong></div>
                <div>Kamar: <strong>Kamar {{ $current->room->room_number }}</strong> ({{ $current->property->name }})</div>
                <div>Tanggal: {{ $current->scheduled_date->format('d M Y') }}</div>
                <div>Status: <span class="badge {{ $current->status->badgeClass() }}">{{ $current->status->label() }}</span></div>
            </div>
        </div>
    </div>

    <!-- Inspeksi Acuan (Baseline) -->
    <div class="col-md-6">
        <div class="card shadow-sm border-secondary">
            <div class="card-header bg-secondary-subtle py-2">
                <span class="badge bg-secondary">Inspeksi Acuan (Baseline Awal)</span>
            </div>
            <div class="card-body p-3 small">
                @if($baseline)
                    <h5 class="fw-bold text-dark">{{ $baseline->inspection_code }}</h5>
                    <div>Tipe: <strong>{{ $baseline->type->label() }}</strong></div>
                    <div>Pemeriksa: {{ $baseline->inspector->name ?? '-' }}</div>
                    <div>Tanggal: {{ $baseline->scheduled_date->format('d M Y') }}</div>
                    <div>Status: <span class="badge {{ $baseline->status->badgeClass() }}">{{ $baseline->status->label() }}</span></div>
                @else
                    <div class="text-center py-3 text-muted">
                        <i class="bi bi-info-circle fs-3 d-block mb-1"></i>
                        Silakan pilih inspeksi acuan (misal inspeksi Check-In) pada dropdown di atas untuk memulai perbandingan.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($comparison)
<!-- Ringkasan Perbedaan & Biaya Kerusakan -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card p-3 h-100 bg-white">
            <span class="text-muted small fw-semibold text-uppercase">Total Item Mengalami Penurunan / Kerusakan</span>
            <h3 class="fw-bold my-1 text-danger">{{ count($comparison['differences']) }} Item</h3>
            <span class="small text-muted">Terdeteksi degradasi fisik atau ditandai perlu perbaikan</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3 h-100 bg-white border-danger">
            <span class="text-muted small fw-semibold text-uppercase">Total Estimasi Biaya Kerusakan (Potongan Deposit)</span>
            <h3 class="fw-bold my-1 text-danger">Rp {{ number_format($comparison['total_damage_cost'], 0, ',', '.') }}</h3>
            <span class="small text-muted">Dapat dijadikan rujukan potongan uang jaminan (deposit) saat check-out</span>
        </div>
    </div>
</div>

<!-- Tabel Rincian Perbandingan Item -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-table text-primary-teal me-2"></i>Rincian Item yang Mengalami Perubahan / Kerusakan
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>Item Checklist</th>
                    <th>Kategori</th>
                    <th>Kondisi Baseline (Awal)</th>
                    <th>Kondisi Saat Ini (Akhir)</th>
                    <th>Status Perubahan</th>
                    <th>Estimasi Biaya</th>
                    <th>Catatan Kerusakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($comparison['differences'] as $diff)
                <tr>
                    <td class="fw-bold text-dark">{{ $diff['item_name'] }}</td>
                    <td><span class="badge bg-light text-muted border">{{ ucfirst($diff['category']) }}</span></td>
                    <td>
                        <span class="badge bg-secondary">{{ ucfirst($diff['baseline_condition']) }}</span>
                    </td>
                    <td>
                        <span class="badge bg-danger">{{ ucfirst($diff['current_condition']) }}</span>
                    </td>
                    <td>
                        @if($diff['is_degraded'])
                            <span class="badge bg-danger text-white">Menurun (Rusak/Aus)</span>
                        @elseif($diff['repair_required'])
                            <span class="badge bg-warning text-dark">Perlu Perbaikan</span>
                        @else
                            <span class="badge bg-info text-dark">Perlu Catatan</span>
                        @endif
                    </td>
                    <td class="fw-semibold text-danger">
                        Rp {{ number_format($diff['estimated_cost'], 0, ',', '.') }}
                    </td>
                    <td class="small">{{ $diff['notes'] ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-success">
                        <i class="bi bi-shield-check fs-2 d-block mb-1"></i>
                        Semua kondisi item checklist sama persis dengan saat awal masuk. Tidak ditemukan penurunan atau kerusakan aset.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
