import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../models/inspection_model.dart';
import '../models/inspection_item_model.dart';
import '../providers/inspection_provider.dart';

class InspectionDetailScreen extends ConsumerStatefulWidget {
  final int inspectionId;
  final InspectionModel? initialInspection;

  const InspectionDetailScreen({
    super.key,
    required this.inspectionId,
    this.initialInspection,
  });

  @override
  ConsumerState<InspectionDetailScreen> createState() => _InspectionDetailScreenState();
}

class _InspectionDetailScreenState extends ConsumerState<InspectionDetailScreen> {
  final TextEditingController _objectionController = TextEditingController();
  final TextEditingController _approvalNotesController = TextEditingController();

  @override
  void dispose() {
    _objectionController.dispose();
    _approvalNotesController.dispose();
    super.dispose();
  }

  void _showApproveDialog(InspectionModel inspection) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Setujui Hasil Inspeksi'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Dengan menyetujui, Anda mengonfirmasi bahwa daftar kondisi item dan catatan inspeksi kamar ini telah sesuai.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _approvalNotesController,
              decoration: const InputDecoration(
                labelText: 'Catatan Persetujuan (Opsional)',
                border: OutlineInputBorder(),
                hintText: 'Misal: Kondisi kamar sesuai saat serah terima kunci',
              ),
              maxLines: 2,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            onPressed: () async {
              Navigator.pop(ctx);
              final success = await ref.read(inspectionDecisionProvider.notifier).approve(
                    widget.inspectionId,
                    notes: _approvalNotesController.text.trim(),
                  );
              if (mounted) {
                if (success) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Hasil inspeksi kamar berhasil disetujui!'),
                      backgroundColor: Colors.green,
                    ),
                  );
                } else {
                  final err = ref.read(inspectionDecisionProvider).errorMessage;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(err ?? 'Gagal menyetujui inspeksi'),
                      backgroundColor: Colors.red,
                    ),
                  );
                }
              }
            },
            child: const Text('Setujui Sekarang', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _showObjectDialog(InspectionModel inspection) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Ajukan Keberatan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Sampaikan ketidaksesuaian checklist kondisi atau fasilitas kamar agar ditindaklanjuti oleh pengelola.',
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _objectionController,
              decoration: const InputDecoration(
                labelText: 'Rincian Keberatan *',
                border: OutlineInputBorder(),
                hintText: 'Misal: AC bocor air dan kran wastafel belum diperbaiki...',
              ),
              maxLines: 4,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () async {
              final text = _objectionController.text.trim();
              if (text.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Wajib mengisi rincian alasan keberatan.'),
                    backgroundColor: Colors.red,
                  ),
                );
                return;
              }
              Navigator.pop(ctx);
              final success = await ref.read(inspectionDecisionProvider.notifier).object(
                    widget.inspectionId,
                    objectionNotes: text,
                  );
              if (mounted) {
                if (success) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Keberatan berhasil diajukan dan dicatat di sistem.'),
                      backgroundColor: Colors.orange,
                    ),
                  );
                } else {
                  final err = ref.read(inspectionDecisionProvider).errorMessage;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(err ?? 'Gagal mengajukan keberatan'),
                      backgroundColor: Colors.red,
                    ),
                  );
                }
              }
            },
            child: const Text('Kirim Keberatan', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(inspectionDetailProvider(widget.inspectionId));
    final inspection = detailAsync.value ?? widget.initialInspection;
    final decisionState = ref.watch(inspectionDecisionProvider);

    if (inspection == null && detailAsync.isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (inspection == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Detail Inspeksi')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text('Data inspeksi tidak ditemukan.'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => ref.invalidate(inspectionDetailProvider(widget.inspectionId)),
                child: const Text('Muat Ulang'),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(inspection.inspectionCode),
        actions: [
          if (inspection.baselineInspectionId != null)
            IconButton(
              icon: const Icon(Icons.compare_arrows_rounded),
              tooltip: 'Bandingkan dengan Baseline',
              onPressed: () {
                context.push(
                  '/inspection/${inspection.id}/compare/${inspection.baselineInspectionId}',
                );
              },
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(inspectionDetailProvider(widget.inspectionId));
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Card
              _buildHeaderCard(inspection),
              const SizedBox(height: 16),

              // Tenant Status Alert / Notice
              if (inspection.status == 'approved_by_tenant') ...[
                _buildStatusBanner(
                  icon: Icons.check_circle_rounded,
                  color: Colors.green,
                  title: 'Hasil Inspeksi Telah Disetujui',
                  subtitle: inspection.tenantFeedback ?? 'Penyewa telah menyetujui seluruh checklist kondisi kamar.',
                ),
                const SizedBox(height: 16),
              ] else if (inspection.status == 'objected_by_tenant') ...[
                _buildStatusBanner(
                  icon: Icons.warning_rounded,
                  color: Colors.red,
                  title: 'Keberatan Diajukan oleh Penyewa',
                  subtitle: inspection.tenantFeedback ?? 'Terdapat catatan keberatan yang menunggu tindak lanjut pengelola.',
                ),
                const SizedBox(height: 16),
              ] else if (inspection.canTenantDecide) ...[
                _buildActionBanner(inspection),
                const SizedBox(height: 16),
              ],

              // Inspector Notes
              if (inspection.inspectorNotes != null && inspection.inspectorNotes!.isNotEmpty) ...[
                Card(
                  elevation: 0,
                  color: Colors.blue.shade50,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: Colors.blue.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.notes_rounded, color: Colors.blue.shade800, size: 22),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Catatan Pemeriksa (${inspection.inspectorName ?? 'Petugas'}):',
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 13,
                                  color: Colors.blue.shade900,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                inspection.inspectorNotes!,
                                style: TextStyle(fontSize: 13, color: Colors.blue.shade900),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Checklist Section
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Checklist Kondisi Fasilitas',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withAlpha(20),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      '${inspection.items.length} Item',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppColors.primary),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),

              if (inspection.items.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Center(
                      child: Text('Belum ada item checklist pada inspeksi ini.'),
                    ),
                  ),
                )
              else
                ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: inspection.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final item = inspection.items[index];
                    return _buildItemTile(item);
                  },
                ),

              const SizedBox(height: 24),

              // Photos Gallery Section
              if (inspection.media.isNotEmpty) ...[
                Text(
                  'Foto Bukti Inspeksi (${inspection.media.length})',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                SizedBox(
                  height: 120,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: inspection.media.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 8),
                    itemBuilder: (context, index) {
                      final media = inspection.media[index];
                      return Container(
                        width: 140,
                        decoration: BoxDecoration(
                          color: Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.grey.shade300),
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.photo_library_outlined, size: 36, color: AppColors.primary),
                            const SizedBox(height: 6),
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 6),
                              child: Text(
                                media.caption ?? media.fileName,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w500),
                                maxLines: 2,
                                textAlign: TextAlign.center,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
                const SizedBox(height: 24),
              ],

              // Comparison Shortcut if Baseline exists
              if (inspection.baselineInspectionId != null) ...[
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(double.infinity, 46),
                    side: const BorderSide(color: AppColors.primary),
                  ),
                  icon: const Icon(Icons.compare_arrows_rounded, color: AppColors.primary),
                  label: const Text(
                    'Bandingkan dengan Kondisi Check-in Awal',
                    style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold),
                  ),
                  onPressed: () {
                    context.push(
                      '/inspection/${inspection.id}/compare/${inspection.baselineInspectionId}',
                    );
                  },
                ),
                const SizedBox(height: 24),
              ],

              // Audit Trail History
              if (inspection.logs.isNotEmpty) ...[
                Text(
                  'Riwayat Audit Trail',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Card(
                  elevation: 0,
                  color: Colors.grey.shade50,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: BorderSide(color: Colors.grey.shade200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      children: inspection.logs.map((log) {
                        return Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.history_rounded, size: 16, color: AppColors.textSecondary),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  '${log.description ?? log.action} - ${log.userName ?? 'Sistem'}',
                                  style: const TextStyle(fontSize: 12),
                                ),
                              ),
                            ],
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
      bottomNavigationBar: inspection.canTenantDecide
          ? Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withAlpha(20),
                    blurRadius: 10,
                    offset: const Offset(0, -3),
                  ),
                ],
              ),
              child: decisionState.isLoading
                  ? const Center(
                      heightFactor: 1,
                      child: CircularProgressIndicator(),
                    )
                  : Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Colors.red),
                              padding: const EdgeInsets.symmetric(vertical: 14),
                            ),
                            onPressed: () => _showObjectDialog(inspection),
                            child: const Text(
                              'Ajukan Keberatan',
                              style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.green,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                            ),
                            onPressed: () => _showApproveDialog(inspection),
                            child: const Text(
                              'Setujui Hasil',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ),
                      ],
                    ),
            )
          : null,
    );
  }

  Widget _buildHeaderCard(InspectionModel inspection) {
    return Card(
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
                  inspection.typeLabel,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.primary),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: inspection.statusColor.withAlpha(30),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: inspection.statusColor.withAlpha(80)),
                  ),
                  child: Text(
                    inspection.statusLabel,
                    style: TextStyle(
                      color: inspection.statusColor,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              inspection.propertyName ?? 'Properti',
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
            ),
            Text(
              '${inspection.roomNumber ?? 'Kamar -'} • Petugas: ${inspection.inspectorName ?? '-'}',
              style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const Divider(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Kondisi Keseluruhan', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                    const SizedBox(height: 2),
                    Text(
                      inspection.overallConditionLabel,
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    const Text('Tanggal Inspeksi', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                    const SizedBox(height: 2),
                    Text(
                      inspection.completedAt != null
                          ? '${inspection.completedAt!.day}/${inspection.completedAt!.month}/${inspection.completedAt!.year}'
                          : '-',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildItemTile(InspectionItemModel item) {
    return Card(
      elevation: 0.5,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(
          color: item.repairRequired ? Colors.red.shade200 : Colors.grey.shade200,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Icon(item.conditionIcon, size: 20, color: item.conditionColor),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.itemName,
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                      ),
                      Text(
                        item.category,
                        style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: item.conditionColor.withAlpha(25),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: item.conditionColor.withAlpha(80)),
                  ),
                  child: Text(
                    item.conditionLabel,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: item.conditionColor,
                    ),
                  ),
                ),
              ],
            ),
            if (item.notes != null && item.notes!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.grey.shade50,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  item.notes!,
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade800),
                ),
              ),
            ],
            if (item.repairRequired) ...[
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: const Text(
                      'Perlu Perbaikan / Ganti',
                      style: TextStyle(fontSize: 11, color: Colors.red, fontWeight: FontWeight.bold),
                    ),
                  ),
                  if (item.estimatedCost > 0)
                    Text(
                      'Est: Rp ${item.estimatedCost.toStringAsFixed(0)}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.red),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildStatusBanner({
    required IconData icon,
    required Color color,
    required String title,
    required String subtitle,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withAlpha(20),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withAlpha(80)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: color),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(fontSize: 12, color: color.withAlpha(220)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionBanner(InspectionModel inspection) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.amber.shade50,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.amber.shade300),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.rate_review_rounded, color: Colors.amber.shade900, size: 22),
              const SizedBox(width: 8),
              Text(
                'Konfirmasi Hasil Inspeksi',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Colors.amber.shade900),
              ),
            ],
          ),
          const SizedBox(height: 6),
          const Text(
            'Silakan periksa checklist fasilitas dan foto di atas. Jika kondisi sesuai, klik "Setujui". Jika ada perbedaan atau kerusakan baru yang belum tercatat, klik "Ajukan Keberatan".',
            style: TextStyle(fontSize: 12),
          ),
        ],
      ),
    );
  }
}
