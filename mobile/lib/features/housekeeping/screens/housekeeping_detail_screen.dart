import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/housekeeping_model.dart';
import '../providers/housekeeping_provider.dart';

class HousekeepingDetailScreen extends ConsumerStatefulWidget {
  final int taskId;
  final HousekeepingTaskModel? initialTask;

  const HousekeepingDetailScreen({
    super.key,
    required this.taskId,
    this.initialTask,
  });

  @override
  ConsumerState<HousekeepingDetailScreen> createState() => _HousekeepingDetailScreenState();
}

class _HousekeepingDetailScreenState extends ConsumerState<HousekeepingDetailScreen> {
  final Map<dynamic, bool> _localChecklist = {};
  final _damagedItemsController = TextEditingController();
  final _notesController = TextEditingController();
  bool _isInitialized = false;

  @override
  void dispose() {
    _damagedItemsController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  void _initChecklist(HousekeepingTaskModel item) {
    if (!_isInitialized) {
      for (final c in item.checklist) {
        _localChecklist[c.id] = c.isDone;
      }
      _isInitialized = true;
    }
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(housekeepingDetailProvider(widget.taskId));

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.initialTask?.taskNumber ?? 'Detail Kebersihan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(housekeepingDetailProvider(widget.taskId)),
          ),
        ],
      ),
      body: detailAsync.when(
        data: (data) {
          _initChecklist(data);
          return _buildContent(context, data);
        },
        loading: () {
          if (widget.initialTask != null) {
            _initChecklist(widget.initialTask!);
            return _buildContent(context, widget.initialTask!);
          }
          return const Center(child: CircularProgressIndicator());
        },
        error: (err, _) {
          if (widget.initialTask != null) {
            _initChecklist(widget.initialTask!);
            return _buildContent(context, widget.initialTask!);
          }
          return Center(child: Text('Gagal memuat detail: $err'));
        },
      ),
    );
  }

  Widget _buildContent(BuildContext context, HousekeepingTaskModel item) {
    final authState = ref.watch(authProvider);
    final isTenant = authState.user == null || authState.user!.isTenant;

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
                      Text(item.taskNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontFamily: 'monospace')),
                      _buildStatusBadge(item.status, item.statusLabel),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(item.typeLabel, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                  const SizedBox(height: 12),
                  const Divider(),
                  _buildInfoRow('Lokasi', item.roomNumber != null ? 'Kamar ${item.roomNumber}' : (item.areaName ?? item.propertyName ?? '-')),
                  _buildInfoRow('Jadwal', item.scheduledDate != null ? '${item.scheduledDate!.day}/${item.scheduledDate!.month}/${item.scheduledDate!.year}' : '-'),
                  if (item.scheduledSlot != null) _buildInfoRow('Sesi Waktu', item.scheduledSlot!),
                  if (item.assigneeName != null) _buildInfoRow('Petugas', item.assigneeName!),
                  if (item.notes != null) ...[
                    const SizedBox(height: 8),
                    Text('Catatan: ${item.notes}', style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Temuan Barang Rusak/Hilang (Jika Ada)
          if (item.damagedOrMissingItems != null && item.damagedOrMissingItems!.isNotEmpty) ...[
            Card(
              color: Colors.red.shade50,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.red.shade200)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.report_problem_outlined, color: Colors.red, size: 20),
                        SizedBox(width: 8),
                        Text('Laporan Barang Rusak / Hilang', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red)),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(item.damagedOrMissingItems!, style: const TextStyle(fontSize: 13, color: AppColors.textPrimary)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Checklist Card
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
                      const Text('Checklist Kebersihan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(color: AppColors.primary.withAlpha(20), borderRadius: BorderRadius.circular(6)),
                        child: Text(
                          '${item.checklist.where((c) => _localChecklist[c.id] ?? c.isDone).length}/${item.checklist.length} Selesai',
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.primary),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Divider(height: 1),
                  ...item.checklist.map((c) {
                    final isChecked = _localChecklist[c.id] ?? c.isDone;
                    final isEditable = item.status == 'in_progress';

                    return CheckboxListTile(
                      title: Text(
                        c.task,
                        style: TextStyle(
                          fontSize: 14,
                          decoration: isChecked ? TextDecoration.lineThrough : null,
                          color: isChecked ? AppColors.textSecondary : AppColors.textPrimary,
                        ),
                      ),
                      value: isChecked,
                      onChanged: isEditable
                          ? (val) {
                              setState(() {
                                _localChecklist[c.id] = val ?? false;
                              });
                            }
                          : null,
                      controlAffinity: ListTileControlAffinity.leading,
                      contentPadding: EdgeInsets.zero,
                    );
                  }),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Action Buttons
          if (item.status == 'assigned')
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () async {
                  final messenger = ScaffoldMessenger.of(context);
                  final ok = await ref.read(housekeepingServiceProvider).startTask(item.id);
                  if (mounted && ok) {
                    messenger.showSnackBar(const SnackBar(content: Text('Pembersihan telah dimulai.')));
                    ref.invalidate(housekeepingDetailProvider(item.id));
                  }
                },
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, padding: const EdgeInsets.symmetric(vertical: 12)),
                icon: const Icon(Icons.play_arrow_rounded, color: Colors.white),
                label: const Text('Mulai Pembersihan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),

          if (item.status == 'in_progress')
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _submitVerification(item),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.orange, padding: const EdgeInsets.symmetric(vertical: 12)),
                icon: const Icon(Icons.check_circle_outline, color: Colors.white),
                label: const Text('Serahkan untuk Verifikasi', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),

          if (item.status == 'pending_verification')
            if (isTenant) ...[
              Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFBFDBFE)),
                ),
                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline, color: Color(0xFF2563EB), size: 20),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Staf telah menyelesaikan pembersihan. Mohon periksa kondisi kamar Anda dan konfirmasi apakah sudah sesuai atau memerlukan perbaikan.',
                        style: TextStyle(fontSize: 13, color: Color(0xFF1E40AF), height: 1.4),
                      ),
                    ),
                  ],
                ),
              ),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _showRevisionDialog(context, item),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFFDC2626),
                        side: const BorderSide(color: Color(0xFFDC2626)),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      icon: const Icon(Icons.assignment_late_outlined, size: 18),
                      label: const Text('Minta Perbaikan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _showApproveDialog(context, item),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0D9488),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      icon: const Icon(Icons.check_circle_outline, size: 18),
                      label: const Text('Konfirmasi Selesai', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                  ),
                ],
              ),
            ] else
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _showVerifyDialog(context, item.id),
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.green, padding: const EdgeInsets.symmetric(vertical: 12)),
                  icon: const Icon(Icons.verified_outlined, color: Colors.white),
                  label: const Text('Verifikasi Hasil Kebersihan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ),
        ],
      ),
    );
  }

  Future<void> _submitVerification(HousekeepingTaskModel item) async {
    final messenger = ScaffoldMessenger.of(context);
    final updatedChecklist = item.checklist.map((c) {
      return {
        'id': c.id,
        'task': c.task,
        'is_done': _localChecklist[c.id] ?? c.isDone,
      };
    }).toList();

    final ok = await ref.read(housekeepingServiceProvider).submitVerification(
          id: item.id,
          checklist: updatedChecklist,
          damagedOrMissingItems: _damagedItemsController.text.trim().isNotEmpty ? _damagedItemsController.text.trim() : null,
          notes: _notesController.text.trim().isNotEmpty ? _notesController.text.trim() : null,
        );

    if (mounted && ok) {
      messenger.showSnackBar(const SnackBar(content: Text('Tugas diserahkan untuk verifikasi supervisor.')));
      ref.invalidate(housekeepingDetailProvider(item.id));
    }
  }

  void _showVerifyDialog(BuildContext context, int taskId) {
    final notesController = TextEditingController();
    final messenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Verifikasi Kebersihan'),
        content: TextField(
          controller: notesController,
          decoration: const InputDecoration(labelText: 'Catatan Verifikasi Supervisor *', hintText: 'Kamar bersih dan rapi'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              if (notesController.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              final ok = await ref.read(housekeepingServiceProvider).verifyTask(
                    taskId,
                    notesController.text.trim(),
                  );
              if (mounted && ok) {
                messenger.showSnackBar(const SnackBar(content: Text('Tugas kebersihan berhasil diverifikasi selesai.')));
                ref.invalidate(housekeepingDetailProvider(taskId));
              }
            },
            child: const Text('Verifikasi'),
          ),
        ],
      ),
    );
  }

  void _showApproveDialog(BuildContext context, HousekeepingTaskModel item) {
    final feedbackController = TextEditingController();
    final messenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.check_circle_rounded, color: Color(0xFF0D9488)),
            SizedBox(width: 8),
            Text('Konfirmasi Selesai'),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Apakah Anda sudah puas dengan kebersihan kamar? Tugas akan ditandai selesai.',
              style: TextStyle(fontSize: 13, color: AppColors.textPrimary),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: feedbackController,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Catatan / Masukan (Opsional)',
                hintText: 'Misal: Kamar bersih dan wangi',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0D9488),
              foregroundColor: Colors.white,
            ),
            onPressed: () async {
              Navigator.pop(ctx);
              final ok = await ref.read(housekeepingServiceProvider).approveTask(
                    item.id,
                    feedback: feedbackController.text.trim().isNotEmpty ? feedbackController.text.trim() : null,
                  );
              if (mounted && ok) {
                messenger.showSnackBar(
                  const SnackBar(content: Text('Terima kasih! Kebersihan kamar telah dikonfirmasi selesai.')),
                );
              }
            },
            child: const Text('Konfirmasi'),
          ),
        ],
      ),
    );
  }

  void _showRevisionDialog(BuildContext context, HousekeepingTaskModel item) {
    final reasonController = TextEditingController();
    final selectedItems = <String>{};
    final messenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.assignment_late_outlined, color: Colors.orange),
                SizedBox(width: 8),
                Text('Minta Perbaikan'),
              ],
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Pilih bagian yang perlu dibersihkan ulang:',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 8),
                  if (item.checklist.isNotEmpty)
                    ...item.checklist.map((c) {
                      final isChecked = selectedItems.contains(c.task);
                      return CheckboxListTile(
                        dense: true,
                        title: Text(c.task, style: const TextStyle(fontSize: 13)),
                        value: isChecked,
                        onChanged: (val) {
                          setDialogState(() {
                            if (val == true) {
                              selectedItems.add(c.task);
                            } else {
                              selectedItems.remove(c.task);
                            }
                          });
                        },
                        contentPadding: EdgeInsets.zero,
                        controlAffinity: ListTileControlAffinity.leading,
                      );
                    })
                  else
                    const Text('Tidak ada rincian checklist.', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
                  const SizedBox(height: 12),
                  TextField(
                    controller: reasonController,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Catatan Perbaikan *',
                      hintText: 'Jelaskan apa yang belum sesuai dan perlu dibersihkan kembali...',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFDC2626),
                  foregroundColor: Colors.white,
                ),
                onPressed: () async {
                  final reason = reasonController.text.trim();
                  if (reason.isEmpty) {
                    messenger.showSnackBar(
                      const SnackBar(content: Text('Catatan perbaikan wajib diisi.')),
                    );
                    return;
                  }
                  Navigator.pop(ctx);
                  final ok = await ref.read(housekeepingServiceProvider).requestRevision(
                        item.id,
                        reason: reason,
                        problematicItems: selectedItems.isNotEmpty ? selectedItems.toList() : null,
                      );
                  if (mounted && ok) {
                    messenger.showSnackBar(
                      const SnackBar(
                        content: Text('Permintaan perbaikan kebersihan telah dikirimkan ke petugas & pengelola.'),
                      ),
                    );
                  }
                },
                child: const Text('Kirim'),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
        ],
      ),
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
