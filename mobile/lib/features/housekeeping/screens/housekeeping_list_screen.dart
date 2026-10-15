import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../models/housekeeping_model.dart';
import '../providers/housekeeping_provider.dart';

class HousekeepingListScreen extends ConsumerStatefulWidget {
  const HousekeepingListScreen({super.key});

  @override
  ConsumerState<HousekeepingListScreen> createState() => _HousekeepingListScreenState();
}

class _HousekeepingListScreenState extends ConsumerState<HousekeepingListScreen> with SingleTickerProviderStateMixin {
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
    final tasksAsync = ref.watch(housekeepingListProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Jadwal Kebersihan (Housekeeping)'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(housekeepingListProvider),
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          tabs: const [
            Tab(text: 'Semua'),
            Tab(text: 'Hari Ini'),
            Tab(text: 'Selesai'),
          ],
        ),
      ),
      body: tasksAsync.when(
        data: (tasks) {
          if (tasks.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.cleaning_services_rounded, size: 64, color: Colors.grey.shade400),
                  const SizedBox(height: 16),
                  const Text('Belum Ada Jadwal Kebersihan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  const Text('Jadwal kebersihan kamar dan area bersama akan tampil di sini.', style: TextStyle(color: AppColors.textSecondary)),
                ],
              ),
            );
          }

          final today = DateTime.now();
          final todayTasks = tasks.where((t) {
            if (t.scheduledDate == null) return false;
            return t.scheduledDate!.year == today.year &&
                t.scheduledDate!.month == today.month &&
                t.scheduledDate!.day == today.day;
          }).toList();

          final finishedTasks = tasks.where((t) => t.status == 'completed').toList();

          return TabBarView(
            controller: _tabController,
            children: [
              _buildListView(tasks),
              _buildListView(todayTasks.isNotEmpty ? todayTasks : tasks),
              _buildListView(finishedTasks),
            ],
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(child: Text('Gagal memuat jadwal: $err')),
      ),
    );
  }

  Widget _buildListView(List<HousekeepingTaskModel> list) {
    if (list.isEmpty) {
      return const Center(child: Text('Tidak ada tugas pada tab ini.', style: TextStyle(color: AppColors.textSecondary)));
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: list.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final item = list[index];
        final doneCount = item.checklist.where((c) => c.isDone).length;
        final totalCount = item.checklist.length;

        return Card(
          elevation: 1,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: InkWell(
            onTap: () => context.push('/housekeeping/${item.id}', extra: item),
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
                        item.taskNumber,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontFamily: 'monospace', color: AppColors.textPrimary),
                      ),
                      _buildStatusBadge(item.status, item.statusLabel),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    item.typeLabel,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.location_on_outlined, size: 16, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Text(
                        item.roomNumber != null ? 'Kamar ${item.roomNumber}' : (item.areaName ?? item.propertyName ?? 'Area Bersama'),
                        style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Divider(height: 1),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.person_outline_rounded, size: 16, color: AppColors.textSecondary),
                          const SizedBox(width: 4),
                          Text(
                            item.assigneeName ?? 'Belum Ditugaskan',
                            style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                      if (totalCount > 0)
                        Text(
                          '$doneCount/$totalCount Checklist',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            color: doneCount == totalCount ? Colors.green : AppColors.primary,
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
      case 'scheduled':
        bg = Colors.grey.shade200;
        fg = Colors.grey.shade800;
        break;
      case 'assigned':
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
