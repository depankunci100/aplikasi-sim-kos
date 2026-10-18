import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/deposit_model.dart';
import '../providers/deposit_provider.dart';

class SettlementDetailScreen extends ConsumerStatefulWidget {
  final int settlementId;

  const SettlementDetailScreen({super.key, required this.settlementId});

  @override
  ConsumerState<SettlementDetailScreen> createState() => _SettlementDetailScreenState();
}

class _SettlementDetailScreenState extends ConsumerState<SettlementDetailScreen> {
  bool _isSubmitting = false;

  @override
  Widget build(BuildContext context) {
    final settlementAsync = ref.watch(settlementDetailProvider(widget.settlementId));
    final currentUser = ref.watch(authProvider).user;

    return Scaffold(
      appBar: AppBar(
        title: Text('Settlement #${widget.settlementId}'),
      ),
      body: settlementAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              Text('Gagal memuat settlement: $err'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => ref.refresh(settlementDetailProvider(widget.settlementId)),
                child: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
        data: (settlement) {
          final isTenant = currentUser?.role == 'tenant';
          final canManageFinances = currentUser?.role == 'super_admin' ||
              currentUser?.role == 'owner' ||
              currentUser?.role == 'manager' ||
              currentUser?.role == 'receptionist';

          return RefreshIndicator(
            onRefresh: () async => ref.refresh(settlementDetailProvider(widget.settlementId)),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Header Banner
                Card(
                  elevation: 1,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              settlement.settlementCode,
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppColors.primary),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: _getStatusBg(settlement.status),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                settlement.statusLabel,
                                style: TextStyle(color: _getStatusFg(settlement.status), fontSize: 11, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(settlement.tenantName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        Text('${settlement.propertyName} • Kamar ${settlement.roomNumber}', style: const TextStyle(color: AppColors.textSecondary)),
                        Text('Tgl Check-Out: ${Formatters.date(settlement.checkOutDate)}', style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Calculation Card
                Card(
                  elevation: 1,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Rekapitulasi Hak Pengembalian', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        const Divider(height: 20),
                        _buildRow('Saldo Deposit', Formatters.currency(settlement.depositBalanceBefore)),
                        _buildRow('Total Potongan Denda', '-${Formatters.currency(settlement.totalDeductions)}', color: Colors.red),
                        const Divider(height: 20),
                        _buildRow(
                          'Sisa Hak Pengembalian (Net Refund)',
                          Formatters.currency(settlement.netRefundAmount),
                          isBold: true,
                          color: AppColors.primary,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Deductions List
                const Text('Rincian Usulan Potongan Denda', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 8),
                if (settlement.deductions.isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('Tidak ada potongan denda. Deposit dikembalikan penuh.', textAlign: TextAlign.center),
                    ),
                  )
                else
                  ...settlement.deductions.map((d) => Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          title: Text(d.title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              if (d.description != null) Text(d.description!, style: const TextStyle(fontSize: 12)),
                              if (d.isDisputed)
                                Container(
                                  margin: const EdgeInsets.only(top: 4),
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(4)),
                                  child: Text('Disengketakan: ${d.disputeReason ?? ''}', style: TextStyle(color: Colors.red.shade800, fontSize: 11)),
                                ),
                            ],
                          ),
                          trailing: Text(
                            Formatters.currency(d.approvedAmount),
                            style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.red),
                          ),
                        ),
                      )),
                const SizedBox(height: 24),

                // Tenant Action Buttons
                if (isTenant && settlement.status == 'pending_tenant_approval') ...[
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: _isSubmitting ? null : () => _respondTenant(true),
                          style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
                          icon: const Icon(Icons.check_circle_outline),
                          label: const Text('Setujui'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _isSubmitting ? null : _showDisputeDialog,
                          style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                          icon: const Icon(Icons.cancel_outlined),
                          label: const Text('Keberatan'),
                        ),
                      ),
                    ],
                  ),
                ],

                // Staff Finalize Button
                if (canManageFinances && settlement.status == 'approved') ...[
                  ElevatedButton.icon(
                    onPressed: _isSubmitting ? null : () => _showFinalizeDialog(settlement),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green.shade700,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                    icon: const Icon(Icons.check),
                    label: const Text('Finalisasi Check-Out & Refund', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  void _respondTenant(bool isApproved, [String? notes]) async {
    setState(() => _isSubmitting = true);
    final scaffoldMessenger = ScaffoldMessenger.of(context);
    try {
      final repo = ref.read(depositRepositoryProvider);
      final ok = await repo.submitTenantDecision(
        settlementId: widget.settlementId,
        decision: isApproved ? 'approve' : 'dispute',
        objectionNotes: notes,
      );
      if (ok && mounted) {
        ref.invalidate(settlementDetailProvider(widget.settlementId));
        scaffoldMessenger.showSnackBar(
          SnackBar(content: Text(isApproved ? 'Settlement disetujui.' : 'Keberatan Anda telah tercatat.')),
        );
      }
    } catch (e) {
      if (mounted) {
        scaffoldMessenger.showSnackBar(SnackBar(content: Text('Error: $e')));
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _showDisputeDialog() {
    final notesCtrl = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Ajukan Keberatan (Dispute)'),
        content: TextField(
          controller: notesCtrl,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Jelaskan alasan keberatan atas denda kerusakan...',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              _respondTenant(false, notesCtrl.text.trim());
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Kirim Keberatan'),
          ),
        ],
      ),
    );
  }

  void _showFinalizeDialog(CheckOutSettlementModel settlement) {
    final destCtrl = TextEditingController(text: settlement.refundDestination ?? '');
    final refCtrl = TextEditingController();
    String method = 'bank_transfer';
    final scaffoldMessenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (dialogCtx, setDialogState) => AlertDialog(
          title: const Text('Finalisasi Check-Out & Refund'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Konfirmasi pembayaran refund sebesar ${Formatters.currency(settlement.netRefundAmount)} dan penutupan masa sewa kamar.',
                style: const TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: method,
                decoration: const InputDecoration(labelText: 'Metode Refund'),
                items: const [
                  DropdownMenuItem(value: 'bank_transfer', child: Text('Transfer Bank')),
                  DropdownMenuItem(value: 'cash', child: Text('Tunai (Cash)')),
                  DropdownMenuItem(value: 'e-wallet', child: Text('E-Wallet')),
                ],
                onChanged: (val) => setDialogState(() => method = val!),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: destCtrl,
                decoration: const InputDecoration(labelText: 'Rekening Tujuan / Penerima'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: refCtrl,
                decoration: const InputDecoration(labelText: 'No. Bukti / Referensi (Opsional)'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(ctx);
                setState(() => _isSubmitting = true);
                try {
                  final repo = ref.read(depositRepositoryProvider);
                  await repo.finalizeRefund(
                    settlementId: widget.settlementId,
                    refundMethod: method,
                    refundDestination: destCtrl.text.trim(),
                    paymentReference: refCtrl.text.trim(),
                  );
                  if (mounted) {
                    ref.invalidate(settlementDetailProvider(widget.settlementId));
                    scaffoldMessenger.showSnackBar(
                      const SnackBar(content: Text('Check-out & refund berhasil diselesaikan!')),
                    );
                  }
                } catch (e) {
                  if (mounted) {
                    scaffoldMessenger.showSnackBar(SnackBar(content: Text('Error: $e')));
                  }
                } finally {
                  if (mounted) setState(() => _isSubmitting = false);
                }
              },
              style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
              child: const Text('Selesaikan'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRow(String label, String value, {bool isBold = false, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: isBold ? Colors.black : AppColors.textSecondary, fontWeight: isBold ? FontWeight.bold : FontWeight.normal)),
          Text(value, style: TextStyle(color: color ?? Colors.black, fontWeight: isBold ? FontWeight.bold : FontWeight.w600)),
        ],
      ),
    );
  }

  Color _getStatusBg(String status) {
    switch (status) {
      case 'settled':
        return Colors.green.shade50;
      case 'disputed':
        return Colors.red.shade50;
      case 'approved':
        return Colors.blue.shade50;
      case 'pending_tenant_approval':
      default:
        return Colors.amber.shade50;
    }
  }

  Color _getStatusFg(String status) {
    switch (status) {
      case 'settled':
        return Colors.green.shade700;
      case 'disputed':
        return Colors.red.shade700;
      case 'approved':
        return Colors.blue.shade700;
      case 'pending_tenant_approval':
      default:
        return Colors.amber.shade800;
    }
  }
}
