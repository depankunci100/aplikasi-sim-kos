@extends('layouts.app')

@section('title', 'Kategori Pengeluaran Operasional - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.expenses.index') }}" class="text-decoration-none">Pengeluaran</a></li>
                <li class="breadcrumb-item active">Kategori</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0"><i class="bi bi-tags text-primary-teal me-2"></i>Kategori Pengeluaran</h3>
        <p class="text-muted mb-0">Kelola klasifikasi pos anggaran dan pengeluaran operasional cabang.</p>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->isSuperAdmin() || auth()->user()->isOwner() || auth()->user()->isManager())
        <button type="button" class="btn btn-primary-teal btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
            <i class="bi bi-plus-circle"></i> Tambah Kategori
        </button>
        @endif
        <a href="{{ route('dashboard.expenses.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nama Kategori</th>
                        <th>Kode Sistem</th>
                        <th>Deskripsi</th>
                        <th>Cakupan Properti</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                    <tr>
                        <td class="ps-3 fw-bold text-dark">{{ $cat->name }}</td>
                        <td><code class="bg-light px-2 py-1 rounded text-dark">{{ $cat->code }}</code></td>
                        <td><small class="text-muted">{{ $cat->description ?: '-' }}</small></td>
                        <td>
                            @if($cat->property)
                                <span class="badge bg-light text-dark border">{{ $cat->property->name }}</span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">Global (Semua Cabang)</span>
                            @endif
                        </td>
                        <td>
                            @if($cat->is_active)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            @if(auth()->user()->isSuperAdmin() || auth()->user()->isOwner() || auth()->user()->isManager())
                            <form action="{{ route('dashboard.expenses.categories.toggle', $cat->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $cat->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                        onclick="return confirm('Apakah Anda yakin mengubah status kategori ini?')">
                                    {{ $cat->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Belum ada kategori pengeluaran terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Category -->
<div class="modal fade" id="modalAddCategory" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dashboard.expenses.categories.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Kategori Pengeluaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Pembelian Gas & Galon" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kode Unik Sistem (huruf kecil & garis bawah) <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="Contoh: gas_galon" required pattern="^[a-z0-9_]+$">
                        <small class="text-muted">Hanya karakter huruf kecil, angka, dan underscore.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Cabang Properti (Opsional)</label>
                        <select name="property_id" class="form-select">
                            <option value="">Global (Berlaku untuk semua properti)</option>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}">{{ $prop->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Keterangan pos pengeluaran ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-teal btn-sm px-3">Simpan Kategori</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
