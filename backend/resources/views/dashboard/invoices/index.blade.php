@extends('layouts.app')

@section('title', 'Daftar Tagihan (Invoice)')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Daftar Tagihan & Kewajiban Sewa</h3>
        <p class="text-muted mb-0">Kelola invoice sewa, deposit jaminan, dan rekam jejak pelunasan penyewa.</p>
    </div>
</div>

<!-- Search & Status Filter -->
<div class="card mb-4 p-3 bg-white">
    <form method="GET" action="{{ route('dashboard.invoices.index') }}" class="row g-2 align-items-center">
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">-- Semua Status Tagihan --</option>
                <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Belum Dibayar (Unpaid)</option>
                <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Dibayar Sebagian (Cicil)</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Lunas (Paid)</option>
                <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Jatuh Tempo (Overdue)</option>
            </select>
        </div>
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Cari nomor invoice, nama penyewa, atau nomor kamar..." value="{{ request('q') }}">
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary-teal flex-grow-1">Cari</button>
            <a href="{{ route('dashboard.invoices.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Invoice Table -->
<div class="card mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>No. Invoice</th>
                    <th>Penyewa & Kamar</th>
                    <th>Jenis Tagihan</th>
                    <th>Total Tagihan</th>
                    <th>Sudah Dibayar</th>
                    <th>Sisa Saldo</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td>
                        <strong class="text-dark">{{ $inv->invoice_number }}</strong>
                        <div class="small text-muted">{{ $inv->booking->booking_code ?? '-' }}</div>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark">{{ $inv->user->name ?? '-' }}</span>
                        @if($inv->booking && $inv->booking->room)
                            <div class="small text-primary-teal fw-semibold">Kamar {{ $inv->booking->room->room_number }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ strtoupper($inv->invoice_type) }}</span>
                    </td>
                    <td>
                        <span class="fw-bold">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</span>
                    </td>
                    <td>
                        <span class="text-success fw-semibold">Rp {{ number_format($inv->paid_amount, 0, ',', '.') }}</span>
                    </td>
                    <td>
                        <strong class="{{ $inv->remaining_balance > 0 ? 'text-danger' : 'text-muted' }}">
                            Rp {{ number_format($inv->remaining_balance, 0, ',', '.') }}
                        </strong>
                    </td>
                    <td>
                        <span class="small {{ $inv->due_date && $inv->due_date->isPast() && !$inv->isPaid() ? 'text-danger fw-bold' : 'text-muted' }}">
                            {{ $inv->due_date ? $inv->due_date->format('d/m/Y') : '-' }}
                        </span>
                    </td>
                    <td>
                        @if($inv->status->value === 'paid')
                            <span class="badge badge-status-paid"><i class="bi bi-check-circle"></i> Lunas</span>
                        @elseif($inv->status->value === 'partially_paid')
                            <span class="badge badge-status-partially_paid"><i class="bi bi-pie-chart"></i> Sebagian</span>
                        @elseif($inv->status->value === 'overdue')
                            <span class="badge badge-status-unpaid"><i class="bi bi-clock-history"></i> Overdue</span>
                        @else
                            <span class="badge badge-status-unpaid"><i class="bi bi-exclamation-circle"></i> Belum Bayar</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('dashboard.invoices.show', $inv->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-receipt"></i> Rincian & Cetak
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-receipt fs-2 d-block mb-2"></i>
                        Tidak ditemukan tagihan yang sesuai.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer bg-white py-2">
        {{ $invoices->links() }}
    </div>
    @endif
</div>
@endsection
