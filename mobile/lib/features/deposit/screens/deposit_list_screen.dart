import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/deposit_model.dart';
import '../providers/deposit_provider.dart';

class DepositListScreen extends ConsumerStatefulWidget {
  const DepositListScreen({super.key});

  @override
  ConsumerState<DepositListScreen> createState() => _DepositListScreenState();
}

class _DepositListScreenState extends ConsumerState<DepositListScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final depositsAsync = ref.watch(depositListProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Uang Jaminan (Deposit)'),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Semua'),
            Tab(text: 'Ditahan'),
            Tab(text: 'Selesai'),
          ],
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Cari kode deposit, nama, atau kamar...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          setState(() => _searchQuery = '');
                        },
                      )
                    : null,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              ),
              onChanged: (val) => setState(() => _searchQuery = val.trim().toLowerCase()),
            ),
          ),
          Expanded(
            child: depositsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (err, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline, size: 48, color: Colors.red),
                      const SizedBox(height: 12),
                      Text('Gagal memuat deposit: $err', textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: () => ref.refresh(depositListProvider),
                        child: const Text('Coba Lagi'),
                      ),
                    ],
                  ),
                ),
              ),
              data: (allDeposits) {
                return TabBarView(
                  controller: _tabController,
                  children: [
                    _buildFilteredList(allDeposits, null),
                    _buildFilteredList(allDeposits, 'held'),
                    _buildFilteredList(allDeposits, 'settled'),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilteredList(List<DepositLedgerModel> items, String? statusFilter) {
    var filtered = items;
    if (statusFilter != null) {
      if (statusFilter == 'settled') {
        filtered = filtered.where((d) => d.status == 'settled' || d.status == 'refunded').toList();
      } else {
        filtered = filtered.where((d) => d.status == statusFilter).toList();
      }
    }

    if (_searchQuery.isNotEmpty) {
      filtered = filtered.where((d) {
        return d.depositCode.toLowerCase().contains(_searchQuery) ||
            d.tenantName.toLowerCase().contains(_searchQuery) ||
            d.roomNumber.toLowerCase().contains(_searchQuery) ||
            d.propertyName.toLowerCase().contains(_searchQuery);
      }).toList();
    }

    if (filtered.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.shield_outlined, size: 64, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            const Text('Tidak ada data deposit yang sesuai', style: TextStyle(color: AppColors.textSecondary)),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () async => ref.refresh(depositListProvider),
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: filtered.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final deposit = filtered[index];
          return Card(
            elevation: 1,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            child: InkWell(
              onTap: () => context.push('/deposits/${deposit.id}'),
              borderRadius: BorderRadius.circular(10),
              child: Padding(
                padding: const EdgeInsets.all(14.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          deposit.depositCode,
                          style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.primary),
                        ),
                        _buildStatusBadge(deposit.status, deposit.statusLabel),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      deposit.tenantName,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                    ),
                    Text(
                      '${deposit.propertyName} • Kamar ${deposit.roomNumber}',
                      style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                    ),
                    const Divider(height: 18),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Saldo Tersedia', style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                            Text(
                              Formatters.currency(deposit.currentBalance),
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 15,
                                color: deposit.currentBalance > 0 ? Colors.green.shade700 : Colors.grey,
                              ),
                            ),
                          ],
                        ),
                        if (deposit.totalDeductions > 0)
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              const Text('Denda/Potongan', style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                              Text(
                                '-${Formatters.currency(deposit.totalDeductions)}',
                                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: Colors.red),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildStatusBadge(String status, String label) {
    Color bg;
    Color fg;
    switch (status) {
      case 'held':
        bg = Colors.green.shade50;
        fg = Colors.green.shade700;
        break;
      case 'partially_refunded':
      case 'refunded':
        bg = Colors.blue.shade50;
        fg = Colors.blue.shade700;
        break;
      case 'settled':
        bg = Colors.grey.shade100;
        fg = Colors.grey.shade700;
        break;
      case 'pending':
      default:
        bg = Colors.amber.shade50;
        fg = Colors.amber.shade800;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: fg.withOpacity(0.3)),
      ),
      child: Text(
        label,
        style: TextStyle(color: fg, fontSize: 11, fontWeight: FontWeight.bold),
      ),
    );
  }
}
