@extends('layouts.app')

@section('title', 'Detail Meter ' . $meter->meter_number)

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('dashboard.utilities.meters.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0 text-dark">Meter #{{ $meter->meter_number }}</h4>
            <span class="text-muted small">
                {{ $meter->property->name ?? '-' }} &bull;
                {{ $meter->room ? 'Kamar ' . $meter->room->room_number : 'Meter Bersama / Komunal' }}
            </span>
        </div>
    </div>
    @if(!auth()->user()->isTenant())
    <div class="d-flex gap-2">
        @if(!auth()->user()->isStaff())
        <a href="{{ route('dashboard.utilities.meters.edit', $meter->id) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit Konfigurasi
        </a>
        @endif
        <a href="{{ route('dashboard.utilities.readings.create', ['meter_id' => $meter->id]) }}" class="btn btn-primary-teal btn-sm">
            <i class="bi bi-pencil-square me-1"></i> Catat Pembacaan Baru
        </a>
    </div>
    @endif
</div>

<div class="row g-4 mb-4">
    <!-- Card Info Meter -->
    <div class="col-lg-4">
        <div class="card p-4 h-100">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Spesifikasi Meter</h6>
            <div class="mb-3">
                <span class="text-muted small d-block">Jenis Utilitas</span>
                @if($meter->utility_type->value === 'electricity')
                    <span class="badge bg-warning-subtle text-warning-emphasis fs-6 mt-1">
                        <i class="bi bi-lightning-charge-fill me-1"></i> Listrik ({{ $meter->unit }})
                    </span>
                @else
                    <span class="badge bg-info-subtle text-info-emphasis fs-6 mt-1">
                        <i class="bi bi-droplet-fill me-1"></i> Air ({{ $meter->unit }})
                    </span>
                @endif
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <span class="text-muted small d-block">Angka Terkini</span>
                    <span class="fs-4 fw-bold text-teal">{{ number_format($meter->current_reading, 2) }}</span>
                    <span class="text-muted small">{{ $meter->unit }}</span>
                </div>
                <div class="col-6">
                    <span class="text-muted small d-block">Baseline Awal</span>
                    <span class="fs-5 fw-semibold text-dark">{{ number_format($meter->initial_reading, 2) }}</span>
                    <span class="text-muted small">{{ $meter->unit }}</span>
                </div>
            </div>

            <ul class="list-unstyled small text-muted border-top pt-3 mb-0">
                <li class="d-flex justify-content-between py-1">
                    <span>Tipe Alokasi:</span>
                    <strong class="text-dark">{{ $meter->meter_type->label() }}</strong>
                </li>
                <li class="d-flex justify-content-between py-1">
                    <span>Kapasitas Max:</span>
                    <strong class="text-dark">{{ $meter->max_reading ? number_format($meter->max_reading, 2) . ' ' . $meter->unit : 'Standar' }}</strong>
                </li>
                <li class="d-flex justify-content-between py-1">
                    <span>Tgl Pemasangan:</span>
                    <strong class="text-dark">{{ $meter->installed_at ? $meter->installed_at->format('d/m/Y') : '-' }}</strong>
                </li>
                <li class="d-flex justify-content-between py-1">
                    <span>Status Operasional:</span>
                    @if($meter->is_active)
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                    @endif
                </li>
            </ul>

            @if($meter->notes)
            <div class="mt-3 p-2 bg-light rounded small text-muted">
                <i class="bi bi-info-circle me-1"></i> {{ $meter->notes }}
            </div>
            @endif

            <!-- Snapshot Tarif Berlaku -->
            <div class="mt-4 pt-3 border-top">
                <span class="text-muted small d-block">Tarif Berlaku Saat Ini:</span>
                @if($effectiveTariff)
                    <div class="d-flex align-items-baseline gap-1 mt-1">
                        <span class="fs-5 fw-bold text-dark">Rp {{ number_format($effectiveTariff->rate_per_unit, 0, ',', '.') }}</span>
                        <span class="small text-muted">/ {{ $effectiveTariff->unit }}</span>
                    </div>
                    <div class="small text-muted">Mulai berlaku sejak {{ $effectiveTariff->effective_from->format('d/m/Y') }}</div>
                @else
                    <div class="alert alert-warning py-2 px-3 small mt-2 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i> Belum ada tarif aktif untuk properti ini.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Riwayat Pembacaan Meter -->
    <div class="col-lg-8">
        <div class="card overflow-hidden">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">Riwayat Pembacaan & Konsumsi</h6>
                <span class="badge bg-light text-dark border">{{ $meter->readings->count() }} Pencatatan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th>Tgl Pembacaan</th>
                            <th class="text-end">Sebelumnya</th>
                            <th class="text-end">Saat Ini</th>
                            <th class="text-end">Pemakaian</th>
                            <th class="text-end">Estimasi Biaya</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($meter->readings as $reading)
                        <tr>
                            <td>
                                <a href="{{ route('dashboard.utilities.readings.show', $reading->id) }}" class="fw-semibold text-teal text-decoration-none">
                                    {{ $reading->reading_date->format('d/m/Y') }}
                                </a>
                                @if($reading->period_start && $reading->period_end)
                                    <div class="small text-muted">{{ $reading->period_start->format('d/m') }} - {{ $reading->period_end->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td class="text-end text-muted small">{{ number_format($reading->previous_meter, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($reading->current_meter, 2) }}</td>
                            <td class="text-end">
                                <span class="badge bg-teal-subtle text-teal fw-semibold">
                                    {{ number_format($reading->usage, 2) }} {{ $meter->unit }}
                                </span>
                            </td>
                            <td class="text-end fw-semibold">
                                Rp {{ number_format($reading->total_cost, 0, ',', '.') }}
                                <div class="small text-muted">@ Rp {{ number_format($reading->rate_per_unit, 0, ',', '.') }}</div>
                            </td>
                            <td class="text-center">
                                @if($reading->status->value === 'approved')
                                    <span class="badge bg-success-subtle text-success">Disetujui</span>
                                @elseif($reading->status->value === 'pending_review')
                                    <span class="badge bg-warning-subtle text-warning">Menunggu Review</span>
                                @elseif($reading->status->value === 'rejected')
                                    <span class="badge bg-danger-subtle text-danger" title="{{ $reading->rejection_reason }}">Ditolak</span>
                                @else
                                    <span class="badge bg-light text-dark border">Draf</span>
                                @endif

                                @if($reading->is_billed)
                                    <div class="mt-1">
                                        <a href="{{ route('dashboard.invoices.show', $reading->invoice_id) }}" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none">
                                            <i class="bi bi-receipt"></i> Billed
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('dashboard.utilities.readings.show', $reading->id) }}" class="btn btn-outline-secondary btn-sm" title="Lihat Detail & Bukti">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-2 text-secondary opacity-50 d-block mb-2"></i>
                                Belum ada riwayat pembacaan untuk meter ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
