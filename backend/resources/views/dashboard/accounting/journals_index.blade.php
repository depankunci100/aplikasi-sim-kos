@extends('layouts.app')

@section('title', 'Jurnal Umum (General Journal) — SIM Kos & Guesthouse')

@section('content')
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Akuntansi & Jurnal</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-1">Jurnal Umum (General Journal)</h1>
            <p class="text-muted small mb-0">Catatan pembukuan berpasangan otomatis dari seluruh transaksi operasional yang sah.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('dashboard.accounting.reports.trial-balance', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-scale"></i>
                <span>Neraca Saldo</span>
            </a>
            <a href="{{ route('dashboard.accounting.coa', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-teal d-flex align-items-center gap-2">
                <i class="bi bi-diagram-3"></i>
                <span>Bagan Akun (COA)</span>
            </a>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filter & Toolbar Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.accounting.journals.index') }}" class="row g-2 align-items-center">
                <!-- Selector Properti -->
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Properti</label>
                    <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ $selectedPropertyId == $prop->id ? 'selected' : '' }}>
                                {{ $prop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Rentang Tanggal -->
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                </div>

                <!-- Sumber Transaksi -->
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Sumber</label>
                    <select name="source_type" class="form-select form-select-sm">
                        <option value="">Semua Sumber</option>
                        <option value="payment" {{ request('source_type') === 'payment' ? 'selected' : '' }}>Pembayaran Sewa/Deposit</option>
                        <option value="expense_payment" {{ request('source_type') === 'expense_payment' ? 'selected' : '' }}>Beban Operasional</option>
                        <option value="check_out_settlement" {{ request('source_type') === 'check_out_settlement' ? 'selected' : '' }}>Settlement Check-Out</option>
                        <option value="deposit_mutation" {{ request('source_type') === 'deposit_mutation' ? 'selected' : '' }}>Mutasi Deposit</option>
                        <option value="reversal" {{ request('source_type') === 'reversal' ? 'selected' : '' }}>Pembalik (Reversal)</option>
                    </select>
                </div>

                <!-- Status Jurnal -->
                <div class="col-md-1">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($journalStatuses as $st)
                            <option value="{{ $st->value }}" {{ request('status') == $st->value ? 'selected' : '' }}>
                                {{ $st->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tombol Aksi -->
                <div class="col-md-2 d-flex gap-1 align-items-end pt-3">
                    <button type="submit" class="btn btn-sm btn-primary-teal w-100"><i class="bi bi-filter"></i> Filter</button>
                    @if(request()->anyFilled(['start_date', 'end_date', 'source_type', 'status', 'search']))
                        <a href="{{ route('dashboard.accounting.journals.index', ['property_id' => $selectedPropertyId]) }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Jurnal -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-journal-text text-teal"></i>
                <span>Buku Jurnal Umum ({{ $journals->total() }} Transaksi)</span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 140px;">No. Jurnal</th>
                        <th style="width: 110px;">Tanggal</th>
                        <th>Sumber Transaksi</th>
                        <th>Keterangan / Memo</th>
                        <th class="text-end">Total Nominal</th>
                        <th class="text-center" style="width: 120px;">Status</th>
                        <th class="text-end pe-3" style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($journals as $j)
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark font-monospace fw-bold px-2 py-1 border">
                                    {{ $j->journal_number }}
                                </span>
                            </td>
                            <td>
                                <span class="small text-dark">{{ $j->entry_date->format('d/m/Y') }}</span>
                            </td>
                            <td>
                                @php
                                    $sourceLabel = match($j->source_type) {
                                        'payment' => 'Penerimaan Kas/Bank',
                                        'expense_payment' => 'Pengeluaran Beban',
                                        'check_out_settlement' => 'Check-Out Settlement',
                                        'deposit_mutation' => 'Mutasi Deposit',
                                        'reversal' => 'Jurnal Pembalik',
                                        default => ucwords(str_replace('_', ' ', $j->source_type)),
                                    };
                                    $badgeColor = match($j->source_type) {
                                        'payment' => 'success',
                                        'expense_payment' => 'danger',
                                        'check_out_settlement' => 'warning',
                                        'reversal' => 'secondary',
                                        default => 'info',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeColor }}-subtle text-{{ $badgeColor }} px-2 py-1">
                                    {{ $sourceLabel }}
                                </span>
                            </td>
                            <td>
                                <div class="text-dark small fw-medium">{{ $j->description }}</div>
                                @if($j->notes)
                                    <small class="text-muted d-block">{{ $j->notes }}</small>
                                @endif
                            </td>
                            <td class="text-end fw-bold font-monospace text-dark">
                                Rp {{ number_format($j->total_amount, 2, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $j->status->badgeClass() }}-subtle text-{{ $j->status->badgeClass() }} px-2 py-1">
                                    {{ $j->status->label() }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-teal me-1" 
                                        data-bs-toggle="offcanvas" 
                                        data-bs-target="#drawerJournalDetail" 
                                        onclick="viewJournalDetail({{ $j->id }})"
                                        title="Rincian Debit/Kredit">
                                    <i class="bi bi-eye"></i> Rincian
                                </button>
                                @if($j->isPosted() && !auth()->user()->isStaff() && !auth()->user()->isTenant())
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalReverseJournal" 
                                            onclick="openReverseModal({{ $j->id }}, '{{ $j->journal_number }}')"
                                            title="Pembalik Jurnal">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ditemukan data jurnal pembukuan untuk filter yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($journals->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $journals->links() }}
            </div>
        @endif
    </div>
</div>

<!-- RIGHT-SIDE DRAWER: Rincian Jurnal & Tabel Baris Debit / Kredit -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="drawerJournalDetail" aria-labelledby="drawerJournalDetailLabel" style="width: 560px;">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2" id="drawerJournalDetailLabel">
                <i class="bi bi-journal-check text-teal"></i>
                <span id="detail_journal_number">Rincian Jurnal</span>
            </h5>
            <div class="small text-muted" id="detail_journal_date">Tanggal Jurnal</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <!-- Header Info Card -->
        <div class="card bg-light border-0 mb-3">
            <div class="card-body p-3">
                <div class="row g-2 small">
                    <div class="col-6">
                        <span class="text-muted d-block">Properti</span>
                        <strong class="text-dark" id="detail_property_name">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Status Jurnal</span>
                        <span id="detail_status_badge" class="badge bg-success">Posted</span>
                    </div>
                    <div class="col-12 mt-2">
                        <span class="text-muted d-block">Deskripsi</span>
                        <div class="text-dark" id="detail_description">-</div>
                    </div>
                    <div class="col-12 mt-2" id="detail_reversal_info" style="display: none;">
                        <span class="text-danger d-block">Informasi Pembalikan:</span>
                        <div class="small text-danger fw-semibold" id="detail_reversal_notes">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Baris Debit & Kredit -->
        <h6 class="fw-bold text-dark mb-2">Baris Pembukuan Berpasangan (Double-Entry)</h6>
        <div class="table-responsive border rounded-3 mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light small text-muted">
                    <tr>
                        <th class="ps-2">Akun Buku Besar</th>
                        <th class="text-end" style="width: 120px;">Debit (Rp)</th>
                        <th class="text-end pe-2" style="width: 120px;">Kredit (Rp)</th>
                    </tr>
                </thead>
                <tbody id="detail_lines_body">
                    <!-- Dynamic Rows -->
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td class="ps-2">TOTAL</td>
                        <td class="text-end font-monospace" id="detail_total_debit">0,00</td>
                        <td class="text-end pe-2 font-monospace" id="detail_total_credit">0,00</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div id="detail_balance_badge" class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 small border-0 mb-3">
            <i class="bi bi-check-circle-fill"></i>
            <span>Jurnal Terverifikasi Seimbang (Total Debit = Total Kredit)</span>
        </div>

        <!-- Audit Trail Metadata -->
        <div class="small text-muted border-top pt-3">
            <div class="mb-1"><i class="bi bi-fingerprint me-1"></i> Idempotency Key: <code id="detail_idempotency_key" class="text-dark">-</code></div>
            <div class="mb-1"><i class="bi bi-person me-1"></i> Diposting Oleh: <span id="detail_creator_name" class="text-dark">-</span></div>
            <div><i class="bi bi-clock-history me-1"></i> Waktu Posting: <span id="detail_posted_at" class="text-dark">-</span></div>
        </div>
    </div>
    <div class="offcanvas-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Tutup</button>
    </div>
</div>

<!-- ACTION CONFIRMATION MODAL: Pembalikan Jurnal (Reversal) -->
<div class="modal fade modal-confirm" tabindex="-1" id="modalReverseJournal" aria-labelledby="modalReverseJournalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalReverseJournalLabel">
                    <i class="bi bi-arrow-counterclockwise text-danger fs-4"></i>
                    <span>Pembalikan Jurnal (Reversal)</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formReverseJournal" method="POST" action="">
                @csrf
                <div class="modal-body py-3">
                    <p class="text-muted small mb-3">
                        Pembalikan akan membatalkan pengaruh saldo dari Jurnal <strong id="reverse_journal_number" class="text-dark"></strong> dengan membuat jurnal pembalik otomatis (seluruh baris debit dan kredit ditukar).
                    </p>

                    <div class="alert alert-warning d-flex align-items-center gap-2 small py-2 px-3 border-0 mb-3">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-warning"></i>
                        <div>Jurnal yang sudah dibalik tidak dapat diedit atau dibalik ulang demi audit trail.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alasan Pembalikan <span class="text-danger">*</span></label>
                        <textarea name="reversal_reason" class="form-control" rows="3" placeholder="Contoh: Kesalahan input nominal pembayaran / transaksi sewa dibatalkan" required minlength="5" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Balikkan Jurnal</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function viewJournalDetail(journalId) {
        fetch(`/dashboard/accounting/journals/${journalId}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const j = res.data;
                    document.getElementById('detail_journal_number').textContent = j.journal_number;
                    document.getElementById('detail_journal_date').textContent = 'Tanggal: ' + j.entry_date;
                    document.getElementById('detail_property_name').textContent = j.property ? j.property.name : '-';
                    document.getElementById('detail_description').textContent = j.description || '-';
                    document.getElementById('detail_idempotency_key').textContent = j.idempotency_key;
                    document.getElementById('detail_creator_name').textContent = j.creator ? j.creator.name : 'Sistem Otomatis';
                    document.getElementById('detail_posted_at').textContent = j.posted_at || '-';

                    const statusBadge = document.getElementById('detail_status_badge');
                    if (j.status === 'posted') {
                        statusBadge.className = 'badge bg-success-subtle text-success';
                        statusBadge.textContent = 'Diposting';
                    } else if (j.status === 'reversed') {
                        statusBadge.className = 'badge bg-secondary-subtle text-secondary';
                        statusBadge.textContent = 'Dibalik (Reversed)';
                    } else {
                        statusBadge.className = 'badge bg-warning-subtle text-warning';
                        statusBadge.textContent = 'Draf';
                    }

                    const linesBody = document.getElementById('detail_lines_body');
                    linesBody.innerHTML = '';
                    let sumDebit = 0;
                    let sumCredit = 0;

                    if (j.lines && j.lines.length > 0) {
                        j.lines.forEach(line => {
                            const d = parseFloat(line.debit) || 0;
                            const c = parseFloat(line.credit) || 0;
                            sumDebit += d;
                            sumCredit += c;

                            const accName = line.account ? `${line.account.code} - ${line.account.name}` : `Akun #${line.account_id}`;
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="ps-2">
                                    <div class="fw-medium text-dark">${accName}</div>
                                    ${line.memo ? `<small class="text-muted">${line.memo}</small>` : ''}
                                </td>
                                <td class="text-end font-monospace text-dark">${d > 0 ? formatRupiah(d) : '-'}</td>
                                <td class="text-end pe-2 font-monospace text-dark">${c > 0 ? formatRupiah(c) : '-'}</td>
                            `;
                            linesBody.appendChild(tr);
                        });
                    }

                    document.getElementById('detail_total_debit').textContent = formatRupiah(sumDebit);
                    document.getElementById('detail_total_credit').textContent = formatRupiah(sumCredit);
                }
            })
            .catch(err => console.error(err));
    }

    function openReverseModal(journalId, journalNumber) {
        const form = document.getElementById('formReverseJournal');
        form.action = `/dashboard/accounting/journals/${journalId}/reverse`;
        document.getElementById('reverse_journal_number').textContent = '#' + journalNumber;
    }

    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number);
    }
</script>
@endpush
@endsection
