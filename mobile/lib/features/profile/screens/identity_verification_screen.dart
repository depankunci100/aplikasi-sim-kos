import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';
import '../services/ktp_ocr_service.dart';

class IdentityVerificationScreen extends ConsumerStatefulWidget {
  const IdentityVerificationScreen({super.key});

  @override
  ConsumerState<IdentityVerificationScreen> createState() => _IdentityVerificationScreenState();
}

class _IdentityVerificationScreenState extends ConsumerState<IdentityVerificationScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isLoading = true;
  bool _isScanning = false;
  bool _isSubmitting = false;

  Map<String, dynamic>? _existingIdentity;

  final _nameController = TextEditingController();
  final _nikController = TextEditingController();
  final _emergencyNameController = TextEditingController();
  final _emergencyPhoneController = TextEditingController();
  final _occupationController = TextEditingController();
  final _institutionController = TextEditingController();
  final _plateController = TextEditingController();
  String _vehicleType = 'none';

  @override
  void initState() {
    super.initState();
    _fetchExistingIdentity();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _nikController.dispose();
    _emergencyNameController.dispose();
    _emergencyPhoneController.dispose();
    _occupationController.dispose();
    _institutionController.dispose();
    _plateController.dispose();
    super.dispose();
  }

  Future<void> _fetchExistingIdentity() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.get('/tenant/identity');

      if (response.statusCode == 200 && response.data['success'] == true) {
        final data = response.data['data'] as Map<String, dynamic>?;
        if (mounted) {
          setState(() {
            _existingIdentity = data;
            if (data != null) {
              _nameController.text = data['name'] ?? '';
              _emergencyNameController.text = data['emergency_name'] ?? '';
              _emergencyPhoneController.text = data['emergency_phone'] ?? '';
              _occupationController.text = data['occupation'] ?? '';
              _institutionController.text = data['institution_name'] ?? '';
              _plateController.text = data['vehicle_plate'] ?? '';
              _vehicleType = data['vehicle_type'] ?? 'none';
            }
            _isLoading = false;
          });
        }
      } else {
        if (mounted) setState(() => _isLoading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _triggerOcrScan() async {
    setState(() => _isScanning = true);

    try {
      final result = await KtpOcrService.simulateScanKtp();

      if (mounted) {
        showModalBottomSheet(
          context: context,
          isScrollControlled: true,
          shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
          builder: (ctx) => Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(color: Colors.green.withAlpha(25), shape: BoxShape.circle),
                      child: const Icon(Icons.check_circle_rounded, color: Colors.green, size: 24),
                    ),
                    const SizedBox(width: 10),
                    const Expanded(
                      child: Text('Hasil Pembacaan OCR (Pratinjau)', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                const Text(
                  'Pratinjau data hasil pemindaian KTP on-device. Anda dapat mengoreksi data sebelum disalin ke formulir. Tidak ada data yang tersimpan sebelum Anda menekan tombol simpan.',
                  style: TextStyle(fontSize: 12, color: AppColors.textSecondary, height: 1.4),
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceMuted,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          const SizedBox(width: 90, child: Text('NIK Terbaca:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold))),
                          Expanded(
                            child: Text(
                              result.nik ?? 'Gagal terbaca',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                                color: result.nik != null ? AppColors.primary : Colors.red,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          const SizedBox(width: 90, child: Text('Nama:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold))),
                          Expanded(child: Text(result.name ?? 'Gagal terbaca', style: const TextStyle(fontSize: 13))),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          const SizedBox(width: 90, child: Text('Alamat/Kota:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold))),
                          Expanded(child: Text(result.birthPlaceDate ?? result.address ?? '-', style: const TextStyle(fontSize: 12))),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.of(ctx).pop(),
                        child: const Text('Batal'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, foregroundColor: Colors.white),
                        onPressed: () {
                          if (result.nik != null) {
                            _nikController.text = result.nik!;
                          }
                          if (result.name != null) {
                            _nameController.text = result.name!;
                          }
                          Navigator.of(ctx).pop();
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Data OCR berhasil disalin ke formulir. Silakan periksa kembali.')),
                          );
                        },
                        child: const Text('Terapkan ke Form'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Bantuan OCR gagal memproses: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isScanning = false);
    }
  }

  Future<void> _submitVerification() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      final apiClient = ref.read(apiClientProvider);

      final payload = <String, dynamic>{
        'name': _nameController.text.trim(),
        'emergency_name': _emergencyNameController.text.trim(),
        'emergency_phone': _emergencyPhoneController.text.trim(),
        'occupation': _occupationController.text.trim(),
        'institution_name': _institutionController.text.trim(),
        'vehicle_type': _vehicleType,
        'vehicle_plate': _plateController.text.trim(),
      };

      if (_nikController.text.trim().isNotEmpty) {
        payload['nik'] = _nikController.text.trim();
      }

      final response = await apiClient.dio.put('/tenant/identity', data: payload);

      if (response.statusCode == 200 && response.data['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Colors.green,
            content: Text('Data profil & identitas KTP berhasil disimpan dengan aman.'),
          ),
        );
        _fetchExistingIdentity();
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(response.data['message'] ?? 'Gagal menyimpan identitas.')),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Terjadi kesalahan saat menyimpan: $e')),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isVerified = _existingIdentity?['is_nik_verified'] == true;
    final maskedNik = _existingIdentity?['masked_nik'];

    return Scaffold(
      appBar: AppBar(
        title: const Text('Edit Profil & Identitas'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16.0),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Status Badge Banner
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: isVerified ? Colors.green.withAlpha(20) : Colors.amber.withAlpha(20),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: isVerified ? Colors.green.withAlpha(80) : Colors.amber.withAlpha(80)),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            isVerified ? Icons.verified_user_rounded : Icons.pending_actions_rounded,
                            color: isVerified ? Colors.green : Colors.orange,
                            size: 32,
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  isVerified ? 'Identitas Terverifikasi Resmi' : 'Identitas Belum Terverifikasi',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 15,
                                    color: isVerified ? Colors.green.shade900 : Colors.brown,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  isVerified
                                      ? 'NIK Terdaftar: $maskedNik (Disamarkan)'
                                      : 'Lengkapi 16 digit NIK resmi untuk memenuhi standar verifikasi hunian kos.',
                                  style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),

                    // OCR Scanning Optional Card (Assistance Only, Not Mandatory)
                    Card(
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                        side: const BorderSide(color: AppColors.border),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            const Row(
                              children: [
                                Icon(Icons.document_scanner_rounded, color: AppColors.primary, size: 26),
                                SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('Bantuan Pemindai KTP (OCR Opsional)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                      Text('Bantu isi otomatis data NIK dan nama dari foto fisik KTP.', style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: OutlinedButton.icon(
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: AppColors.primary,
                                  side: const BorderSide(color: AppColors.primary),
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                                icon: _isScanning
                                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                    : const Icon(Icons.camera_alt_rounded, size: 18),
                                label: Text(_isScanning ? 'Memproses Pemindaian...' : 'Pindai KTP (Bantuan Pengisian)'),
                                onPressed: _isScanning ? null : _triggerOcrScan,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),

                    const Text('Data Identitas Penghuni', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                    const SizedBox(height: 12),

                    // Nama Lengkap sesuai KTP
                    TextFormField(
                      controller: _nameController,
                      decoration: InputDecoration(
                        labelText: 'Nama Lengkap Sesuai KTP *',
                        prefixIcon: const Icon(Icons.person_outline_rounded),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      validator: (v) {
                        if (v == null || v.trim().isEmpty) {
                          return 'Nama lengkap wajib diisi sesuai KTP.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),

                    // NIK TextField
                    TextFormField(
                      controller: _nikController,
                      keyboardType: TextInputType.number,
                      maxLength: 16,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      decoration: InputDecoration(
                        labelText: isVerified ? 'Ganti NIK KTP (Kosongkan jika tetap)' : 'Nomor Induk Kependudukan (NIK) *',
                        hintText: isVerified ? 'Saat ini: $maskedNik' : '16 digit angka sesuai KTP',
                        prefixIcon: const Icon(Icons.badge_rounded),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      validator: (v) {
                        if (!isVerified && (v == null || v.trim().length != 16)) {
                          return 'NIK harus terdiri dari tepat 16 digit angka.';
                        }
                        if (v != null && v.trim().isNotEmpty && v.trim().length != 16) {
                          return 'NIK harus tepat 16 digit angka.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),

                    // Kontak Darurat
                    TextFormField(
                      controller: _emergencyNameController,
                      decoration: InputDecoration(
                        labelText: 'Nama Kontak Darurat (Keluarga / Kerabat)',
                        prefixIcon: const Icon(Icons.person_pin_rounded),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                    const SizedBox(height: 14),

                    TextFormField(
                      controller: _emergencyPhoneController,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(
                        labelText: 'Nomor Telepon Kontak Darurat',
                        prefixIcon: const Icon(Icons.phone_in_talk_rounded),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Pekerjaan & Instansi
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: _occupationController,
                            decoration: InputDecoration(
                              labelText: 'Pekerjaan',
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: TextFormField(
                            controller: _institutionController,
                            decoration: InputDecoration(
                              labelText: 'Instansi / Kampus',
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),

                    // Kendaraan
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            value: _vehicleType,
                            decoration: InputDecoration(
                              labelText: 'Jenis Kendaraan',
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            items: const [
                              DropdownMenuItem(value: 'none', child: Text('Tidak Ada')),
                              DropdownMenuItem(value: 'motor', child: Text('Sepeda Motor')),
                              DropdownMenuItem(value: 'mobil', child: Text('Mobil')),
                            ],
                            onChanged: (val) {
                              if (val != null) setState(() => _vehicleType = val);
                            },
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: TextFormField(
                            controller: _plateController,
                            decoration: InputDecoration(
                              labelText: 'Plat Nomor',
                              hintText: 'Contoh: N 1234 AB',
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Security & Privacy Disclaimer
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.surfaceMuted,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: const Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.shield_outlined, color: AppColors.primary, size: 20),
                          SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              'Data KTP Anda disimpan secara aman pada penyimpanan privat server terenkripsi. NIK utuh tidak pernah dibagikan kepada pihak ketiga.',
                              style: TextStyle(fontSize: 11, color: AppColors.textSecondary, height: 1.3),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Tombol Konfirmasi Simpan
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
                            : const Icon(Icons.check_circle_outline_rounded),
                        label: Text(
                          _isSubmitting ? 'Menyimpan...' : 'Simpan Data Identitas',
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                        ),
                        onPressed: _isSubmitting ? null : _submitVerification,
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
