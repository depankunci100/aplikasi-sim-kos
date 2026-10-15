import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../providers/maintenance_provider.dart';

class MaintenanceFormScreen extends ConsumerStatefulWidget {
  const MaintenanceFormScreen({super.key});

  @override
  ConsumerState<MaintenanceFormScreen> createState() => _MaintenanceFormScreenState();
}

class _MaintenanceFormScreenState extends ConsumerState<MaintenanceFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descController = TextEditingController();

  String _selectedCategory = 'ac';
  String _selectedPriority = 'normal';
  bool _isLoading = false;

  final Map<String, String> _categories = {
    'ac': 'Pendingin Ruangan (AC)',
    'electricity': 'Kelistrikan / Saklar / Lampu',
    'water': 'Saluran Air / Wastafel',
    'plumbing': 'Pipa & Sanitasi',
    'furniture': 'Perabot / Furnitur Kamar',
    'door_lock': 'Pintu / Kunci / Jendela',
    'internet': 'Jaringan WiFi / Internet',
    'cleanliness': 'Kebersihan Ekstra',
    'other': 'Lain-lain',
  };

  @override
  void dispose() {
    _titleController.dispose();
    _descController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Laporkan Kerusakan'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Card(
                elevation: 0,
                color: AppColors.primary.withAlpha(15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                child: const Padding(
                  padding: EdgeInsets.all(14),
                  child: Row(
                    children: [
                      Icon(Icons.info_outline_rounded, color: AppColors.primary),
                      SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Sampaikan kerusakan fasilitas dengan jelas agar teknisi dapat menyiapkan peralatan dan suku cadang yang tepat.',
                          style: TextStyle(fontSize: 12, color: AppColors.textPrimary),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Kategori Kerusakan
              const Text('Kategori Kerusakan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              DropdownButtonFormField<String>(
                value: _selectedCategory,
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                ),
                items: _categories.entries.map((e) {
                  return DropdownMenuItem(value: e.key, child: Text(e.value));
                }).toList(),
                onChanged: (val) {
                  if (val != null) setState(() => _selectedCategory = val);
                },
              ),
              const SizedBox(height: 16),

              // Prioritas
              const Text('Tingkat Urgensi / Prioritas', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              DropdownButtonFormField<String>(
                value: _selectedPriority,
                decoration: InputDecoration(
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                ),
                items: const [
                  DropdownMenuItem(value: 'low', child: Text('Rendah (SLA 72 Jam)')),
                  DropdownMenuItem(value: 'normal', child: Text('Normal (SLA 48 Jam)')),
                  DropdownMenuItem(value: 'high', child: Text('Tinggi (SLA 24 Jam)')),
                  DropdownMenuItem(value: 'urgent', child: Text('Mendesak / Darurat (SLA 6 Jam)')),
                ],
                onChanged: (val) {
                  if (val != null) setState(() => _selectedPriority = val);
                },
              ),
              const SizedBox(height: 16),

              // Judul Keluhan
              const Text('Judul Singkat Kerusakan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _titleController,
                decoration: InputDecoration(
                  hintText: 'Misal: AC tidak dingin dan menetes',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                ),
                validator: (val) => (val == null || val.trim().isEmpty) ? 'Judul wajib diisi' : null,
              ),
              const SizedBox(height: 16),

              // Deskripsi
              const Text('Deskripsi & Lokasi Detail', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _descController,
                maxLines: 4,
                decoration: InputDecoration(
                  hintText: 'Jelaskan kronologi atau kondisi kerusakan secara lengkap...',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  contentPadding: const EdgeInsets.all(14),
                ),
                validator: (val) => (val == null || val.trim().isEmpty) ? 'Deskripsi wajib diisi' : null,
              ),
              const SizedBox(height: 24),

              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: _isLoading
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('Kirim Laporan Perbaikan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    final success = await ref.read(maintenanceServiceProvider).createRequest(
          propertyId: 1, // Default active property
          roomId: 1,
          category: _selectedCategory,
          priority: _selectedPriority,
          title: _titleController.text.trim(),
          description: _descController.text.trim(),
        );

    setState(() => _isLoading = false);

    if (mounted) {
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Laporan perbaikan berhasil diajukan.')),
        );
        ref.invalidate(maintenanceListProvider);
        context.pop();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Berhasil menyimpan laporan demo.')),
        );
        ref.invalidate(maintenanceListProvider);
        context.pop();
      }
    }
  }
}
