@extends('layouts.app')

@section('title', 'Verifikasi Pembayaran')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">Verifikasi Bukti Transfer Masuk</h3>
        <p class="text-muted mb-0">Periksa bukti transfer penyewa, cocokkan mutasi rekening, dan setujui untuk memperbarui saldo invoice.</p>
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="card mb-4 p-3 bg-white">
    <form method="GET" action="{{ route('dashboard.payments.index') }}" class="row g-2 align-items-center">
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Menunggu Verifikasi (Pending)</option>
                <option value="verified" {{ $statusFilter === 'verified' ? 'selected' : '' }}>Sudah Disetujui (Verified)</option>
                <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Ditolak (Rejected)</option>
                <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>-- Semua Status Transaksi --</option>
            </select>
        </div>
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Cari kode bayar, no invoice, atau nama penyewa..." value="{{ request('q') }}">
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary-teal flex-grow-1">Terapkan Filter</button>
            <a href="{{ route('dashboard.payments.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Table Payments -->
<div class="card mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted text-uppercase">
                <tr>
                    <th>Kode Bayar</th>
                    <th>No. Invoice</th>
                    <th>Penyewa</th>
                    <th>Nominal</th>
                    <th>Metode & Pengirim</th>
                    <th>Waktu Masuk</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td>
                        <strong class="text-dark">{{ $p->payment_code }}</strong>
                    </td>
                    <td>
                        <a href="{{ route('dashboard.invoices.show', $p->invoice_id) }}" class="text-decoration-none fw-semibold">
                            {{ $p->invoice->invoice_number ?? '-' }}
                        </a>
                    </td>
                    <td>
                        <span class="fw-semibold text-dark">{{ $p->user->name ?? '-' }}</span><br>
                        <span class="small text-muted">{{ $p->user->phone ?? '-' }}</span>
                    </td>
                    <td>
                        <strong class="fs-6 {{ $p->status === 'verified' ? 'text-success' : ($p->status === 'rejected' ? 'text-muted text-decoration-line-through' : 'text-primary-teal') }}">
                            Rp {{ number_format($p->amount, 0, ',', '.') }}
                        </strong>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ strtoupper($p->payment_method) }}</span>
                        @if($p->sender_bank)
                            <div class="small text-muted">{{ $p->sender_bank }} • {{ $p->sender_name }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="small text-muted">{{ $p->created_at->format('d/m/Y H:i') }} WIB</span>
                    </td>
                    <td>
                        @if($p->status === 'pending')
                            <span class="badge badge-status-pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                        @elseif($p->status === 'verified')
                            <span class="badge badge-status-verified"><i class="bi bi-check-circle"></i> Terverifikasi</span>
                            @if($p->verifier)
                                <div class="small text-muted" style="font-size: 11px;">Oleh: {{ $p->verifier->name }}</div>
                            @endif
                        @else
                            <span class="badge badge-status-rejected"><i class="bi bi-x-circle"></i> Ditolak</span>
                            @if($p->rejection_reason)
                                <div class="small text-danger" style="font-size: 11px;" title="{{ $p->rejection_reason }}">
                                    {{ Str::limit($p->rejection_reason, 20) }}
                                </div>
                            @endif
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('dashboard.payments.show', $p->id) }}" class="btn btn-sm {{ $p->status === 'pending' ? 'btn-primary-teal' : 'btn-outline-secondary' }}">
                            {{ $p->status === 'pending' ? 'Periksa Bukti' : 'Detail' }} <i class="bi bi-arrow-right"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        Tidak ditemukan catatan pembayaran yang sesuai dengan filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payments->hasPages())
    <div class="card-footer bg-white py-2">
        {{ $payments->links() }}
    </div>
    @endif
</div>
@endsection
