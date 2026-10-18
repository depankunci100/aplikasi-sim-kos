import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../providers/deposit_provider.dart';

class DepositDetailScreen extends ConsumerWidget {
  final int depositId;

  const DepositDetailScreen({super.key, required this.depositId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final depositAsync = ref.watch(depositDetailProvider(depositId));

    return Scaffold(
      appBar: AppBar(
        title: Text('Deposit #$depositId'),
      ),
      body: depositAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              Text('Gagal memuat detail deposit: $err'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => ref.refresh(depositDetailProvider(depositId)),
                child: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
        data: (deposit) {
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(depositDetailProvider(depositId)),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Header Card
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
                              deposit.depositCode,
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppColors.primary),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: Colors.green.shade50,
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: Colors.green.shade700.withOpacity(0.3)),
                              ),
                              child: Text(
                                deposit.statusLabel,
                                style: TextStyle(color: Colors.green.shade700, fontSize: 11, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(deposit.tenantName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        Text('${deposit.propertyName} • Kamar ${deposit.roomNumber}', style: const TextStyle(color: AppColors.textSecondary)),
                        if (deposit.bookingCode != null)
                          Text('Pemesanan: ${deposit.bookingCode}', style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Reconciliation Card
                Card(
                  elevation: 1,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Rekonsiliasi Saldo Uang Jaminan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        const Divider(height: 20),
                        _buildRow('Target Uang Jaminan', Formatters.currency(deposit.initialDepositAmount)),
                        _buildRow('Penerimaan Terverifikasi', '+${Formatters.currency(deposit.totalReceived)}', color: Colors.green),
                        if (deposit.totalDeductions > 0)
                          _buildRow('Potongan Denda/Kerusakan', '-${Formatters.currency(deposit.totalDeductions)}', color: Colors.red),
                        if (deposit.totalRefunded > 0)
                          _buildRow('Pengembalian (Refund)', '-${Formatters.currency(deposit.totalRefunded)}', color: Colors.blue),
                        const Divider(height: 20),
                        _buildRow(
                          'Saldo Tersedia (Saat Ini)',
                          Formatters.currency(deposit.currentBalance),
                          isBold: true,
                          color: deposit.currentBalance > 0 ? Colors.green.shade800 : Colors.grey.shade700,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Riwayat Mutasi
                const Text('Riwayat Mutasi Uang Jaminan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 8),
                if (deposit.mutations.isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16.0),
                      child: Text('Belum ada riwayat mutasi untuk deposit ini.', textAlign: TextAlign.center),
                    ),
                  )
                else
                  ...deposit.mutations.map((m) => Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          title: Text(m.typeLabel, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                          subtitle: Text(
                            '${m.mutationCode} • ${Formatters.date(m.createdAt)}\n${m.notes ?? ''}',
                            style: const TextStyle(fontSize: 12),
                          ),
                          isThreeLine: m.notes != null,
                          trailing: Text(
                            '${m.direction == 'in' ? '+' : '-'}${Formatters.currency(m.amount)}',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: m.direction == 'in' ? Colors.green : Colors.red,
                            ),
                          ),
                        ),
                      )),
              ],
            ),
          );
        },
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
}
