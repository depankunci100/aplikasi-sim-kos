import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../models/inspection_model.dart';
import '../providers/inspection_provider.dart';

class InspectionsScreen extends ConsumerStatefulWidget {
  const InspectionsScreen({super.key});

  @override
  ConsumerState<InspectionsScreen> createState() => _InspectionsScreenState();
}

class _InspectionsScreenState extends ConsumerState<InspectionsScreen> with SingleTickerProviderStateMixin {
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
    final inspectionsAsync = ref.watch(inspectionsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inspeksi & Checklist Kamar'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primary,
          tabs: const [
            Tab(text: 'Semua'),
            Tab(text: 'Perlu Konfirmasi'),
            Tab(text: 'Selesai / Riwayat'),
          ],
        ),
      ),
      body: inspectionsAsync.when(
        data: (inspections) {
          if (inspections.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.fact_check_outlined, size: 64, color: Colors.grey.shade400),
                  const SizedBox(height: 16),
                  const Text(
                    'Belum Ada Laporan Inspeksi',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Riwayat checklist kondisi kamar Anda akan tampil di sini.',
                    style: TextStyle(color: AppColors.textSecondary),
                  ),
                ],
              ),
            );
          }

          final pendingAction = inspections.where((i) => i.status == 'completed').toList();
          final history = inspections
              .where((i) => i.status == 'approved_by_tenant' || i.status == 'objected_by_tenant' || i.status == 'cancelled')
              .toList();

          return TabBarView(
            controller: _tabController,
            children: [
              _buildList(context, inspections),
              _buildList(context, pendingAction, emptyMsg: 'Tidak ada inspeksi yang menunggu persetujuan Anda.'),
              _buildList(context, history, emptyMsg: 'Belum ada riwayat inspeksi selesai.'),
            ],
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              Text('Gagal memuat data inspeksi: $err'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => ref.invalidate(inspectionsProvider),
                child: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildList(BuildContext context, List<InspectionModel> list, {String? emptyMsg}) {
    if (list.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Text(
            emptyMsg ?? 'Tidak ada data inspeksi.',
            style: const TextStyle(color: AppColors.textSecondary),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(inspectionsProvider);
      },
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: list.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final item = list[index];
          return _buildInspectionCard(context, item);
        },
      ),
    );
  }

  Widget _buildInspectionCard(BuildContext context, InspectionModel item) {
    return Card(
      elevation: 1.5,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: item.canTenantDecide ? AppColors.primary.withAlpha(120) : Colors.grey.shade200,
          width: item.canTenantDecide ? 1.5 : 1,
        ),
      ),
      child: InkWell(
        onTap: () {
          context.push('/inspection/${item.id}', extra: item);
        },
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header row
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withAlpha(25),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      item.typeLabel,
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: item.statusColor.withAlpha(30),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: item.statusColor.withAlpha(100)),
                    ),
                    child: Text(
                      item.statusLabel,
                      style: TextStyle(
                        color: item.statusColor,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                item.propertyName ?? 'Properti SIM Kos',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  const Icon(Icons.meeting_room_outlined, size: 16, color: AppColors.textSecondary),
                  const SizedBox(width: 4),
                  Text(
                    item.roomNumber ?? 'Kamar -',
                    style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                  ),
                  const SizedBox(width: 16),
                  const Icon(Icons.tag_rounded, size: 16, color: AppColors.textSecondary),
                  const SizedBox(width: 4),
                  Text(
                    item.inspectionCode,
                    style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                  ),
                ],
              ),
              const Divider(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.shield_outlined, size: 16, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Text(
                        'Kondisi: ${item.overallConditionLabel}',
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
                      ),
                    ],
                  ),
                  const Row(
                    children: [
                      Text(
                        'Lihat Rincian',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.primary,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      SizedBox(width: 2),
                      Icon(Icons.chevron_right_rounded, size: 18, color: AppColors.primary),
                    ],
                  ),
                ],
              ),
              if (item.canTenantDecide) ...[
                const SizedBox(height: 10),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.amber.shade50,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.amber.shade300),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.notification_important_rounded, size: 16, color: Colors.amber.shade900),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          'Menunggu konfirmasi persetujuan kondisi dari Anda.',
                          style: TextStyle(fontSize: 12, color: Colors.amber.shade900, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
