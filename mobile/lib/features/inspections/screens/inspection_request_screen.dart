import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';

class InspectionRequestScreen extends ConsumerStatefulWidget {
  const InspectionRequestScreen({super.key});

  @override
  ConsumerState<InspectionRequestScreen> createState() => _InspectionRequestScreenState();
}

class _InspectionRequestScreenState extends ConsumerState<InspectionRequestScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isLoadingRoom = true;
  bool _isSubmitting = false;
  Map<String, dynamic>? _activeRoom;
  String? _errorMessage;

  String _inspectionType = 'periodic';
  String _selectedReason = 'Pemeriksaan Rutin Kondisi Kamar';
  final _descriptionController = TextEditingController();
  DateTime _preferredDate = DateTime.now().add(const Duration(days: 2));

  final List<Map<String, String>> _reasons = [
    {
      'label': 'Pemeriksaan Rutin Kondisi Kamar',
      'type': 'periodic',
      'desc': 'Pemeriksaan berkala aset & kebersihan fasilitas'
    },
    {
      'label': 'Kerusakan Fasilitas / Perabot',
      'type': 'maintenance',
      'desc': 'Pemeriksaan bersama kendala fasilitas yang membutuhkan perbaikan'
    },
    {
      'label': 'Persiapan Check-Out / Pengakhiran Sewa',
      'type': 'check_out',
      'desc': 'Pemeriksaan awal aset sebelum serah terima check-out'
    },
  ];

  @override
  void initState() {
    super.initState();
    _fetchActiveRoom();
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _fetchActiveRoom() async {
    setState(() {
      _isLoadingRoom = true;
      _errorMessage = null;
    });

    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.get('/tenant/active-room');

      if (response.statusCode == 200 && response.data['success'] == true) {
        setState(() {
          _activeRoom = response.data['data'] as Map<String, dynamic>?;
          _isLoadingRoom = false;
        });
      } else {
        setState(() {
          _errorMessage = response.data['message'] ?? 'Tidak ditemukan kamar aktif.';
          _isLoadingRoom = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Gagal memuat kamar aktif: $e';
        _isLoadingRoom = false;
      });
    }
  }

  Future<void> _submitRequest() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.post('/inspections/request', data: {
        'type': _inspectionType,
        'reason': _selectedReason,
        'description': _descriptionController.text.trim(),
        'scheduled_date': DateFormat('yyyy-MM-dd').format(_preferredDate),
      });

      if (response.statusCode == 201 && response.data['success'] == true) {
        if (!mounted) return;
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (ctx) => AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.check_circle_rounded, color: Colors.green),
                SizedBox(width: 8),
                Text('Permohonan Terkirim'),
              ],
            ),
            content: const Text(
              'Permohonan inspeksi kamar Anda telah berhasil diajukan. Petugas pengelola kos akan meninjau jadwal dan mengonfirmasi waktu pemeriksaan.',
            ),
            actions: [
              ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, foregroundColor: Colors.white),
                onPressed: () {
                  Navigator.of(ctx).pop();
                  context.pushReplacement('/inspections');
                },
                child: const Text('Lihat Riwayat Inspeksi'),
              ),
            ],
          ),
        );
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(response.data['message'] ?? 'Gagal mengajukan inspeksi.')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Terjadi kendala: $e')),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Ajukan Inspeksi Kamar'),
      ),
      body: _isLoadingRoom
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline_rounded, size: 64, color: AppColors.error),
                        const SizedBox(height: 16),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 14, color: AppColors.textSecondary),
                        ),
                        const SizedBox(height: 20),
                        ElevatedButton(onPressed: _fetchActiveRoom, child: const Text('Coba Lagi')),
                      ],
                    ),
                  ),
                )
              : _activeRoom == null
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(28.0),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(20),
                              decoration: BoxDecoration(
                                color: AppColors.primary.withAlpha(25),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.fact_check_outlined, size: 56, color: AppColors.primary),
                            ),
                            const SizedBox(height: 20),
                            const Text(
                              'Belum Ada Kamar Aktif',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 10),
                            const Text(
                              'Akun Anda belum memiliki sewa kamar aktif yang terhubung. Pengajuan inspeksi kamar hanya dapat dilakukan oleh penyewa yang sedang aktif menghuni kamar.',
                              style: TextStyle(fontSize: 14, color: AppColors.textSecondary, height: 1.5),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 24),
                            ElevatedButton.icon(
                              onPressed: () => context.pop(),
                              icon: const Icon(Icons.arrow_back),
                              label: const Text('Kembali'),
                            ),
                          ],
                        ),
                      ),
                    )
                  : SingleChildScrollView(
                  padding: const EdgeInsets.all(16.0),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Card Info Kamar Aktif
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF1E293B), Color(0xFF0F766E)],
                            ),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.white.withAlpha(25),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.room_preferences_rounded, color: Colors.white, size: 28),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _activeRoom?['property_name'] ?? 'Properti Kos',
                                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      'Kamar ${_activeRoom?['room_number'] ?? "-"} (${_activeRoom?['room_type'] ?? "Standard"})',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 16,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      'Penghuni Resmi: ${ref.watch(authProvider).user?.name ?? "-"}',
                                      style: const TextStyle(color: Colors.white60, fontSize: 11),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        const Text('Alasan Permohonan Inspeksi', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 10),
                        ..._reasons.map((r) {
                          final isSelected = _selectedReason == r['label'];
                          return Card(
                            elevation: 0,
                            margin: const EdgeInsets.only(bottom: 8),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                              side: BorderSide(
                                color: isSelected ? AppColors.primary : AppColors.border,
                                width: isSelected ? 1.5 : 1,
                              ),
                            ),
                            color: isSelected ? AppColors.primary.withAlpha(10) : Colors.white,
                            child: ListTile(
                              leading: Icon(
                                isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
                                color: isSelected ? AppColors.primary : AppColors.textMuted,
                              ),
                              title: Text(r['label']!, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                              subtitle: Text(r['desc']!, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                              onTap: () {
                                setState(() {
                                  _selectedReason = r['label']!;
                                  _inspectionType = r['type']!;
                                });
                              },
                            ),
                          );
                        }),
                        const SizedBox(height: 16),

                        // Date Picker
                        Card(
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                            side: const BorderSide(color: AppColors.border),
                          ),
                          child: ListTile(
                            leading: const Icon(Icons.event_available_rounded, color: AppColors.primary),
                            title: const Text('Perkiraan Tanggal Pemeriksaan', style: TextStyle(fontSize: 13)),
                            subtitle: Text(
                              DateFormat('EEEE, dd MMMM yyyy', 'id').format(_preferredDate),
                              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                            ),
                            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 14),
                            onTap: () async {
                              final picked = await showDatePicker(
                                context: context,
                                initialDate: _preferredDate,
                                firstDate: DateTime.now(),
                                lastDate: DateTime.now().add(const Duration(days: 30)),
                              );
                              if (picked != null) {
                                setState(() => _preferredDate = picked);
                              }
                            },
                          ),
                        ),
                        const SizedBox(height: 16),

                        const Text('Keterangan / Detail Kondisi Kamar', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: _descriptionController,
                          maxLines: 4,
                          decoration: InputDecoration(
                            hintText: 'Jelaskan kondisi atau fasilitas kamar yang ingin diperiksa bersama petugas.',
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          validator: (v) {
                            if (v == null || v.trim().isEmpty) {
                              return 'Mohon tuliskan deskripsi permohonan inspeksi.';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 24),

                        SizedBox(
                          width: double.infinity,
                          height: 48,
                          child: ElevatedButton.icon(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            icon: _isSubmitting
                                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                                : const Icon(Icons.send_rounded),
                            label: Text(
                              _isSubmitting ? 'Mengirimkan...' : 'Ajukan Permohonan Inspeksi',
                              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                            ),
                            onPressed: _isSubmitting ? null : _submitRequest,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
    );
  }
}
