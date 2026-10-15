import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../models/maintenance_model.dart';
import '../providers/maintenance_provider.dart';

class MaintenanceListScreen extends ConsumerStatefulWidget {
  const MaintenanceListScreen({super.key});

  @override
  ConsumerState<MaintenanceListScreen> createState() => _MaintenanceListScreenState();
}

class _MaintenanceListScreenState extends ConsumerState<MaintenanceListScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final maintenanceAsync = ref.watch(maintenanceListProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Perbaikan & Maintenance'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(maintenanceListProvider),
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          tabs: const [
            Tab(text: 'Semua'),
            Tab(text: 'Diproses'),
            Tab(text: 'Selesai'),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/maintenance/new'),
        backgroundColor: AppColors.primary,
        icon: const Icon(Icons.build_rounded, color: Colors.white),
        label: const Text('Lapor Kerusakan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      body: maintenanceAsync.when(
        data: (requests) {
          if (requests.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.handyman_outlined, size: 64, color: Colors.grey.shade400),
                  const SizedBox(height: 16),
                  const Text('Belum Ada Tiket Perbaikan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  const Text('Ajukan perbaikan kamar jika terdapat fasilitas yang rusak.', style: TextStyle(color: AppColors.textSecondary)),
                ],
              ),
            );
          }

          final activeRequests = requests.where((r) => !['completed', 'cancelled', 'rejected'].contains(r.status)).toList();
          final finishedRequests = requests.where((r) => ['completed', 'cancelled', 'rejected'].contains(r.status)).toList();

          return TabBarView(
            controller: _tabController,
            children: [
              _buildListView(requests),
              _buildListView(activeRequests),
              _buildListView(finishedRequests),
            ],
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(child: Text('Gagal memuat tiket: $err')),
      ),
    );
  }

  Widget _buildListView(List<MaintenanceRequestModel> list) {
    if (list.isEmpty) {
      return const Center(child: Text('Tidak ada tiket pada kategori ini.', style: TextStyle(color: AppColors.textSecondary)));
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: list.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final item = list[index];
        return Card(
          elevation: 1,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: InkWell(
            onTap: () => context.push('/maintenance/${item.id}', extra: item),
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        item.ticketNumber,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontFamily: 'monospace', color: AppColors.textPrimary),
                      ),
                      _buildStatusBadge(item.status, item.statusLabel),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    item.title,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    item.description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                  ),
                  const SizedBox(height: 12),
                  const Divider(height: 1),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.room_outlined, size: 16, color: AppColors.textSecondary),
                          const SizedBox(width: 4),
                          Text(
                            item.roomNumber != null ? 'Kamar ${item.roomNumber}' : (item.propertyName ?? 'Area Bersama'),
                            style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withAlpha(20),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          item.categoryLabel,
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.primary),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildStatusBadge(String status, String label) {
    Color bg = Colors.grey.shade200;
    Color fg = Colors.grey.shade800;

    switch (status) {
      case 'reported':
        bg = Colors.blue.shade50;
        fg = Colors.blue.shade700;
        break;
      case 'in_progress':
        bg = Colors.orange.shade50;
        fg = Colors.orange.shade700;
        break;
      case 'pending_verification':
        bg = Colors.amber.shade50;
        fg = Colors.amber.shade800;
        break;
      case 'completed':
        bg = Colors.green.shade50;
        fg = Colors.green.shade700;
        break;
      case 'cancelled':
      case 'rejected':
        bg = Colors.red.shade50;
        fg = Colors.red.shade700;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Text(label, style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.bold)),
    );
  }
}
