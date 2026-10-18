@extends('layouts.app')

@section('title', 'Papan Pengumuman - SIM Kos & Guesthouse')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-megaphone text-primary-teal me-2"></i>Papan Pengumuman</h3>
        <p class="text-muted mb-0">Publikasikan informasi penting, tata tertib, dan pemberitahuan operasional bagi penghuni dan staf.</p>
    </div>
    @if($canManage)
    <button type="button" class="btn btn-primary-teal d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#createAnnouncementDrawer" aria-controls="createAnnouncementDrawer">
        <i class="bi bi-plus-circle"></i>
        <span>Buat Pengumuman</span>
    </button>
    @endif
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('dashboard.announcements.index') }}" class="row g-2 align-items-center">
            @if(!auth()->user()->isTenant())
            <div class="col-md-4">
                <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Semua Properti / Global --</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" {{ (isset($filters['property_id']) && $filters['property_id'] == $property->id) ? 'selected' : '' }}>
                            {{ $property->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="target_role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Semua Target Audiens --</option>
                    <option value="all" {{ (isset($filters['target_role']) && $filters['target_role'] == 'all') ? 'selected' : '' }}>Semua Pengguna</option>
                    <option value="tenant" {{ (isset($filters['target_role']) && $filters['target_role'] == 'tenant') ? 'selected' : '' }}>Khusus Penghuni (Tenant)</option>
                    <option value="staff" {{ (isset($filters['target_role']) && $filters['target_role'] == 'staff') ? 'selected' : '' }}>Khusus Staf Operasional</option>
                </select>
            </div>
            @endif
            <div class="col-md-4 ms-auto">
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul pengumuman..." value="{{ $filters['search'] ?? '' }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    @if(!empty($filters['search']) || !empty($filters['property_id']) || !empty($filters['target_role']))
                        <a href="{{ route('dashboard.announcements.index') }}" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Announcements List -->
<div class="row g-3">
    @forelse($announcements as $announcement)
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-4 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        @if($announcement->property)
                            <span class="badge bg-primary-teal text-white me-1">{{ $announcement->property->name }}</span>
                        @else
                            <span class="badge bg-secondary me-1"><i class="bi bi-globe me-1"></i>Semua Properti</span>
                        @endif

                        @if($announcement->target_role === 'tenant')
                            <span class="badge bg-info text-dark">Penghuni</span>
                        @elseif($announcement->target_role === 'staff')
                            <span class="badge bg-warning text-dark">Staf</span>
                        @else
                            <span class="badge bg-light text-secondary border">Umum</span>
                        @endif
                    </div>
                    @if(!$announcement->is_published)
                        <span class="badge bg-danger">Draft</span>
                    @endif
                </div>

                <h5 class="fw-bold mb-2 text-dark">{{ $announcement->title }}</h5>
                <p class="text-muted small mb-3 flex-grow-1" style="white-space: pre-line;">{{ Str::limit($announcement->content, 180) }}</p>

                <div class="pt-3 border-top mt-auto">
                    <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                        <div>
                            <i class="bi bi-person me-1"></i>{{ $announcement->author->name ?? 'Admin' }}
                        </div>
                        <div>
                            <i class="bi bi-clock me-1"></i>{{ $announcement->published_at ? $announcement->published_at->format('d M Y') : $announcement->created_at->format('d M Y') }}
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        @if($announcement->attachment_path)
                            <a href="{{ asset('storage/' . $announcement->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                <i class="bi bi-paperclip"></i>
                                <span>Lampiran</span>
                            </a>
                        @else
                            <span></span>
                        @endif

                        @if($canManage && (auth()->user()->isSuperAdmin() || $announcement->author_id === auth()->id()))
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#editDrawer{{ $announcement->id }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('dashboard.announcements.destroy', $announcement->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengumuman ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Offcanvas Drawer per announcement -->
    @if($canManage)
    <div class="offcanvas offcanvas-end drawer-md" tabindex="-1" id="editDrawer{{ $announcement->id }}" aria-labelledby="editDrawerLabel{{ $announcement->id }}">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold" id="editDrawerLabel{{ $announcement->id }}"><i class="bi bi-pencil-square me-2 text-primary-teal"></i>Edit Pengumuman</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <form method="POST" action="{{ route('dashboard.announcements.update', $announcement->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="offcanvas-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Properti Target</label>
                    <select name="property_id" class="form-select">
                        <option value="">Semua Properti (Global)</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ $announcement->property_id == $property->id ? 'selected' : '' }}>
                                {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Target Penerima</label>
                    <select name="target_role" class="form-select" required>
                        <option value="all" {{ $announcement->target_role == 'all' ? 'selected' : '' }}>Semua Pengguna</option>
                        <option value="tenant" {{ $announcement->target_role == 'tenant' ? 'selected' : '' }}>Hanya Penghuni (Tenant)</option>
                        <option value="staff" {{ $announcement->target_role == 'staff' ? 'selected' : '' }}>Hanya Staf Operasional</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Judul Pengumuman <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ $announcement->title }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Isi Pengumuman <span class="text-danger">*</span></label>
                    <textarea name="content" class="form-control" rows="6" required>{{ $announcement->content }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Ganti Berkas Lampiran</label>
                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="form-text">Maksimal 5 MB (JPG, PNG, atau PDF).</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_published" value="1" id="publishCheck{{ $announcement->id }}" {{ $announcement->is_published ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="publishCheck{{ $announcement->id }}">Terbitkan Langsung</label>
                </div>
            </div>
            <div class="offcanvas-footer border-top p-3 d-flex justify-content-end gap-2 bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Batal</button>
                <button type="submit" class="btn btn-primary-teal">Simpan Perubahan</button>
            </div>
        </form>
    </div>
    @endif

    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm text-center p-5">
            <div class="py-4">
                <i class="bi bi-megaphone text-muted" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold mt-3 text-secondary">Belum Ada Pengumuman</h5>
                <p class="text-muted">Belum ada pengumuman yang diterbitkan untuk cakupan properti ini.</p>
                @if($canManage)
                <button type="button" class="btn btn-primary-teal mt-2" data-bs-toggle="offcanvas" data-bs-target="#createAnnouncementDrawer">
                    <i class="bi bi-plus-circle me-1"></i>Buat Pengumuman Baru
                </button>
                @endif
            </div>
        </div>
    </div>
    @endforelse
</div>

<div class="mt-4">
    {{ $announcements->links() }}
</div>

<!-- Create Announcement Drawer -->
@if($canManage)
<div class="offcanvas offcanvas-end drawer-md" tabindex="-1" id="createAnnouncementDrawer" aria-labelledby="createAnnouncementDrawerLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="createAnnouncementDrawerLabel"><i class="bi bi-megaphone-fill me-2 text-primary-teal"></i>Buat Pengumuman Baru</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <form method="POST" action="{{ route('dashboard.announcements.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="offcanvas-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Properti Target</label>
                <select name="property_id" class="form-select">
                    <option value="">Semua Properti (Global)</option>
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}" {{ (isset($filters['property_id']) && $filters['property_id'] == $property->id) ? 'selected' : '' }}>
                            {{ $property->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Pilih properti khusus atau biarkan kosong untuk menyiarkan ke seluruh cabang kos.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Target Penerima</label>
                <select name="target_role" class="form-select" required>
                    <option value="all" selected>Semua Pengguna</option>
                    <option value="tenant">Hanya Penghuni (Tenant)</option>
                    <option value="staff">Hanya Staf Operasional</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Judul Pengumuman <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Contoh: Pemadaman Listrik Berkala Tanggal 20 Okt" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Isi Pengumuman <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="6" placeholder="Tuliskan isi detail pengumuman yang jelas dan mudah dipahami..." required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Lampiran Dokumen / Foto (Opsional)</label>
                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                <div class="form-text">Maksimal 5 MB (JPG, PNG, atau PDF).</div>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="is_published" value="1" id="publishCheckNew" checked>
                <label class="form-check-label fw-semibold" for="publishCheckNew">Terbitkan Langsung ke Pengguna</label>
            </div>
        </div>
        <div class="offcanvas-footer border-top p-3 d-flex justify-content-end gap-2 bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-primary-teal">Publikasikan Pengumuman</button>
        </div>
    </form>
</div>
@endif
@endsection
