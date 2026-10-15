import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/utility_meter_model.dart';
import '../providers/utility_provider.dart';

class RecordMeterScreen extends ConsumerStatefulWidget {
  final UtilityMeterModel meter;

  const RecordMeterScreen({
    super.key,
    required this.meter,
  });

  @override
  ConsumerState<RecordMeterScreen> createState() => _RecordMeterScreenState();
}

class _RecordMeterScreenState extends ConsumerState<RecordMeterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentMeterController = TextEditingController();
  final _notesController = TextEditingController();

  DateTime _readingDate = DateTime.now();
  DateTime? _periodStart;
  DateTime? _periodEnd;
  bool _hasPhotoAttached = true;
  String _selectedStatus = 'pending_review';

  // Sample valid PNG bytes (1x1 pixel) for mobile demonstration and test isolation
  static const List<int> _samplePngBytes = [
    0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, 0x00, 0x00, 0x00, 0x0D,
    0x49, 0x48, 0x44, 0x52, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01,
    0x08, 0x06, 0x00, 0x00, 0x00, 0x1F, 0x15, 0xC4, 0x89, 0x00, 0x00, 0x00,
    0x0A, 0x49, 0x44, 0x41, 0x54, 0x78, 0x9C, 0x63, 0x00, 0x01, 0x00, 0x00,
    0x05, 0x00, 0x01, 0x0D, 0x0A, 0x2D, 0xB4, 0x00, 0x00, 0x00, 0x00, 0x49,
    0x45, 0x4E, 0x44, 0xAE, 0x42, 0x60, 0x82,
  ];

  @override
  void dispose() {
    _currentMeterController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  double? get _parsedCurrentValue => double.tryParse(_currentMeterController.text.trim());
  double get _previousValue => widget.meter.currentReading.toDouble();

  bool get _isBackwardReading {
    final current = _parsedCurrentValue;
    return current != null && current < _previousValue;
  }

  double get _calculatedUsage {
    final current = _parsedCurrentValue;
    if (current == null || current < _previousValue) return 0.0;
    return current - _previousValue;
  }

  Future<void> _submitReading() async {
    if (!_formKey.currentState!.validate()) return;

    if (_isBackwardReading) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Nilai baru (${_parsedCurrentValue!.toStringAsFixed(2)}) tidak boleh lebih kecil dari sebelumnya (${_previousValue.toStringAsFixed(2)})!',
          ),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    final success = await ref.read(utilityNotifierProvider.notifier).recordReading(
      meterId: widget.meter.id,
      currentMeter: _parsedCurrentValue!,
      readingDate: _readingDate,
      periodStart: _periodStart,
      periodEnd: _periodEnd,
      photoBytes: _hasPhotoAttached ? _samplePngBytes : null,
      photoFilename: 'meter_${widget.meter.meterNumber}_proof.png',
      notes: _notesController.text.trim(),
      status: _selectedStatus,
    );

    if (mounted) {
      final actionState = ref.read(utilityNotifierProvider);
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(actionState.successMessage ?? 'Pembacaan meter berhasil dicatat.'),
            backgroundColor: AppColors.success,
          ),
        );
        context.pop();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(actionState.errorMessage ?? 'Gagal menyimpan pembacaan meter.'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final actionState = ref.watch(utilityNotifierProvider);
    final authState = ref.watch(authProvider);
    final isPrivileged = authState.user?.isOwner == true || authState.user?.isManager == true;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Catat Nilai Meter'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Card Info Meter
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.grey.shade200),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          widget.meter.meterNumber,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: widget.meter.isElectricity
                                ? Colors.amber.shade100
                                : Colors.blue.shade100,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            widget.meter.utilityTypeLabel,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: widget.meter.isElectricity
                                  ? Colors.amber.shade900
                                  : Colors.blue.shade900,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${widget.meter.propertyName ?? '-'} • ${widget.meter.roomNumber != null ? 'Kamar ${widget.meter.roomNumber}' : 'Komunal'}',
                      style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                    ),
                    const Divider(height: 20),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Pembacaan Terakhir / Baseline:', style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
                        Text(
                          '${_previousValue.toStringAsFixed(2)} ${widget.meter.unit}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppColors.primary),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Input Nilai Meter Baru
              const Text('Nilai Meter Baru', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _currentMeterController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  hintText: 'Contoh: ${_previousValue + 15.5}',
                  suffixText: widget.meter.unit,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  filled: true,
                  fillColor: Colors.grey.shade50,
                ),
                onChanged: (val) => setState(() {}),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) {
                    return 'Nilai meter wajib diisi';
                  }
                  final parsed = double.tryParse(val.trim());
                  if (parsed == null || parsed < 0) {
                    return 'Masukkan angka desimal yang valid';
                  }
                  if (parsed < _previousValue) {
                    return 'Nilai tidak boleh lebih kecil dari pembacaan sebelumnya';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 10),

              // Live Usage Feedback Card
              if (_parsedCurrentValue != null)
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: _isBackwardReading ? AppColors.danger.withAlpha(20) : AppColors.success.withAlpha(20),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: _isBackwardReading ? AppColors.danger : AppColors.success,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        _isBackwardReading ? Icons.warning_amber_rounded : Icons.check_circle_outline_rounded,
                        color: _isBackwardReading ? AppColors.danger : AppColors.success,
                        size: 22,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _isBackwardReading
                              ? 'Peringatan: Nilai baru lebih kecil dari pembacaan sebelumnya (${_previousValue.toStringAsFixed(2)} ${widget.meter.unit})!'
                              : 'Pemakaian Terhitung: ${_calculatedUsage.toStringAsFixed(2)} ${widget.meter.unit}',
                          style: TextStyle(
                            color: _isBackwardReading ? AppColors.danger : AppColors.success,
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 20),

              // Tanggal Pembacaan
              const Text('Tanggal Pembacaan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              InkWell(
                onTap: () async {
                  final picked = await showDatePicker(
                    context: context,
                    initialDate: _readingDate,
                    firstDate: DateTime(2025),
                    lastDate: DateTime.now().add(const Duration(days: 1)),
                  );
                  if (picked != null) {
                    setState(() => _readingDate = picked);
                  }
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey.shade400),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(Formatters.toShortDate(_readingDate)),
                      const Icon(Icons.calendar_today_rounded, size: 18, color: AppColors.textSecondary),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Lampiran Foto Bukti Meter
              const Text('Foto Bukti Display Meter (Privat)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.grey.shade300),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.camera_alt_rounded, color: AppColors.primary, size: 28),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _hasPhotoAttached ? 'Foto Display Terlampir (meter_proof.png)' : 'Belum Ada Foto',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                          const Text(
                            'Disimpan aman di server privat untuk verifikasi kasir/penyewa.',
                            style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: Icon(_hasPhotoAttached ? Icons.check_circle : Icons.add_a_photo, color: AppColors.primary),
                      onPressed: () {
                        setState(() => _hasPhotoAttached = !_hasPhotoAttached);
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Pilihan Status Review (Jika Manajer / Owner)
              if (isPrivileged) ...[
                const Text('Status Pembacaan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                const SizedBox(height: 6),
                DropdownButtonFormField<String>(
                  value: _selectedStatus,
                  decoration: InputDecoration(
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'pending_review', child: Text('Menunggu Review Kasir/Manajer')),
                    DropdownMenuItem(value: 'approved', child: Text('Langsung Disetujui (Disetujui)')),
                  ],
                  onChanged: (val) => setState(() => _selectedStatus = val ?? 'pending_review'),
                ),
                const SizedBox(height: 20),
              ],

              // Catatan Tambahan
              const Text('Catatan Pemeriksaan', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 6),
              TextFormField(
                controller: _notesController,
                maxLines: 2,
                decoration: InputDecoration(
                  hintText: 'Kondisi fisik meteran, segel aman, dll...',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                ),
              ),
              const SizedBox(height: 30),

              // Tombol Submit
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: actionState.isLoading || _isBackwardReading ? null : _submitReading,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: actionState.isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text(
                          'Simpan Pembacaan Meter',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
