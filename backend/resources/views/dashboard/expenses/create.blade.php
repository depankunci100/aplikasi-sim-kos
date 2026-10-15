@extends('layouts.app')

@section('title', 'Catat Pengeluaran Operasional - SIM Kos & Guesthouse')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.expenses.index') }}" class="text-decoration-none">Pengeluaran</a></li>
                        <li class="breadcrumb-item active">Catat Baru</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Catat Pengeluaran Operasional Baru</h4>
            </div>
            <a href="{{ route('dashboard.expenses.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('dashboard.expenses.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cabang Properti <span class="text-danger">*</span></label>
                            <select name="property_id" class="form-select @error('property_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Properti --</option>
                                @foreach($properties as $prop)
                                    <option value="{{ $prop->id }}" {{ old('property_id') == $prop->id ? 'selected' : '' }}>
                                        {{ $prop->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('property_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kategori Pengeluaran <span class="text-danger">*</span></label>
                            <select name="expense_category_id" class="form-select @error('expense_category_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('expense_category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Judul Pengeluaran / Keterangan Singkat <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   placeholder="Contoh: Pembelian token listrik kos cabang melati" value="{{ old('title') }}" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vendor / Toko / Penerima</label>
                            <input type="text" name="vendor_name" class="form-control @error('vendor_name') is-invalid @enderror"
                                   placeholder="Contoh: Toko Bangunan Berkah" value="{{ old('vendor_name') }}">
                            @error('vendor_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nominal Biaya (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control @error('amount') is-invalid @enderror"
                                   placeholder="0" value="{{ old('amount') }}" required>
                            @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tanggal Pengeluaran <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror"
                                   value="{{ old('expense_date', date('Y-m-d')) }}" required>
                            @error('expense_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rencana Metode Bayar</label>
                            <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Tunai / Kas Kecil (Petty Cash)</option>
                                <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Transfer Bank</option>
                                <option value="qris" {{ old('payment_method') == 'qris' ? 'selected' : '' }}>QRIS / E-Wallet</option>
                                <option value="other" {{ old('payment_method') == 'other' ? 'selected' : '' }}>Lainnya</option>
                            </select>
                            @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Bukti Nota / Kuitansi / Faktur (Format: JPG, PNG, WebP, PDF. Maks 5MB)</label>
                            <input type="file" name="receipt" class="form-control @error('receipt') is-invalid @enderror"
                                   accept="image/jpeg,image/png,image/webp,application/pdf">
                            @error('receipt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan Tambahan</label>
                            <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror"
                                      placeholder="Rincian barang/jasa atau keperluan pengeluaran...">{{ old('notes') }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 mt-4 d-flex justify-content-end gap-2 border-top pt-3">
                            <a href="{{ route('dashboard.expenses.index') }}" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" name="action_type" value="draft" class="btn btn-secondary">
                                <i class="bi bi-save me-1"></i>Simpan Draft
                            </button>
                            <button type="submit" name="action_type" value="submit" class="btn btn-primary-teal px-4">
                                <i class="bi bi-send-check me-1"></i>Ajukan Persetujuan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
