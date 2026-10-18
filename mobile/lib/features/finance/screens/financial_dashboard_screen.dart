import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../providers/financial_dashboard_provider.dart';

class FinancialDashboardScreen extends ConsumerWidget {
  const FinancialDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardAsync = ref.watch(financialDashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Keuangan & Analitik'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(financialDashboardProvider),
          ),
        ],
      ),
      body: dashboardAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              Text('Gagal memuat analitik keuangan: $err'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => ref.invalidate(financialDashboardProvider),
                child: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
        data: (data) {
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(financialDashboardProvider),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Quick Action Bar
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () => context.push('/finance/expenses'),
                        icon: const Icon(Icons.receipt_long_rounded, size: 18),
                        label: const Text('Pengeluaran'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => context.push('/finance/reports'),
                        icon: const Icon(Icons.analytics_rounded, size: 18),
                        label: const Text('Laporan Kas'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.primary,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => context.push('/finance/accounting'),
                    icon: const Icon(Icons.account_balance_rounded, size: 18),
                    label: const Text('Buku Jurnal & Neraca Akuntansi'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.secondary,
                      side: BorderSide(color: AppColors.secondary.withOpacity(0.5)),
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Primary 4 KPI Grid
                Row(
                  children: [
                    Expanded(
                      child: _buildMetricCard(
                        title: 'Kas Masuk',
                        amount: data.totalVerifiedReceipts,
                        icon: Icons.arrow_downward_rounded,
                        color: Colors.green,
                        subtitle: 'Penerimaan terverifikasi',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _buildMetricCard(
                        title: 'Kas Keluar',
                        amount: data.totalPaidExpenses,
                        icon: Icons.arrow_upward_rounded,
                        color: Colors.redAccent,
                        subtitle: 'Pengeluaran dibayar',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _buildMetricCard(
                        title: 'Arus Kas Bersih',
                        amount: data.netCashFlow,
                        icon: Icons.account_balance_wallet_rounded,
                        color: data.netCashFlow >= 0 ? AppColors.primary : Colors.red,
                        subtitle: 'Selisih transaksi kas',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _buildMetricCard(
                        title: 'Sisa Piutang',
                        amount: data.totalReceivables,
                        icon: Icons.pending_actions_rounded,
                        color: Colors.orange,
                        subtitle: '${data.unpaidInvoicesCount} tagihan belum lunas',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // Secondary operational metrics
                Card(
                  elevation: 0,
                  color: Colors.grey.shade50,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: Colors.grey.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Kinerja Operasional & Properti',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        const SizedBox(height: 16),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceAround,
                          children: [
                            _buildMiniStat(
                              label: 'Okupansi',
                              value: '${data.occupancyRate}%',
                              subvalue: '${data.occupiedRooms}/${data.totalRooms} kamar',
                              color: Colors.blue,
                            ),
                            _buildMiniStat(
                              label: 'Sewa Aktif',
                              value: '${data.activeBookingsCount}',
                              subvalue: 'penghuni',
                              color: Colors.teal,
                            ),
                            _buildMiniStat(
                              label: 'Approval',
                              value: '${data.pendingExpensesCount}',
                              subvalue: 'pengajuan',
                              color: Colors.orange,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 20),

                // Trend Section
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Tren Kas 6 Bulan Terakhir',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                    TextButton(
                      onPressed: () => context.push('/finance/reports'),
                      child: const Text('Selengkapnya'),
                    ),
                  ],
                ),
                const SizedBox(height: 8),

                ...data.chartTrend.map((t) {
                  final diff = t.income - t.expense;
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                      side: BorderSide(color: Colors.grey.shade200),
                    ),
                    child: ListTile(
                      dense: true,
                      title: Text(t.label, style: const TextStyle(fontWeight: FontWeight.bold)),
                      subtitle: Text(
                        'Masuk: ${_formatRupiah(t.income)} | Keluar: ${_formatRupiah(t.expense)}',
                        style: const TextStyle(fontSize: 12),
                      ),
                      trailing: Text(
                        '${diff >= 0 ? '+' : ''}${_formatRupiah(diff)}',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          color: diff >= 0 ? Colors.green : Colors.red,
                        ),
                      ),
                    ),
                  );
                }),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildMetricCard({
    required String title,
    required num amount,
    required IconData icon,
    required Color color,
    required String subtitle,
  }) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: Colors.grey.shade200),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(title, style: const TextStyle(fontSize: 12, color: Colors.grey, fontWeight: FontWeight.w600)),
                CircleAvatar(
                  radius: 14,
                  backgroundColor: color.withOpacity(0.15),
                  child: Icon(icon, size: 16, color: color),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              _formatRupiah(amount),
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: color),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 4),
            Text(subtitle, style: const TextStyle(fontSize: 10, color: Colors.grey), maxLines: 1),
          ],
        ),
      ),
    );
  }

  Widget _buildMiniStat({
    required String label,
    required String value,
    required String subvalue,
    required Color color,
  }) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        Text(subvalue, style: const TextStyle(fontSize: 11, color: Colors.grey)),
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
