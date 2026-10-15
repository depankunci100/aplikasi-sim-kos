import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../models/complaint_model.dart';
import '../providers/complaint_provider.dart';

class ComplaintsListScreen extends ConsumerStatefulWidget {
  const ComplaintsListScreen({super.key});

  @override
  ConsumerState<ComplaintsListScreen> createState() => _ComplaintsListScreenState();
}

class _ComplaintsListScreenState extends ConsumerState<ComplaintsListScreen> with SingleTickerProviderStateMixin {
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
    final complaintsAsync = ref.watch(complaintsListProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Keluhan & Layanan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(complaintsListProvider),
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          tabs: const [
            Tab(text: 'Semua'),
            Tab(text: 'Aktif'),
            Tab(text: 'Selesai'),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/complaints/new'),
        backgroundColor: Colors.teal,
        icon: const Icon(Icons.chat_bubble_outline_rounded, color: Colors.white),
        label: const Text('Buat Keluhan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      body: complaintsAsync.when(
        data: (complaints) {
          if (complaints.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.support_agent_rounded, size: 64, color: Colors.grey.shade400),
                  const SizedBox(height: 16),
                  const Text('Belum Ada Keluhan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  const Text('Keluhan terkait fasilitas, tagihan, atau layanan dapat diajukan di sini.', style: TextStyle(color: AppColors.textSecondary)),
                ],
              ),
            );
          }

          final activeList = complaints.where((c) => !['closed', 'rejected'].contains(c.status)).toList();
          final finishedList = complaints.where((c) => ['closed', 'rejected'].contains(c.status)).toList();

          return TabBarView(
            controller: _tabController,
            children: [
              _buildListView(complaints),
              _buildListView(activeList),
              _buildListView(finishedList),
            ],
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(child: Text('Gagal memuat keluhan: $err')),
      ),
    );
  }

  Widget _buildListView(List<ComplaintModel> list) {
    if (list.isEmpty) {
      return const Center(child: Text('Tidak ada keluhan pada tab ini.', style: TextStyle(color: AppColors.textSecondary)));
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
            onTap: () => context.push('/complaint/${item.id}', extra: item),
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
                  Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
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
                      Text(item.categoryLabel, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w600)),
                      if (item.rating != null)
                        Row(
                          children: [
                            const Icon(Icons.star_rounded, size: 16, color: Colors.amber),
                            const SizedBox(width: 2),
                            Text('${item.rating}/5', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          ],
                        )
                      else if (item.comments.isNotEmpty)
                        Text('${item.comments.length} Tanggapan', style: const TextStyle(fontSize: 11, color: Colors.teal, fontWeight: FontWeight.bold)),
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
      case 'submitted':
        bg = Colors.blue.shade50;
        fg = Colors.blue.shade700;
        break;
      case 'acknowledged':
        bg = Colors.cyan.shade50;
        fg = Colors.cyan.shade800;
        break;
      case 'in_progress':
        bg = Colors.orange.shade50;
        fg = Colors.orange.shade700;
        break;
      case 'resolved':
        bg = Colors.teal.shade50;
        fg = Colors.teal.shade700;
        break;
      case 'closed':
        bg = Colors.green.shade50;
        fg = Colors.green.shade700;
        break;
      case 'reopened':
        bg = Colors.purple.shade50;
        fg = Colors.purple.shade700;
        break;
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
