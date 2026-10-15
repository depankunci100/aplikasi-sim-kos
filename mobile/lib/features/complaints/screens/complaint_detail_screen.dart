import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../models/complaint_model.dart';
import '../providers/complaint_provider.dart';

class ComplaintDetailScreen extends ConsumerStatefulWidget {
  final int complaintId;
  final ComplaintModel? initialComplaint;

  const ComplaintDetailScreen({
    super.key,
    required this.complaintId,
    this.initialComplaint,
  });

  @override
  ConsumerState<ComplaintDetailScreen> createState() => _ComplaintDetailScreenState();
}

class _ComplaintDetailScreenState extends ConsumerState<ComplaintDetailScreen> {
  final _commentController = TextEditingController();
  bool _isSendingComment = false;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(complaintDetailProvider(widget.complaintId));

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.initialComplaint?.ticketNumber ?? 'Detail Keluhan'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(complaintDetailProvider(widget.complaintId)),
          ),
        ],
      ),
      body: detailAsync.when(
        data: (data) => _buildContent(context, data),
        loading: () => widget.initialComplaint != null
            ? _buildContent(context, widget.initialComplaint!)
            : const Center(child: CircularProgressIndicator()),
        error: (err, _) => widget.initialComplaint != null
            ? _buildContent(context, widget.initialComplaint!)
            : Center(child: Text('Gagal memuat detail: $err')),
      ),
    );
  }

  Widget _buildContent(BuildContext context, ComplaintModel item) {
    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Info Card
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
                        if (item.assigneeName != null) _buildInfoRow('Penanggung Jawab', item.assigneeName!),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Catatan Penyelesaian
                if (item.resolutionNotes != null && item.resolutionNotes!.isNotEmpty) ...[
                  Card(
                    color: Colors.green.shade50,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.green.shade200)),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Row(
                            children: [
                              Icon(Icons.check_circle_outline, color: Colors.green, size: 20),
                              SizedBox(width: 8),
                              Text('Catatan Penyelesaian Petugas', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.green)),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(item.resolutionNotes!, style: const TextStyle(fontSize: 13, color: AppColors.textPrimary)),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Konfirmasi Penyelesaian Oleh Tenant (Jika status = resolved)
                if (item.status == 'resolved') ...[
                  Card(
                    color: Colors.amber.shade50,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.amber.shade200)),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Keluhan Telah Ditangani Petugas', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                          const SizedBox(height: 4),
                          const Text(
                            'Mohon konfirmasi jika keluhan sudah terselesaikan dengan baik, atau buka kembali bila belum tuntas.',
                            style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () => _showReopenDialog(context, item.id),
                                  style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                                  child: const Text('Buka Kembali'),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: ElevatedButton(
                                  onPressed: () => _showConfirmDialog(context, item.id),
                                  style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
                                  child: const Text('Konfirmasi Selesai', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Thread Tanggapan / Komentar
                const Text('Tanggapan & Percakapan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 8),
                if (item.comments.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Center(child: Text('Belum ada tanggapan.', style: TextStyle(color: AppColors.textSecondary))),
                  )
                else
                  ...item.comments.map((c) => _buildCommentBubble(c)),
              ],
            ),
          ),
        ),

        // Input Tanggapan Baru
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [BoxShadow(color: Colors.black.withAlpha(15), blurRadius: 4, offset: const Offset(0, -2))],
          ),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _commentController,
                  decoration: const InputDecoration(
                    hintText: 'Tulis tanggapan atau pertanyaan...',
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                ),
              ),
              IconButton(
                icon: _isSendingComment ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.send_rounded, color: AppColors.primary),
                onPressed: _isSendingComment ? null : () => _sendComment(item.id),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildCommentBubble(ComplaintCommentModel c) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey.shade100,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(c.userName ?? 'Petugas', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              if (c.createdAt != null)
                Text(
                  '${c.createdAt!.hour}:${c.createdAt!.minute.toString().padLeft(2, '0')}',
                  style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                ),
            ],
          ),
          const SizedBox(height: 4),
          Text(c.comment, style: const TextStyle(fontSize: 13)),
        ],
      ),
    );
  }

  Future<void> _sendComment(int complaintId) async {
    final text = _commentController.text.trim();
    if (text.isEmpty) return;

    setState(() => _isSendingComment = true);
    final ok = await ref.read(complaintServiceProvider).addComment(
          complaintId: complaintId,
          comment: text,
        );
    setState(() => _isSendingComment = false);

    if (mounted) {
      if (ok) {
        _commentController.clear();
        ref.invalidate(complaintDetailProvider(complaintId));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Tanggapan berhasil disimpan.')));
        _commentController.clear();
      }
    }
  }

  void _showConfirmDialog(BuildContext context, int complaintId) {
    int rating = 5;
    final feedbackController = TextEditingController();
    final messenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlgState) => AlertDialog(
          title: const Text('Konfirmasi Selesai & Beri Nilai'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('Berapa rating kepuasan Anda terhadap penanganan keluhan ini?'),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (index) {
                  final star = index + 1;
                  return IconButton(
                    icon: Icon(star <= rating ? Icons.star_rounded : Icons.star_border_rounded, color: Colors.amber, size: 32),
                    onPressed: () => setDlgState(() => rating = star),
                  );
                }),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: feedbackController,
                decoration: const InputDecoration(labelText: 'Kesan & Masukan (Opsional)', hintText: 'Pelayanan cepat dan ramah'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(ctx);
                final ok = await ref.read(complaintServiceProvider).confirmOrReopen(
                      complaintId: complaintId,
                      action: 'confirm',
                      rating: rating,
                      feedback: feedbackController.text.trim(),
                    );
                if (mounted && ok) {
                  messenger.showSnackBar(const SnackBar(content: Text('Keluhan berhasil ditutup. Terima kasih atas masukan Anda.')));
                  ref.invalidate(complaintDetailProvider(complaintId));
                }
              },
              child: const Text('Simpan'),
            ),
          ],
        ),
      ),
    );
  }

  void _showReopenDialog(BuildContext context, int complaintId) {
    final reasonController = TextEditingController();
    final messenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Buka Kembali Keluhan'),
        content: TextField(
          controller: reasonController,
          decoration: const InputDecoration(labelText: 'Alasan Keluhan Dibuka Kembali *', hintText: 'Contoh: Keran masih menetes'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Batal')),
          ElevatedButton(
            onPressed: () async {
              if (reasonController.text.trim().isEmpty) return;
              Navigator.pop(ctx);
              final ok = await ref.read(complaintServiceProvider).confirmOrReopen(
                    complaintId: complaintId,
                    action: 'reopen',
                    reason: reasonController.text.trim(),
                  );
              if (mounted && ok) {
                messenger.showSnackBar(const SnackBar(content: Text('Keluhan telah dibuka kembali untuk penanganan lebih lanjut.')));
                ref.invalidate(complaintDetailProvider(complaintId));
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Buka Kembali', style: TextStyle(color: Colors.white)),
          ),
        ],
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
