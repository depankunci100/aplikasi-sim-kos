import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';

class HousekeepingRequestScreen extends ConsumerStatefulWidget {
  const HousekeepingRequestScreen({super.key});

  @override
  ConsumerState<HousekeepingRequestScreen> createState() => _HousekeepingRequestScreenState();
}

class _HousekeepingRequestScreenState extends ConsumerState<HousekeepingRequestScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isLoadingRoom = true;
  bool _isSubmitting = false;
  Map<String, dynamic>? _activeRoom;
  String? _errorMessage;

  DateTime _selectedDate = DateTime.now().add(const Duration(days: 1));
  String _selectedSlot = 'Pagi (08:00 - 11:00)';
  final String _priority = 'normal';
  final _notesController = TextEditingController();

  final List<String> _slots = [
    'Pagi (08:00 - 11:00)',
    'Siang (13:00 - 15:00)',
    'Sore (15:00 - 17:00)',
  ];

  @override
  void initState() {
    super.initState();
    _fetchActiveRoom();
  }

  @override
  void dispose() {
    _notesController.dispose();
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
        _errorMessage = 'Gagal memuat informasi kamar aktif: $e';
        _isLoadingRoom = false;
      });
    }
  }

  Future<void> _submitRequest() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.post('/housekeeping/request', data: {
        'scheduled_date': DateFormat('yyyy-MM-dd').format(_selectedDate),
        'scheduled_slot': _selectedSlot,
        'priority': _priority,
        'notes': _notesController.text.trim(),
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
                Text('Pengajuan Terkirim'),
              ],
            ),
            content: const Text(
              'Permohonan pembersihan kamar berhasil dikirimkan ke tim staf kos. Anda dapat memantau proses tindak lanjut pada riwayat kebersihan.',
            ),
            actions: [
              ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, foregroundColor: Colors.white),
                onPressed: () {
                  Navigator.of(ctx).pop();
                  context.pushReplacement('/housekeeping');
                },
                child: const Text('Lihat Riwayat'),
              ),
            ],
          ),
        );
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(response.data['message'] ?? 'Gagal mengajukan pembersihan.')),
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
        title: const Text('Ajukan Pembersihan Kamar'),
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
                        ElevatedButton(
                          onPressed: _fetchActiveRoom,
                          child: const Text('Coba Lagi'),
                        ),
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
                              child: const Icon(Icons.meeting_room_outlined, size: 56, color: AppColors.primary),
                            ),
                            const SizedBox(height: 20),
                            const Text(
                              'Belum Ada Kamar Aktif',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 10),
                            const Text(
                              'Akun Anda belum memiliki sewa kamar aktif yang terhubung. Pengajuan pembersihan kamar hanya dapat dilakukan oleh penyewa yang sedang aktif menghuni kamar.',
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
                              colors: [Color(0xFF0F766E), Color(0xFF134E4A)],
                            ),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.white.withAlpha(30),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.bed_rounded, color: Colors.white, size: 28),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _activeRoom?['property_name'] ?? 'SIM Kos & Guesthouse',
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
                                      'Kode Booking: ${_activeRoom?['booking_code'] ?? "-"}',
                                      style: const TextStyle(color: Colors.white60, fontSize: 11),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        const Text(
                          'Jadwal Pembersihan',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                        ),
                        const SizedBox(height: 12),

                        // Date Picker
                        Card(
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                            side: const BorderSide(color: AppColors.border),
                          ),
                          child: ListTile(
                            leading: const Icon(Icons.calendar_today_rounded, color: AppColors.primary),
                            title: const Text('Tanggal Pelaksanaan', style: TextStyle(fontSize: 13)),
                            subtitle: Text(
                              DateFormat('EEEE, dd MMMM yyyy', 'id').format(_selectedDate),
                              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                            ),
                            trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 14),
                            onTap: () async {
                              final picked = await showDatePicker(
                                context: context,
                                initialDate: _selectedDate,
                                firstDate: DateTime.now(),
                                lastDate: DateTime.now().add(const Duration(days: 30)),
                              );
                              if (picked != null) {
                                setState(() => _selectedDate = picked);
                              }
                            },
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Slot Waktu
                        const Text('Pilihan Waktu (Slot)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          children: _slots.map((slot) {
                            final isSelected = _selectedSlot == slot;
                            return ChoiceChip(
                              label: Text(slot),
                              selected: isSelected,
                              selectedColor: AppColors.primary,
                              labelStyle: TextStyle(
                                color: isSelected ? Colors.white : AppColors.textPrimary,
                                fontSize: 12,
                                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                              ),
                              onSelected: (val) {
                                if (val) setState(() => _selectedSlot = slot);
                              },
                            );
                          }).toList(),
                        ),
                        const SizedBox(height: 20),

                        // Catatan / Permintaan Khusus
                        const Text('Catatan Tambahan (Opsional)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 8),
                        TextFormField(
                          controller: _notesController,
                          maxLines: 4,
                          decoration: InputDecoration(
                            hintText: 'Misal: Mohon ganti sarung bantal tambahan, bersihkan kaca jendela, atau kosongkan tempat sampah.',
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Tombol Submit
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
                              _isSubmitting ? 'Mengirimkan...' : 'Kirim Pengajuan Pembersihan',
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
