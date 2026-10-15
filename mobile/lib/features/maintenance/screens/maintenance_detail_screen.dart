import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../models/maintenance_model.dart';
import '../providers/maintenance_provider.dart';

class MaintenanceDetailScreen extends ConsumerStatefulWidget {
  final int maintenanceId;
  final MaintenanceRequestModel? initialMaintenance;

  const MaintenanceDetailScreen({
    super.key,
    required this.maintenanceId,
    this.initialMaintenance,
  });

  @override
  ConsumerState<MaintenanceDetailScreen> createState() => _MaintenanceDetailScreenState();
}

class _MaintenanceDetailScreenState extends ConsumerState<MaintenanceDetailScreen> {
  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(maintenanceDetailProvider(widget.maintenanceId));

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.initialMaintenance?.ticketNumber ?? 'Detail Perbaikan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(maintenanceDetailProvider(widget.maintenanceId)),
          ),
        ],
      ),
      body: detailAsync.when(
        data: (data) => _buildBody(context, data),
        loading: () => widget.initialMaintenance != null
            ? _buildBody(context, widget.initialMaintenance!)
            : const Center(child: CircularProgressIndicator()),
        error: (err, _) => widget.initialMaintenance != null
            ? _buildBody(context, widget.initialMaintenance!)
            : Center(child: Text('Gagal memuat detail: $err')),
      ),
    );
  }

  Widget _buildBody(BuildContext context, MaintenanceRequestModel item) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
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
                      Text(item.ticketNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontFamily: 'monospace')),
                      _buildStatusBadge(item.status, item.statusLabel),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                  const SizedBox(height: 6),
                  Text(item.description, style: const TextStyle(color: AppColors.textSecondary, fontSize: 14)),
                  const SizedBox(height: 12),
                  const Divider(),
                  _buildInfoRow('Kategori', item.categoryLabel),
                  _buildInfoRow('Prioritas', item.priority.toUpperCase()),
                  _buildInfoRow('Lokasi', item.roomNumber != null ? 'Kamar ${item.roomNumber}' : (item.propertyName ?? '-')),
                  if (item.assigneeName != null) _buildInfoRow('Teknisi', item.assigneeName!),
                  if (item.slaDueAt != null) _buildInfoRow('Target SLA', '${item.slaHours} Jam (${item.isOverdue ? 'Terlambat' : 'Tepat Waktu'})'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Biaya Perbaikan (Jika Ada)
          if (item.totalCost > 0) ...[
            Card(
              elevation: 1,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Rincian Biaya Perbaikan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    const SizedBox(height: 8),
                    _buildInfoRow('Biaya Tenaga Kerja', 'Rp ${item.laborCost.toStringAsFixed(0)}'),
                    _buildInfoRow('Biaya Material/Suku Cadang', 'Rp ${item.materialCost.toStringAsFixed(0)}'),
                    const Divider(),
                    _buildInfoRow('Total Biaya', 'Rp ${item.totalCost.toStringAsFixed(0)}', isBold: true),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Catatan Tindakan (Jika Ada)
          if (item.actionTaken != null && item.actionTaken!.isNotEmpty) ...[
            Card(
              elevation: 1,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Tindakan Perbaikan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    const SizedBox(height: 6),
                    Text(item.actionTaken!, style: const TextStyle(fontSize: 13, color: AppColors.textPrimary)),
                    if (item.diagnosisNotes != null) ...[
                      const SizedBox(height: 8),
                      Text('Diagnosis: ${item.diagnosisNotes}', style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Action Buttons
          if (item.status == 'assigned')
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () async {
                  final messenger = ScaffoldMessenger.of(context);
                  final success = await ref.read(maintenanceServiceProvider).startWork(item.id);
                  if (mounted && success) {
                    messenger.showSnackBar(const SnackBar(content: Text('Pekerjaan perbaikan telah dimulai.')));
                    ref.invalidate(maintenanceDetailProvider(item.id));
                  }
                },
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, padding: const EdgeInsets.symmetric(vertical: 12)),
                icon: const Icon(Icons.play_arrow_rounded, color: Colors.white),
                label: const Text('Mulai Pengerjaan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),

          if (item.status == 'in_progress')
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _showSubmitVerificationDialog(context, item),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.orange, padding: const EdgeInsets.symmetric(vertical: 12)),
                icon: const Icon(Icons.check_circle_outline, color: Colors.white),
                label: const Text('Serahkan untuk Verifikasi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),

          if (item.status == 'pending_verification')
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _showVerifyDialog(context, item),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.green, padding: const EdgeInsets.symmetric(vertical: 12)),
                icon: const Icon(Icons.verified_outlined, color: Colors.white),
                label: const Text('Verifikasi & Selesaikan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),
        ],
      ),
    );
  }

  void _showSubmitVerificationDialog(BuildContext context, MaintenanceRequestModel item) {
    final actionController = TextEditingController();
    final laborController = TextEditingController();
    final materialController = TextEditingController();
    final messenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Serahkan Hasil Perbaikan'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: actionController,
                decoration: const InputDecoration(labelText: 'Tindakan yang Dilakukan *', hintText: 'Ganti saklar / perbaiki pipa'),
              ),
              TextField(
                controller: laborController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Biaya Tenaga Kerja (Rp)'),
              ),
              TextField(
                controller: materialController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Biaya Material / Suku Cadang (Rp)'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              if (actionController.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              final ok = await ref.read(maintenanceServiceProvider).submitVerification(
                    id: item.id,
                    actionTaken: actionController.text.trim(),
                    laborCost: double.tryParse(laborController.text),
                    materialCost: double.tryParse(materialController.text),
                  );
              if (mounted && ok) {
                messenger.showSnackBar(const SnackBar(content: Text('Hasil perbaikan berhasil diserahkan.')));
                ref.invalidate(maintenanceDetailProvider(item.id));
              }
            },
            child: const Text('Kirim'),
          ),
        ],
      ),
    );
  }

  void _showVerifyDialog(BuildContext context, MaintenanceRequestModel item) {
    final notesController = TextEditingController();
    final messenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Verifikasi Perbaikan'),
        content: TextField(
          controller: notesController,
          decoration: const InputDecoration(labelText: 'Catatan Verifikasi Supervisor *', hintText: 'Pekerjaan rapi dan sesuai SOP'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              if (notesController.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              final ok = await ref.read(maintenanceServiceProvider).verifyAndComplete(
                    item.id,
                    notesController.text.trim(),
                  );
              if (mounted && ok) {
                messenger.showSnackBar(const SnackBar(content: Text('Perbaikan berhasil diverifikasi selesai.')));
                ref.invalidate(maintenanceDetailProvider(item.id));
              }
            },
            child: const Text('Verifikasi'),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value, {bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
          Text(value, style: TextStyle(fontWeight: isBold ? FontWeight.bold : FontWeight.w600, fontSize: 13)),
        ],
      ),
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
