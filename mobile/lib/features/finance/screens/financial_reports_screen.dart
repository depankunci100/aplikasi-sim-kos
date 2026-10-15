import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../providers/financial_dashboard_provider.dart';

class FinancialReportsScreen extends ConsumerStatefulWidget {
  const FinancialReportsScreen({super.key});

  @override
  ConsumerState<FinancialReportsScreen> createState() => _FinancialReportsScreenState();
}

class _FinancialReportsScreenState extends ConsumerState<FinancialReportsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Laporan Finansial'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          tabs: const [
            Tab(text: 'Mutasi Arus Kas'),
            Tab(text: 'Piutang & Aging'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildCashFlowTab(),
          _buildReceivablesTab(),
        ],
      ),
    );
  }

  Widget _buildCashFlowTab() {
    final cashFlowAsync = ref.watch(cashFlowTransactionsProvider);

    return cashFlowAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (err, _) => Center(child: Text('Gagal memuat arus kas: $err')),
      data: (transactions) {
        if (transactions.isEmpty) {
          return const Center(child: Text('Belum ada riwayat mutasi kas.'));
        }

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(cashFlowTransactionsProvider),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: transactions.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final tx = transactions[index];
              final isIn = tx.type == 'in';

              return Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                  side: BorderSide(color: Colors.grey.shade200),
                ),
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: isIn ? Colors.green.shade50 : Colors.red.shade50,
                    child: Icon(
                      isIn ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
                      color: isIn ? Colors.green : Colors.red,
                      size: 20,
                    ),
                  ),
                  title: Text(tx.description, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  subtitle: Text('${tx.propertyName} • ${tx.date}', style: const TextStyle(fontSize: 11)),
                  trailing: Text(
                    '${isIn ? '+' : '-'}${_formatRupiah(tx.amount)}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                      color: isIn ? Colors.green : Colors.red,
                    ),
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildReceivablesTab() {
    final receivablesAsync = ref.watch(receivablesListProvider);

    return receivablesAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (err, _) => Center(child: Text('Gagal memuat piutang: $err')),
      data: (invoices) {
        if (invoices.isEmpty) {
          return const Center(child: Text('Tidak ada piutang atau tagihan tertunggak.'));
        }

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(receivablesListProvider),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: invoices.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final inv = invoices[index];

              return Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
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
                          Text(inv.invoiceNumber, style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: Colors.orange.shade50,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(inv.agingBucket, style: TextStyle(color: Colors.orange.shade800, fontSize: 11, fontWeight: FontWeight.bold)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text('${inv.tenantName} - Kamar ${inv.roomNumber}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                      Text('${inv.propertyName} • Jatuh Tempo: ${inv.dueDate}', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      const Divider(height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Sisa Piutang', style: TextStyle(fontSize: 12, color: Colors.grey)),
                          Text(_formatRupiah(inv.remainingBalance), style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.red, fontSize: 15)),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
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
