@extends('layouts.app')

@section('title', 'Kuitansi Tagihan ' . $invoice->invoice_number)

@push('styles')
<style>
    @media print {
        body { background: #fff !important; }
        nav, .no-print, .btn { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .container-fluid { padding: 0 !important; }
    }
</style>
@endpush

@section('content')
<div class="mb-4 no-print d-flex justify-content-between align-items-center">
    <a href="{{ route('dashboard.invoices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali ke Daftar Tagihan
    </a>
    <button onclick="window.print()" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-1">
        <i class="bi bi-printer"></i> Cetak / Unduh Kuitansi (PDF)
    </button>
</div>

<div class="card p-4 p-sm-5 bg-white mx-auto shadow-sm" style="max-width: 820px;">
    <!-- Header Invoice -->
    <div class="row align-items-center border-bottom pb-4 mb-4">
        <div class="col-sm-7">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-buildings-fill fs-3 text-primary-teal"></i>
                <h4 class="fw-bold mb-0 text-dark">{{ $invoice->property->name ?? ($invoice->booking->property->name ?? 'SIM Kos & Guesthouse') }}</h4>
            </div>
            <p class="text-muted small mb-0">
                {{ $invoice->property->address ?? ($invoice->booking->property->address ?? 'Alamat Properti') }}<br>
                Kontak: {{ $invoice->property->phone ?? ($invoice->booking->property->phone ?? '-') }}
            </p>
        </div>
        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <h3 class="fw-bold text-dark mb-0">INVOICE</h3>
            <span class="fw-bold text-primary-teal fs-6">{{ $invoice->invoice_number }}</span>
            <div class="small text-muted mt-1">Tanggal Terbit: {{ $invoice->created_at->format('d/m/Y') }}</div>
            <div class="small text-danger fw-semibold">Jatuh Tempo: {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</div>
        </div>
    </div>

    <!-- Informasi Penyewa & Kamar -->
    <div class="row mb-4">
        <div class="col-sm-6">
            <span class="text-muted small text-uppercase fw-semibold">Ditagihkan Kepada:</span>
            <h5 class="fw-bold text-dark my-1">{{ $invoice->user->name }}</h5>
            <div class="small text-muted">Email: {{ $invoice->user->email }}</div>
            <div class="small text-muted">No. HP: {{ $invoice->user->phone ?? '-' }}</div>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <span class="text-muted small text-uppercase fw-semibold">Rincian Unit Sewa:</span>
            @if($invoice->booking)
                <h5 class="fw-bold text-primary-teal my-1">Kamar {{ $invoice->booking->room->room_number ?? '-' }} ({{ $invoice->booking->room->roomType->name ?? 'Standard' }})</h5>
                <div class="small text-muted">Kode Booking: {{ $invoice->booking->booking_code }}</div>
                <div class="small text-muted">Periode Sewa: {{ $invoice->booking->start_date?->format('d/m/Y') }} s/d {{ $invoice->booking->end_date?->format('d/m/Y') }}</div>
            @else
                <div class="text-muted">-</div>
            @endif
        </div>
    </div>

    <!-- Tabel Rincian Biaya -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th>Deskripsi Rincian</th>
                    <th class="text-center" style="width: 15%;">Tipe</th>
                    <th class="text-end" style="width: 25%;">Jumlah (IDR)</th>
                </tr>
            </thead>
            <tbody>
                @if($invoice->items->isNotEmpty())
                    @foreach($invoice->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            @if(!empty($item->metadata))
                                <div class="small text-muted">
                                    Meter: <code>#{{ $item->metadata['meter_number'] ?? '-' }}</code> |
                                    Sebelumnya: {{ $item->metadata['previous_meter'] ?? '-' }} &rarr; Pembacaan: {{ $item->metadata['current_meter'] ?? '-' }}
                                    ({{ $item->quantity }} {{ $item->unit }} &times; Rp {{ number_format($item->rate, 0, ',', '.') }})
                                </div>
                            @elseif($item->unit)
                                <div class="small text-muted">{{ $item->quantity }} {{ $item->unit }} &times; Rp {{ number_format($item->rate, 0, ',', '.') }}</div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if(in_array($item->item_type, ['electricity', 'water', 'utility']))
                                <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-lightning-charge me-1"></i>{{ ucfirst($item->item_type) }}</span>
                            @else
                                <span class="badge bg-light text-dark border">{{ ucfirst($item->item_type) }}</span>
                            @endif
                        </td>
                        <td class="text-end fw-semibold">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td>
                            <strong>Biaya Sewa Pokok ({{ strtoupper($invoice->invoice_type) }})</strong>
                            <div class="small text-muted">Biaya pemakaian unit kamar untuk periode yang disepakati.</div>
                        </td>
                        <td class="text-center"><span class="badge bg-light text-dark border">Sewa</span></td>
                        <td class="text-end fw-semibold">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endif
                @if($invoice->discount > 0)
                <tr>
                    <td class="text-success">Potongan Harga / Diskon Promo</td>
                    <td class="text-center text-success"><span class="badge bg-success-subtle text-success">Diskon</span></td>
                    <td class="text-end text-success">- Rp {{ number_format($invoice->discount, 0, ',', '.') }}</td>
                </tr>
                @endif
                @if($invoice->penalty > 0)
                <tr>
                    <td class="text-danger">Denda Keterlambatan / Kerusakan</td>
                    <td class="text-center text-danger"><span class="badge bg-danger-subtle text-danger">Denda</span></td>
                    <td class="text-end text-danger">+ Rp {{ number_format($invoice->penalty, 0, ',', '.') }}</td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <th colspan="2" class="text-end">Total Tagihan:</th>
                    <th class="text-end fs-5 text-dark">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</th>
                </tr>
                <tr>
                    <th colspan="2" class="text-end text-success">Total Pembayaran Terverifikasi:</th>
                    <th class="text-end fs-6 text-success">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</th>
                </tr>
                <tr class="table-light">
                    <th colspan="2" class="text-end text-danger fw-bold">Sisa Saldo Wajib Bayar:</th>
                    <th class="text-end fs-5 text-danger fw-bold">Rp {{ number_format($invoice->remaining_balance, 0, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Riwayat Pembayaran Masuk -->
    <div class="mb-4">
        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Riwayat Pembayaran & Stempel Kasir</h6>
        @if($invoice->payments->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-striped small align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode Bayar</th>
                            <th>Tanggal & Waktu</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th>Petugas Verifikator</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->payments as $pm)
                        <tr>
                            <td><strong>{{ $pm->payment_code }}</strong></td>
                            <td>{{ $pm->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ strtoupper($pm->payment_method) }} ({{ $pm->sender_bank ?? '-' }})</td>
                            <td>Rp {{ number_format($pm->amount, 0, ',', '.') }}</td>
                            <td>
                                @if($pm->status === 'verified')
                                    <span class="badge bg-success">Sah / Diterima</span>
                                @elseif($pm->status === 'rejected')
                                    <span class="badge bg-danger">Ditolak</span>
                                @else
                                    <span class="badge bg-warning text-dark">Menunggu</span>
                                @endif
                            </td>
                            <td>{{ $pm->verifier->name ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-3 bg-light text-center small text-muted rounded">Belum ada catatan pembayaran yang diajukan untuk tagihan ini.</div>
        @endif
    </div>

    <!-- Footer Catatan & Stempel -->
    <div class="row align-items-end mt-4 pt-4 border-top">
        <div class="col-8">
            <p class="small text-muted mb-0">
                * Kuitansi ini diterbitkan secara elektronik oleh SIM Kos & Guesthouse.<br>
                * Pembayaran sah apabila status telah ditandai LUNAS atau DITERIMA oleh kasir pengelola.
            </p>
        </div>
        <div class="col-4 text-center">
            @if($invoice->isPaid())
                <div class="border border-success border-2 rounded p-2 text-success fw-bold text-uppercase" style="transform: rotate(-5deg);">
                    <i class="bi bi-patch-check-fill fs-5"></i> LUNAS TERVERIFIKASI
                </div>
            @else
                <div class="border border-danger border-2 rounded p-2 text-danger fw-bold text-uppercase" style="transform: rotate(-5deg);">
                    <i class="bi bi-hourglass-split fs-5"></i> MENUNGGU PELUNASAN
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
