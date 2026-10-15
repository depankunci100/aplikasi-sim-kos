import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';
import '../../properties/providers/property_provider.dart';
import '../providers/expense_provider.dart';

class ExpenseFormScreen extends ConsumerStatefulWidget {
  const ExpenseFormScreen({super.key});

  @override
  ConsumerState<ExpenseFormScreen> createState() => _ExpenseFormScreenState();
}

class _ExpenseFormScreenState extends ConsumerState<ExpenseFormScreen> {
  final _formKey = GlobalKey<FormState>();

  int? _selectedPropertyId;
  int? _selectedCategoryId;
  final _titleController = TextEditingController();
  final _vendorController = TextEditingController();
  final _amountController = TextEditingController();
  final _notesController = TextEditingController();
  String _paymentMethod = 'cash';
  bool _isLoading = false;

  @override
  void dispose() {
    _titleController.dispose();
    _vendorController.dispose();
    _amountController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit(bool submitDirectly) async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedPropertyId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih properti terlebih dahulu.')),
      );
      return;
    }

    setState(() => _isLoading = true);
    final client = ref.read(apiClientProvider);

    try {
      final res = await client.dio.post(ApiEndpoints.expenses, data: {
        'property_id': _selectedPropertyId,
        'expense_category_id': _selectedCategoryId,
        'title': _titleController.text.trim(),
        'vendor_name': _vendorController.text.trim(),
        'amount': num.tryParse(_amountController.text) ?? 0,
        'expense_date': DateTime.now().toIso8601String().split('T').first,
        'payment_method': _paymentMethod,
        'notes': _notesController.text.trim(),
        'action_type': submitDirectly ? 'submit' : 'draft',
      });

      if (res.data['success'] == true) {
        ref.invalidate(expenseListProvider);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(submitDirectly
                  ? 'Pengeluaran berhasil diajukan!'
                  : 'Draft pengeluaran berhasil disimpan.'),
            ),
          );
          context.pop();
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal menyimpan pengeluaran: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final propertiesAsync = ref.watch(propertiesProvider);
    final categoriesAsync = ref.watch(expenseCategoriesProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Catat Pengeluaran Baru'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Property Select
                  propertiesAsync.when(
                    loading: () => const LinearProgressIndicator(),
                    error: (_, __) => const SizedBox(),
                    data: (props) {
                      if (_selectedPropertyId == null && props.isNotEmpty) {
                        _selectedPropertyId = props.first.id;
                      }
                      return DropdownButtonFormField<int>(
                        value: _selectedPropertyId,
                        decoration: const InputDecoration(
                          labelText: 'Cabang Properti *',
                          border: OutlineInputBorder(),
                        ),
                        items: props.map((p) => DropdownMenuItem(value: p.id, child: Text(p.name))).toList(),
                        onChanged: (val) => setState(() => _selectedPropertyId = val),
                        validator: (val) => val == null ? 'Wajib pilih properti' : null,
                      );
                    },
                  ),
                  const SizedBox(height: 16),

                  // Category Select
                  categoriesAsync.when(
                    loading: () => const LinearProgressIndicator(),
                    error: (_, __) => const SizedBox(),
                    data: (cats) {
                      return DropdownButtonFormField<int>(
                        value: _selectedCategoryId,
                        decoration: const InputDecoration(
                          labelText: 'Kategori Pengeluaran *',
                          border: OutlineInputBorder(),
                        ),
                        items: cats.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                        onChanged: (val) => setState(() => _selectedCategoryId = val),
                        validator: (val) => val == null ? 'Wajib pilih kategori' : null,
                      );
                    },
                  ),
                  const SizedBox(height: 16),

                  // Title
                  TextFormField(
                    controller: _titleController,
                    decoration: const InputDecoration(
                      labelText: 'Judul / Keperluan Pengeluaran *',
                      hintText: 'Contoh: Token listrik atau perbaikan AC',
                      border: OutlineInputBorder(),
                    ),
                    validator: (val) => (val == null || val.trim().isEmpty) ? 'Judul wajib diisi' : null,
                  ),
                  const SizedBox(height: 16),

                  // Vendor
                  TextFormField(
                    controller: _vendorController,
                    decoration: const InputDecoration(
                      labelText: 'Vendor / Toko / Penerima',
                      hintText: 'Contoh: Toko Bangunan Berkah',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Amount
                  TextFormField(
                    controller: _amountController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Nominal Biaya (Rp) *',
                      hintText: '0',
                      border: OutlineInputBorder(),
                      prefixText: 'Rp ',
                    ),
                    validator: (val) {
                      if (val == null || val.trim().isEmpty) return 'Nominal wajib diisi';
                      final n = num.tryParse(val);
                      if (n == null || n <= 0) return 'Nominal harus lebih dari 0';
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),

                  // Payment Method
                  DropdownButtonFormField<String>(
                    value: _paymentMethod,
                    decoration: const InputDecoration(
                      labelText: 'Rencana Metode Pembayaran',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'cash', child: Text('Tunai / Kas Kecil (Petty Cash)')),
                      DropdownMenuItem(value: 'bank_transfer', child: Text('Transfer Bank')),
                      DropdownMenuItem(value: 'qris', child: Text('QRIS / E-Wallet')),
                    ],
                    onChanged: (val) {
                      if (val != null) setState(() => _paymentMethod = val);
                    },
                  ),
                  const SizedBox(height: 16),

                  // Notes
                  TextFormField(
                    controller: _notesController,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Catatan Tambahan',
                      hintText: 'Rincian kebutuhan barang/jasa...',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Buttons
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => _submit(false),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          child: const Text('Simpan Draft'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () => _submit(true),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          child: const Text('Ajukan Approval'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
    );
  }
}
