@extends('layouts.app')

@section('title', 'Edit Draft Pengeluaran - SIM Kos & Guesthouse')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.expenses.index') }}" class="text-decoration-none">Pengeluaran</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.expenses.show', $expense->id) }}" class="text-decoration-none">{{ $expense->expense_number }}</a></li>
                        <li class="breadcrumb-item active">Edit Draft</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0">Edit Draft Pengeluaran {{ $expense->expense_number }}</h4>
            </div>
            <a href="{{ route('dashboard.expenses.show', $expense->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('dashboard.expenses.update', $expense->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cabang Properti</label>
                            <input type="text" class="form-control" value="{{ $expense->property->name }}" disabled>
                            <small class="text-muted">Properti tidak dapat diubah setelah draft dibuat.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kategori Pengeluaran <span class="text-danger">*</span></label>
                            <select name="expense_category_id" class="form-select @error('expense_category_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('expense_category_id', $expense->expense_category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Judul Pengeluaran <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $expense->title) }}" required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vendor / Toko / Penerima</label>
                            <input type="text" name="vendor_name" class="form-control @error('vendor_name') is-invalid @enderror"
                                   value="{{ old('vendor_name', $expense->vendor_name) }}">
                            @error('vendor_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nominal Biaya (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount', $expense->amount) }}" required>
                            @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tanggal Pengeluaran <span class="text-danger">*</span></label>
                            <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror"
                                   value="{{ old('expense_date', $expense->expense_date?->toDateString()) }}" required>
                            @error('expense_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rencana Metode Bayar</label>
                            <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                                <option value="cash" {{ old('payment_method', $expense->payment_method) == 'cash' ? 'selected' : '' }}>Tunai / Kas Kecil</option>
                                <option value="bank_transfer" {{ old('payment_method', $expense->payment_method) == 'bank_transfer' ? 'selected' : '' }}>Transfer Bank</option>
                                <option value="qris" {{ old('payment_method', $expense->payment_method) == 'qris' ? 'selected' : '' }}>QRIS / E-Wallet</option>
                                <option value="other" {{ old('payment_method', $expense->payment_method) == 'other' ? 'selected' : '' }}>Lainnya</option>
                            </select>
                            @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Ganti Bukti Nota / Kuitansi (Opsional)</label>
                            <input type="file" name="receipt" class="form-control @error('receipt') is-invalid @enderror"
                                   accept="image/jpeg,image/png,image/webp,application/pdf">
                            @if($expense->receipt_photo)
                                <small class="text-muted">Bukti kuitansi sudah terunggah sebelumnya. Biarkan kosong jika tidak ingin mengganti.</small>
                            @endif
                            @error('receipt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $expense->notes) }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 mt-4 d-flex justify-content-end gap-2 border-top pt-3">
                            <a href="{{ route('dashboard.expenses.show', $expense->id) }}" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary-teal px-4">
                                <i class="bi bi-save me-1"></i>Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
