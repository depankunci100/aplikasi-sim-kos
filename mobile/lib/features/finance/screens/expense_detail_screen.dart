import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/expense_model.dart';
import '../providers/expense_provider.dart';

class ExpenseDetailScreen extends ConsumerStatefulWidget {
  final int expenseId;
  final ExpenseModel? initialExpense;

  const ExpenseDetailScreen({
    super.key,
    required this.expenseId,
    this.initialExpense,
  });

  @override
  ConsumerState<ExpenseDetailScreen> createState() => _ExpenseDetailScreenState();
}

class _ExpenseDetailScreenState extends ConsumerState<ExpenseDetailScreen> {
  ExpenseModel? _expense;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _expense = widget.initialExpense;
    _fetchDetail();
  }

  Future<void> _fetchDetail() async {
    final client = ref.read(apiClientProvider);
    try {
      final res = await client.dio.get('${ApiEndpoints.expenses}/${widget.expenseId}');
      if (res.data['success'] == true) {
        if (mounted) {
          setState(() {
            _expense = ExpenseModel.fromJson(res.data['data'] as Map<String, dynamic>);
          });
        }
      }
    } catch (_) {
      // Use initialExpense if available
    }
  }

  Future<void> _submitForApproval() async {
    setState(() => _isLoading = true);
    final client = ref.read(apiClientProvider);
    try {
      await client.dio.post('${ApiEndpoints.expenses}/${widget.expenseId}/submit');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pengeluaran berhasil diajukan untuk persetujuan.')),
        );
        ref.invalidate(expenseListProvider);
        _fetchDetail();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mengajukan: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _approveExpense() async {
    final notesController = TextEditingController();
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Setujui Pengeluaran'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Setujui pengeluaran ${_expense?.expenseNumber}?'),
            const SizedBox(height: 12),
            TextField(
              controller: notesController,
              decoration: const InputDecoration(
                labelText: 'Catatan Persetujuan (Opsional)',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            child: const Text('Setujui', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isLoading = true);
    final client = ref.read(apiClientProvider);
    try {
      await client.dio.post('${ApiEndpoints.expenses}/${widget.expenseId}/approve', data: {
        'notes': notesController.text,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pengeluaran berhasil disetujui.')),
        );
        ref.invalidate(expenseListProvider);
        _fetchDetail();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal menyetujui: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _rejectExpense() async {
    final reasonController = TextEditingController();
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Tolak Pengeluaran', style: TextStyle(color: Colors.red)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Harap masukkan alasan penolakan pengeluaran ini:'),
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(
                labelText: 'Alasan Penolakan *',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () {
              if (reasonController.text.trim().isEmpty) return;
              Navigator.pop(ctx, true);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Tolak', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isLoading = true);
    final client = ref.read(apiClientProvider);
    try {
      await client.dio.post('${ApiEndpoints.expenses}/${widget.expenseId}/reject', data: {
        'rejection_reason': reasonController.text,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pengeluaran telah ditolak.')),
        );
        ref.invalidate(expenseListProvider);
        _fetchDetail();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal menolak: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _recordPayment() async {
    final amountController = TextEditingController(text: _expense?.remainingBalance.toInt().toString());
    String method = 'cash';

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => AlertDialog(
          title: const Text('Catat Pembayaran'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Sisa tagihan: ${_formatRupiah(_expense?.remainingBalance ?? 0)}'),
              const SizedBox(height: 12),
              TextField(
                controller: amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Nominal Bayar (Rp) *',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: method,
                decoration: const InputDecoration(
                  labelText: 'Metode Pembayaran',
                  border: OutlineInputBorder(),
                ),
                items: const [
                  DropdownMenuItem(value: 'cash', child: Text('Tunai / Kas Kecil')),
                  DropdownMenuItem(value: 'bank_transfer', child: Text('Transfer Bank')),
                  DropdownMenuItem(value: 'qris', child: Text('QRIS')),
                ],
                onChanged: (val) {
                  if (val != null) setModalState(() => method = val);
                },
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Batal')),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
              child: const Text('Simpan Pembayaran', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      ),
    );

    if (confirm != true) return;

    final payAmount = num.tryParse(amountController.text) ?? 0;
    if (payAmount <= 0) return;

    setState(() => _isLoading = true);
    final client = ref.read(apiClientProvider);
    try {
      await client.dio.post('${ApiEndpoints.expenses}/${widget.expenseId}/pay', data: {
        'amount': payAmount,
        'payment_date': DateTime.now().toIso8601String().split('T').first,
        'payment_method': method,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Pembayaran berhasil dicatat.')),
        );
        ref.invalidate(expenseListProvider);
        _fetchDetail();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mencatat pembayaran: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final exp = _expense;

    if (exp == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Detail Pengeluaran')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(exp.expenseNumber),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _fetchDetail,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Header status
                Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: Colors.grey.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              exp.expenseNumber,
                              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.grey),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: exp.isApproved ? Colors.green.shade50 : Colors.orange.shade50,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                exp.approvalStatus.toUpperCase(),
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 11,
                                  color: exp.isApproved ? Colors.green.shade700 : Colors.orange.shade700,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          exp.title,
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${exp.propertyName} • ${exp.categoryName}',
                          style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // Amount breakdown
                Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: Colors.grey.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        _buildRow('Total Biaya', _formatRupiah(exp.amount), isBold: true),
                        const Divider(height: 20),
                        _buildRow('Telah Dibayar', _formatRupiah(exp.paidAmount), color: Colors.green),
                        const Divider(height: 20),
                        _buildRow('Sisa Tagihan', _formatRupiah(exp.remainingBalance), color: Colors.redAccent, isBold: true),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // Details Card
                Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: Colors.grey.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Informasi Lengkap', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        const SizedBox(height: 12),
                        _buildRow('Vendor / Toko', exp.vendorName ?? '-'),
                        const SizedBox(height: 8),
                        _buildRow('Tanggal', exp.expenseDate != null ? '${exp.expenseDate!.day}/${exp.expenseDate!.month}/${exp.expenseDate!.year}' : '-'),
                        const SizedBox(height: 8),
                        _buildRow('Diajukan Oleh', exp.submitterName ?? '-'),
                        if (exp.approverName != null) ...[
                          const SizedBox(height: 8),
                          _buildRow('Disetujui Oleh', exp.approverName!, color: Colors.green),
                        ],
                        if (exp.notes != null && exp.notes!.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          const Text('Catatan:', style: TextStyle(fontSize: 12, color: Colors.grey)),
                          const SizedBox(height: 4),
                          Text(exp.notes!, style: const TextStyle(fontSize: 13)),
                        ],
                        if (exp.rejectionReason != null) ...[
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(8)),
                            child: Text('Alasan Penolakan: ${exp.rejectionReason}', style: TextStyle(color: Colors.red.shade800, fontSize: 13)),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 20),

                // Action Buttons Section
                if (exp.isDraft) ...[
                  ElevatedButton.icon(
                    onPressed: _submitForApproval,
                    icon: const Icon(Icons.send_rounded),
                    label: const Text('Ajukan Persetujuan'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                  ),
                ],

                if (exp.isSubmitted) ...[
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: _approveExpense,
                          icon: const Icon(Icons.check_circle_rounded),
                          label: const Text('Setujui'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.green,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _rejectExpense,
                          icon: const Icon(Icons.cancel_rounded),
                          label: const Text('Tolak'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.red,
                            side: const BorderSide(color: Colors.red),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],

                if (exp.isApproved && !exp.isPaid) ...[
                  ElevatedButton.icon(
                    onPressed: _recordPayment,
                    icon: const Icon(Icons.payment_rounded),
                    label: const Text('Catat Pembayaran'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                  ),
                ],
              ],
            ),
    );
  }

  Widget _buildRow(String label, String value, {Color? color, bool isBold = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13)),
        Text(
          value,
          style: TextStyle(
            fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
            fontSize: isBold ? 15 : 13,
            color: color ?? Colors.black87,
          ),
        ),
      ],
    );
  }

  static String _formatRupiah(num value) {
    final str = value.toInt().toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]}.',
        );
    return 'Rp $str';
  }
}
