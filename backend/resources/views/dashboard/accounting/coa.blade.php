@extends('layouts.app')

@section('title', 'Bagan Akun (Chart of Accounts) — SIM Kos & Guesthouse')

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
            <h1 class="h3 fw-bold text-dark mb-1">Bagan Akun (Chart of Accounts)</h1>
            <p class="text-muted small mb-0">Kelola daftar akun buku besar, klasifikasi akun, dan sifat normal multi-properti.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary-teal d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#drawerAddAccount" aria-controls="drawerAddAccount">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Tambah Akun Baru</span>
            </button>
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
            <form method="GET" action="{{ route('dashboard.accounting.coa') }}" class="row g-2 align-items-center">
                <!-- Selector Properti -->
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Pilih Properti</label>
                    <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($properties as $prop)
                            <option value="{{ $prop->id }}" {{ $selectedPropertyId == $prop->id ? 'selected' : '' }}>
                                {{ $prop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Klasifikasi Tipe -->
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Klasifikasi Akun</label>
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Klasifikasi</option>
                        @foreach($accountTypes as $type)
                            <option value="{{ $type->value }}" {{ request('type') == $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Status -->
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="is_active" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Non-Aktif</option>
                    </select>
                </div>

                <!-- Search -->
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Pencarian</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Cari kode atau nama akun..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        @if(request()->anyFilled(['type', 'is_active', 'search']))
                            <a href="{{ route('dashboard.accounting.coa', ['property_id' => $selectedPropertyId]) }}" class="btn btn-outline-danger" title="Reset Filter"><i class="bi bi-x-circle"></i></a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Akun -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-list-ul text-teal"></i>
                <span>Daftar Akun Buku Besar ({{ count($accounts) }} Akun)</span>
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 120px;">Kode</th>
                        <th>Nama Akun</th>
                        <th>Klasifikasi</th>
                        <th>Sifat Normal</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th class="text-end pe-3" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $acc)
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark font-monospace fw-bold px-2 py-1 border">
                                    {{ $acc->code }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $acc->name }}</div>
                                @if($acc->description)
                                    <small class="text-muted d-block text-truncate" style="max-width: 350px;">{{ $acc->description }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $acc->type->badgeClass() }}-subtle text-{{ $acc->type->badgeClass() }} fw-semibold px-2 py-1 rounded-pill">
                                    {{ $acc->type->label() }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $acc->isDebitNormal() ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' }} px-2 py-1">
                                    {{ $acc->normal_balance->label() }}
                                </span>
                            </td>
                            <td>
                                <span class="small text-muted">{{ $acc->category ? ucwords(str_replace('_', ' ', $acc->category)) : '-' }}</span>
                            </td>
                            <td>
                                @if($acc->is_active)
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Non-Aktif</span>
                                @endif
                                @if($acc->is_system)
                                    <span class="badge bg-dark-subtle text-dark ms-1" title="Akun sistem standar"><i class="bi bi-lock-fill"></i></span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary" 
                                        data-bs-toggle="offcanvas" 
                                        data-bs-target="#drawerEditAccount"
                                        onclick="openEditDrawer({{ json_encode($acc) }})">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ditemukan akun buku besar yang cocok dengan kriteria pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RIGHT-SIDE DRAWER: Tambah Akun Baru -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="drawerAddAccount" aria-labelledby="drawerAddAccountLabel" style="width: 480px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2" id="drawerAddAccountLabel">
            <i class="bi bi-plus-circle text-teal"></i>
            <span>Tambah Akun Baru</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form method="POST" action="{{ route('dashboard.accounting.coa.store') }}">
        @csrf
        <input type="hidden" name="property_id" value="{{ $selectedPropertyId }}">

        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Kode Akun <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control font-monospace" placeholder="Misal: 1104, 5107" required maxlength="20">
                <div class="form-text small">Gunakan awalan 1 (Aset), 2 (Kewajiban), 3 (Ekuitas), 4 (Pendapatan), atau 5 (Beban).</div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Nama Akun <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="Misal: Kas Kecil Operasional" required maxlength="100">
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Klasifikasi Tipe <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" required>
                        @foreach($accountTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Sifat Normal</label>
                    <select name="normal_balance" class="form-select">
                        <option value="">Default Sesuai Tipe</option>
                        @foreach($normalBalances as $nb)
                            <option value="{{ $nb->value }}">{{ $nb->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Kategori Spesifik</label>
                <input type="text" name="category" class="form-control" placeholder="Misal: cash_bank, receivable, operating_expense">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Deskripsi / Catatan Akun</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Keterangan peruntukan akun ini..."></textarea>
            </div>
        </div>
        <div class="offcanvas-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-primary-teal">Simpan Akun</button>
        </div>
    </form>
</div>

<!-- RIGHT-SIDE DRAWER: Edit Akun -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="drawerEditAccount" aria-labelledby="drawerEditAccountLabel" style="width: 480px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2" id="drawerEditAccountLabel">
            <i class="bi bi-pencil-square text-teal"></i>
            <span>Edit Akun Buku Besar</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form id="formEditAccount" method="POST" action="">
        @csrf
        @method('PUT')

        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Kode Akun</label>
                <input type="text" id="edit_code" class="form-control font-monospace bg-light" readonly>
                <div class="form-text small" id="edit_code_hint">Kode akun sistem bawaan tidak dapat diubah.</div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Nama Akun <span class="text-danger">*</span></label>
                <input type="text" name="name" id="edit_name" class="form-control" required maxlength="100">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Kategori Spesifik</label>
                <input type="text" name="category" id="edit_category" class="form-control" placeholder="Misal: cash_bank, receivable">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Status Keaktifan</label>
                <select name="is_active" id="edit_is_active" class="form-select">
                    <option value="1">Aktif</option>
                    <option value="0">Non-Aktif (Arsip)</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Deskripsi / Catatan Akun</label>
                <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
            </div>
        </div>
        <div class="offcanvas-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-primary-teal">Simpan Perubahan</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function openEditDrawer(account) {
        const form = document.getElementById('formEditAccount');
        form.action = `/dashboard/accounting/coa/${account.id}`;

        document.getElementById('edit_code').value = account.code;
        document.getElementById('edit_name').value = account.name;
        document.getElementById('edit_category').value = account.category || '';
        document.getElementById('edit_is_active').value = account.is_active ? '1' : '0';
        document.getElementById('edit_description').value = account.description || '';

        const codeHint = document.getElementById('edit_code_hint');
        if (account.is_system) {
            codeHint.textContent = 'Akun sistem standar: kode akun terkunci demi integritas posting otomatis.';
        } else {
            codeHint.textContent = 'Akun kustom pengguna.';
        }
    }
</script>
@endpush
@endsection
