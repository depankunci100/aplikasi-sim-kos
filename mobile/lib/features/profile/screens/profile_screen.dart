import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  bool _isLoadingIdentity = true;
  Map<String, dynamic>? _identityData;

  @override
  void initState() {
    super.initState();
    _fetchTenantIdentity();
  }

  Future<void> _fetchTenantIdentity() async {
    setState(() => _isLoadingIdentity = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.get('/tenant/identity');
      if (response.statusCode == 200 && response.data['success'] == true) {
        if (mounted) {
          setState(() {
            _identityData = response.data['data'] as Map<String, dynamic>?;
            _isLoadingIdentity = false;
          });
        }
      } else {
        if (mounted) setState(() => _isLoadingIdentity = false);
      }
    } catch (_) {
      if (mounted) setState(() => _isLoadingIdentity = false);
    }
  }

  String _formatDate(String? isoString) {
    if (isoString == null || isoString.isEmpty) return '-';
    try {
      final dt = DateTime.parse(isoString).toLocal();
      final months = [
        '',
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des'
      ];
      final hour = dt.hour.toString().padLeft(2, '0');
      final min = dt.minute.toString().padLeft(2, '0');
      return '${dt.day} ${months[dt.month]} ${dt.year}, $hour:$min WIB';
    } catch (_) {
      return isoString;
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final user = authState.user;
    final isTenant = user?.isTenant ?? false;
    final isVerified = _identityData?['is_nik_verified'] == true;
    final maskedNik = _identityData?['masked_nik'] ?? 'Belum terdaftar';
    final lastUpdated = _identityData?['updated_at'] ?? _identityData?['nik_verified_at'];

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil & Identitas'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Segarkan',
            onPressed: _fetchTenantIdentity,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchTenantIdentity,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(20.0),
          child: Column(
            children: [
              // User Avatar & Name Header
              Center(
                child: Stack(
                  children: [
                    CircleAvatar(
                      radius: 44,
                      backgroundColor: AppColors.primary.withAlpha(25),
                      child: Text(
                        user != null && user.name.isNotEmpty ? user.name[0].toUpperCase() : 'U',
                        style: const TextStyle(fontSize: 34, fontWeight: FontWeight.bold, color: AppColors.primary),
                      ),
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: Container(
                        padding: const EdgeInsets.all(6),
                        decoration: const BoxDecoration(
                          color: AppColors.primary,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.badge_rounded, size: 14, color: Colors.white),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Text(
                _identityData?['name'] ?? user?.name ?? 'Nama Pengguna',
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.primary.withAlpha(25),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  user?.roleLabel ?? 'Penyewa / Tamu',
                  style: const TextStyle(color: AppColors.primary, fontSize: 12, fontWeight: FontWeight.w600),
                ),
              ),
              const SizedBox(height: 24),

              // Section: Profil & Identitas KTP (Tenant Identity UX)
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: AppColors.border),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(18.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Row(
                            children: [
                              Icon(Icons.badge_outlined, color: AppColors.primary, size: 22),
                              SizedBox(width: 8),
                              Text(
                                'Identitas Kependudukan',
                                style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                              ),
                            ],
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: isVerified ? Colors.green.withAlpha(25) : Colors.amber.withAlpha(30),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  isVerified ? Icons.check_circle_rounded : Icons.pending_rounded,
                                  size: 13,
                                  color: isVerified ? Colors.green.shade800 : Colors.brown,
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  isVerified ? 'Terverifikasi' : 'Belum Diverifikasi',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: isVerified ? Colors.green.shade900 : Colors.brown,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      const Divider(height: 1),
                      const SizedBox(height: 14),

                      if (_isLoadingIdentity)
                        const Center(child: Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator()))
                      else ...[
                        _IdentityDetailRow(
                          icon: Icons.person_outline_rounded,
                          label: 'Nama Sesuai KTP',
                          value: _identityData?['name'] ?? user?.name ?? '-',
                        ),
                        const SizedBox(height: 12),
                        _IdentityDetailRow(
                          icon: Icons.fingerprint_rounded,
                          label: 'NIK (Disamarkan Demi Privasi)',
                          value: maskedNik,
                          isHighlight: isVerified,
                        ),
                        const SizedBox(height: 12),
                        _IdentityDetailRow(
                          icon: Icons.schedule_rounded,
                          label: 'Terakhir Diperbarui',
                          value: _formatDate(lastUpdated),
                        ),
                        const SizedBox(height: 12),
                        _IdentityDetailRow(
                          icon: Icons.contact_emergency_outlined,
                          label: 'Kontak Darurat',
                          value: (_identityData?['emergency_name'] != null && _identityData!['emergency_name'].isNotEmpty)
                              ? '${_identityData!['emergency_name']} (${_identityData?['emergency_phone'] ?? "-"})'
                              : 'Belum diisi',
                        ),
                        const SizedBox(height: 12),
                        _IdentityDetailRow(
                          icon: Icons.work_outline_rounded,
                          label: 'Pekerjaan / Instansi',
                          value: (_identityData?['occupation'] != null && _identityData!['occupation'].isNotEmpty)
                              ? '${_identityData!['occupation']} - ${_identityData?['institution_name'] ?? ""}'
                              : 'Belum diisi',
                        ),
                        const SizedBox(height: 12),
                        _IdentityDetailRow(
                          icon: Icons.two_wheeler_rounded,
                          label: 'Kendaraan',
                          value: (_identityData?['vehicle_type'] != null && _identityData!['vehicle_type'] != 'none')
                              ? '${_identityData!['vehicle_type'] == "motor" ? "Sepeda Motor" : "Mobil"} (${_identityData?['vehicle_plate'] ?? "-"})'
                              : 'Tidak ada kendaraan',
                        ),
                      ],
                      const SizedBox(height: 18),

                      // Button Edit Identitas
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            elevation: 0,
                          ),
                          icon: const Icon(Icons.edit_note_rounded, size: 20),
                          label: Text(isVerified ? 'Edit Data Identitas' : 'Lengkapi / Verifikasi Identitas'),
                          onPressed: () async {
                            await context.push('/identity/verify');
                            _fetchTenantIdentity();
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Akun & Kontak Card
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: AppColors.border),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(18.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.account_circle_outlined, color: AppColors.primary, size: 22),
                          SizedBox(width: 8),
                          Text(
                            'Informasi Akun',
                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      const Divider(height: 1),
                      const SizedBox(height: 14),
                      _IdentityDetailRow(
                        icon: Icons.email_outlined,
                        label: 'Email Akun',
                        value: user?.email ?? '-',
                      ),
                      const SizedBox(height: 12),
                      _IdentityDetailRow(
                        icon: Icons.phone_outlined,
                        label: 'Nomor Handphone',
                        value: user?.phone ?? '-',
                      ),
                      const SizedBox(height: 12),
                      const _IdentityDetailRow(
                        icon: Icons.verified_user_outlined,
                        label: 'Status Akun',
                        value: 'Aktif Terdaftar',
                      ),
                    ],
                  ),
                ),
              ),
              // Layanan Kamar Shortcut (Khusus Penyewa)
              if (isTenant) ...[
                Card(
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: const BorderSide(color: AppColors.border),
                  ),
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
                    leading: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withAlpha(25),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.room_service_rounded, color: AppColors.primary, size: 22),
                    ),
                    title: const Text('Layanan Kamar Saya', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    subtitle: const Text('Pengajuan inspeksi & pembersihan unit', style: TextStyle(fontSize: 12)),
                    trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
                    onTap: () => context.push('/room-services'),
                  ),
                ),
                const SizedBox(height: 20),
              ],

              // Logout Button
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.danger,
                    side: const BorderSide(color: AppColors.danger),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  icon: const Icon(Icons.logout_rounded),
                  label: const Text('Keluar dari Akun', style: TextStyle(fontWeight: FontWeight.w600)),
                  onPressed: () async {
                    final confirm = await showDialog<bool>(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        title: const Text('Konfirmasi Keluar'),
                        content: const Text('Apakah Anda yakin ingin keluar dari aplikasi?'),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(ctx, false),
                            child: const Text('Batal'),
                          ),
                          ElevatedButton(
                            style: ElevatedButton.styleFrom(backgroundColor: AppColors.danger),
                            onPressed: () => Navigator.pop(ctx, true),
                            child: const Text('Keluar'),
                          ),
                        ],
                      ),
                    );

                    if (confirm == true) {
                      await ref.read(authProvider.notifier).logout();
                      if (context.mounted) {
                        context.go('/login');
                      }
                    }
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _IdentityDetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool isHighlight;

  const _IdentityDetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.isHighlight = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: isHighlight ? AppColors.primary : AppColors.textSecondary),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textSecondary)),
              const SizedBox(height: 2),
              Text(
                value,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: isHighlight ? AppColors.primary : AppColors.textPrimary,
                  fontFamily: isHighlight ? 'monospace' : null,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
