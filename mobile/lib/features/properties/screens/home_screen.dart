import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/models/user_model.dart';
import '../../auth/providers/auth_provider.dart';
import '../../invoices/providers/invoice_provider.dart';
import '../models/property_model.dart';
import '../providers/property_provider.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  int _currentNavIndex = 0;

  String _getTimeGreeting() {
    final hour = DateTime.now().hour;
    if (hour < 11) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final user = authState.user;
    final propertiesAsync = ref.watch(propertiesProvider);
    final invoicesAsync = ref.watch(invoicesProvider);

    final isCustomerOrTenant = (user == null || user.isTenant);

    return Scaffold(
      backgroundColor: AppColors.primary,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () async {
            ref.invalidate(propertiesProvider);
            ref.invalidate(invoicesProvider);
          },
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            child: isCustomerOrTenant
                ? _buildCustomerView(context, user, invoicesAsync)
                : _buildAdminView(context, user, propertiesAsync),
          ),
        ),
      ),
      bottomNavigationBar: _buildBottomNavigationBar(context, isCustomerOrTenant),
    );
  }

  // =========================================================================
  // SCREEN 1: ADMIN / OPERATIONAL VIEW (Selamat Pagi Admin 👋)
  // =========================================================================
  Widget _buildAdminView(
    BuildContext context,
    UserModel? user,
    AsyncValue<List<PropertyModel>> propertiesAsync,
  ) {
    final totalRooms = propertiesAsync.value?.fold<int>(0, (sum, p) => sum + p.totalRooms) ?? 24;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Top Header
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Greeting Row with Action Icons
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${_getTimeGreeting()} Admin 👋',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            letterSpacing: -0.5,
                          ),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Kelola layanan, pelanggan, dan pembayaran kos dengan mudah.',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 13,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Row(
                    children: [
                      _buildHeaderIconButton(
                        icon: Icons.notifications_none_rounded,
                        tooltip: 'Notifikasi',
                        onTap: () => context.push('/notifications'),
                      ),
                      const SizedBox(width: 8),
                      _buildHeaderIconButton(
                        icon: Icons.person_outline_rounded,
                        tooltip: 'Profil',
                        onTap: () => context.push('/profile'),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // 2x2 Floating Stat Cards Row 1
              Row(
                children: [
                  Expanded(
                    child: _buildAdminStatCard(
                      icon: Icons.people_alt_rounded,
                      iconBgColor: const Color(0xFFCCFBF1),
                      iconColor: AppColors.primary,
                      title: 'Total Pelanggan',
                      value: '140',
                      onTap: () => context.push('/bookings'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _buildAdminStatCard(
                      icon: Icons.water_drop_rounded,
                      iconBgColor: const Color(0xFFCCFBF1),
                      iconColor: AppColors.primary,
                      title: 'Total Layanan',
                      value: '$totalRooms',
                      onTap: () => _showServicesModal(context),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // 2x2 Floating Stat Cards Row 2
              Row(
                children: [
                  Expanded(
                    child: _buildAdminStatCard(
                      icon: Icons.pie_chart_rounded,
                      iconBgColor: const Color(0xFFCCFBF1),
                      iconColor: AppColors.primary,
                      title: 'Perlu Verifikasi',
                      value: '140',
                      onTap: () => context.push('/invoices'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _buildAdminStatCard(
                      icon: Icons.attach_money_rounded,
                      iconBgColor: const Color(0xFFCCFBF1),
                      iconColor: AppColors.primary,
                      title: 'Total Pemasukan',
                      value: '10.000',
                      onTap: () => context.push('/finance/dashboard'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        // White Curved Container (Menu Cepat & Aktifitas Terbaru)
        Container(
          width: double.infinity,
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Menu Cepat
              const Text(
                'Menu Cepat',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  _buildQuickActionBtn(
                    icon: Icons.person_add_alt_1_rounded,
                    label: 'Customer',
                    onTap: () => context.push('/bookings'),
                  ),
                  _buildQuickActionBtn(
                    icon: Icons.water_drop_rounded,
                    label: 'Layanan',
                    onTap: () => _showServicesModal(context),
                  ),
                  _buildQuickActionBtn(
                    icon: Icons.post_add_rounded,
                    label: 'Tagihan',
                    onTap: () => context.push('/invoices'),
                  ),
                  _buildQuickActionBtn(
                    icon: Icons.verified_rounded,
                    label: 'Verifikasi',
                    onTap: () => context.push('/inspections'),
                  ),
                ],
              ),
              const SizedBox(height: 28),

              // Aktifitas Terbaru
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Aktifitas Terbaru',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  GestureDetector(
                    onTap: () => context.push('/notifications'),
                    child: const Text(
                      'Lihat Semua',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: AppColors.secondary,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // List of activities matching the image
              _buildActivityItem(
                icon: Icons.person_rounded,
                iconBgColor: const Color(0xFFCCFBF1),
                iconColor: AppColors.primary,
                title: 'Pelanggan baru: Aditya',
                time: '5 menit lalu',
                onTap: () => context.push('/bookings'),
              ),
              const SizedBox(height: 12),
              _buildActivityItem(
                icon: Icons.description_rounded,
                iconBgColor: const Color(0xFFCCFBF1),
                iconColor: AppColors.primary,
                title: 'Tagihan baru: Royan',
                time: '8 menit lalu',
                onTap: () => context.push('/invoices'),
              ),
              const SizedBox(height: 12),
              _buildActivityItem(
                icon: Icons.pie_chart_rounded,
                iconBgColor: const Color(0xFFCCFBF1),
                iconColor: AppColors.primary,
                title: 'Pembayaran pending: Rafael',
                time: '15 menit lalu',
                onTap: () => context.push('/invoices'),
              ),
              const SizedBox(height: 12),
              _buildActivityItem(
                icon: Icons.check_circle_rounded,
                iconBgColor: const Color(0xFFCCFBF1),
                iconColor: AppColors.primary,
                title: 'Pembayaran terverifikasi: Zulkarnaen',
                time: '32 menit lalu',
                onTap: () => context.push('/invoices'),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ],
    );
  }

  // =========================================================================
  // SCREEN 2: CUSTOMER / TENANT VIEW (Halo Customer 👋)
  // =========================================================================
  Widget _buildCustomerView(
    BuildContext context,
    UserModel? user,
    AsyncValue<dynamic> invoicesAsync,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Top Header
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Greeting Row
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user != null ? 'Halo ${user.name} 👋' : 'Halo Customer 👋',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                            letterSpacing: -0.5,
                          ),
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Pantau penggunaan air, cek tagihan bulanan, layanan kos secara praktis',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 13,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Row(
                    children: [
                      _buildHeaderIconButton(
                        icon: Icons.notifications_none_rounded,
                        tooltip: 'Pengumuman & Notifikasi',
                        onTap: () => context.push('/announcements'),
                      ),
                      const SizedBox(width: 8),
                      _buildHeaderIconButton(
                        icon: Icons.person_outline_rounded,
                        tooltip: 'Profil',
                        onTap: () => context.push('/profile'),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // Big White Hero Card: Total Tagihan
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.08),
                      blurRadius: 16,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Row: Title and Status Badge
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Total Tagihan',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEE2E2),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text(
                            'Belum Lunas',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFFDC2626),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),

                    // Row: Nominal & Bayar Button
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        const Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Rp 224.000',
                              style: TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.w800,
                                color: AppColors.primary,
                              ),
                            ),
                            SizedBox(height: 6),
                            Text(
                              '24 Mei 2026',
                              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                            ),
                            SizedBox(height: 2),
                            Text(
                              'Penggunaan: 16 m³',
                              style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                            ),
                          ],
                        ),
                        ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                            elevation: 0,
                          ),
                          onPressed: () => context.push('/invoices'),
                          child: const Text(
                            'Bayar',
                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),

              // 2 Cards Row: Belum Lunas & Sudah Lunas
              Row(
                children: [
                  Expanded(
                    child: _buildCustomerCounterCard(
                      icon: Icons.note_alt_rounded,
                      iconColor: AppColors.primary,
                      label: 'Belum Lunas',
                      value: '1',
                      onTap: () => context.push('/invoices'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _buildCustomerCounterCard(
                      icon: Icons.assignment_turned_in_rounded,
                      iconColor: AppColors.primary,
                      label: 'Sudah Lunas',
                      value: '4',
                      onTap: () => context.push('/invoices'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        // White Curved Container (Tagihan per Bulan & Tagihan Terbaru)
        Container(
          width: double.infinity,
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 30),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Tagihan per Bulan Header
              const Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Tagihan per Bulan',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  Text(
                    '5 Bulan Terakhir',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.primary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Mini Bar Chart matching the image
              _buildMonthlyBarChart(),
              const SizedBox(height: 28),

              // Tagihan Terbaru
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Tagihan Terbaru',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  GestureDetector(
                    onTap: () => context.push('/invoices'),
                    child: const Text(
                      'Lihat Semua',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // List of Recent Invoices
              _buildCustomerInvoiceItem(
                monthYear: 'Mei 2026',
                usageInfo: 'Penggunaan: 24m³',
                amount: 'Rp 84.000',
                isPaid: true,
                onTap: () => context.push('/invoices'),
              ),
              const SizedBox(height: 12),
              _buildCustomerInvoiceItem(
                monthYear: 'April 2026',
                usageInfo: 'Penggunaan: 20m³',
                amount: 'Rp 50.000',
                isPaid: false,
                onTap: () => context.push('/invoices'),
              ),
              const SizedBox(height: 12),
              _buildCustomerInvoiceItem(
                monthYear: 'Maret 2026',
                usageInfo: 'Penggunaan: 20m³',
                amount: 'Rp 64.000',
                isPaid: true,
                onTap: () => context.push('/invoices'),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ],
    );
  }

  // =========================================================================
  // SUB-COMPONENTS & WIDGETS
  // =========================================================================

  Widget _buildHeaderIconButton({
    required IconData icon,
    required String tooltip,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(0.15),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: Colors.white, size: 20),
        ),
      ),
    );
  }

  Widget _buildAdminStatCard({
    required IconData icon,
    required Color iconBgColor,
    required Color iconColor,
    required String title,
    required String value,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: iconBgColor,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: iconColor, size: 24),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    value,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCustomerCounterCard({
    required IconData icon,
    required Color iconColor,
    required String label,
    required String value,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Row(
          children: [
            Icon(icon, color: iconColor, size: 26),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                ),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickActionBtn({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Column(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: const Color(0xFFCCFBF1),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: AppColors.primary, size: 28),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActivityItem({
    required IconData icon,
    required Color iconBgColor,
    required Color iconColor,
    required String title,
    required String time,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: iconBgColor,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: iconColor, size: 18),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    time,
                    style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMonthlyBarChart() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: [
          SizedBox(
            height: 100,
            child: Stack(
              children: [
                // Horizontal Guidelines
                Column(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Container(height: 1, color: const Color(0xFFE2E8F0)),
                    Container(height: 1, color: const Color(0xFFE2E8F0)),
                    Container(height: 1, color: const Color(0xFFE2E8F0)),
                  ],
                ),
                // 5 Bars Row
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    _buildBar(height: 40, isHighlighted: false),
                    _buildBar(height: 70, isHighlighted: false),
                    _buildBar(height: 55, isHighlighted: false),
                    _buildBar(height: 45, isHighlighted: false),
                    _buildBar(height: 95, isHighlighted: true),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          const Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              Text('Jan', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
              Text('Feb', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
              Text('Mar', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
              Text('Apr', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
              Text('Mei', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppColors.primary)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBar({required double height, required bool isHighlighted}) {
    return Container(
      width: 32,
      height: height,
      decoration: BoxDecoration(
        color: isHighlighted ? AppColors.primary : const Color(0xFFE2E8F0),
        borderRadius: const BorderRadius.vertical(top: Radius.circular(6)),
      ),
    );
  }

  Widget _buildCustomerInvoiceItem({
    required String monthYear,
    required String usageInfo,
    required String amount,
    required bool isPaid,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFCCFBF1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.calendar_month_rounded, color: AppColors.primary, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    monthYear,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    usageInfo,
                    style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  amount,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(height: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: isPaid ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    isPaid ? 'Lunas' : 'Belum Lunas',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: isPaid ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================================
  // BOTTOM NAVIGATION BAR
  // =========================================================================
  Widget _buildBottomNavigationBar(BuildContext context, bool isCustomer) {
    if (isCustomer) {
      return Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 10,
              offset: const Offset(0, -3),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(
                  icon: Icons.home_rounded,
                  label: 'Beranda',
                  isSelected: _currentNavIndex == 0,
                  onTap: () => setState(() => _currentNavIndex = 0),
                ),
                _buildNavItem(
                  icon: Icons.room_service_outlined,
                  label: 'Layanan',
                  isSelected: _currentNavIndex == 1,
                  onTap: () {
                    setState(() => _currentNavIndex = 1);
                    context.push('/room-services');
                  },
                ),
                _buildNavItem(
                  icon: Icons.receipt_long_outlined,
                  label: 'Tagihan',
                  isSelected: _currentNavIndex == 2,
                  onTap: () {
                    setState(() => _currentNavIndex = 2);
                    context.push('/invoices');
                  },
                ),
                _buildNavItem(
                  icon: Icons.person_outline_rounded,
                  label: 'Profil',
                  isSelected: _currentNavIndex == 3,
                  onTap: () {
                    setState(() => _currentNavIndex = 3);
                    context.push('/profile');
                  },
                ),
              ],
            ),
          ),
        ),
      );
    }

    // Admin / Staff Bottom Nav Bar
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 10,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildNavItem(
                icon: Icons.home_rounded,
                label: 'Beranda',
                isSelected: _currentNavIndex == 0,
                onTap: () => setState(() => _currentNavIndex = 0),
              ),
              _buildNavItem(
                icon: Icons.people_outline_rounded,
                label: 'Penghuni',
                isSelected: _currentNavIndex == 1,
                onTap: () {
                  setState(() => _currentNavIndex = 1);
                  context.push('/bookings');
                },
              ),
              _buildNavItem(
                icon: Icons.water_drop_outlined,
                label: 'Layanan',
                isSelected: _currentNavIndex == 2,
                onTap: () {
                  setState(() => _currentNavIndex = 2);
                  _showServicesModal(context);
                },
              ),
              _buildNavItem(
                icon: Icons.receipt_outlined,
                label: 'Tagihan',
                isSelected: _currentNavIndex == 3,
                onTap: () {
                  setState(() => _currentNavIndex = 3);
                  context.push('/invoices');
                },
              ),
              _buildNavItem(
                icon: Icons.person_outline_rounded,
                label: 'Profil',
                isSelected: _currentNavIndex == 4,
                onTap: () {
                  setState(() => _currentNavIndex = 4);
                  context.push('/profile');
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem({
    required IconData icon,
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    const activeColor = AppColors.primary;
    const inactiveColor = Color(0xFF94A3B8);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              color: isSelected ? activeColor : inactiveColor,
              size: 24,
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                color: isSelected ? activeColor : inactiveColor,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // Bottom Sheet Layanan Lengkap (Housekeeping, Utilitas, Maintenance, Inspeksi)
  void _showServicesModal(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(20.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Pilih Layanan Operasional Kos',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
            ),
            const SizedBox(height: 16),
            ListTile(
              leading: const Icon(Icons.room_service_rounded, color: AppColors.primary),
              title: const Text('Layanan Kamar (Inspeksi & Pembersihan)'),
              subtitle: const Text('Pengajuan dan pemantauan layanan unit hunian'),
              onTap: () {
                Navigator.pop(ctx);
                context.push('/room-services');
              },
            ),
            ListTile(
              leading: const Icon(Icons.speed_rounded, color: AppColors.primary),
              title: const Text('Catat Meteran Listrik & Air'),
              subtitle: const Text('Input angka meter kWh dan m³ bulanan'),
              onTap: () {
                Navigator.pop(ctx);
                context.push('/utilities');
              },
            ),
            ListTile(
              leading: const Icon(Icons.cleaning_services_rounded, color: Colors.teal),
              title: const Text('Tugas Housekeeping'),
              subtitle: const Text('Jadwal & checklist pembersihan kamar'),
              onTap: () {
                Navigator.pop(ctx);
                context.push('/housekeeping');
              },
            ),
            ListTile(
              leading: const Icon(Icons.build_rounded, color: Colors.blue),
              title: const Text('Maintenance & Perbaikan'),
              subtitle: const Text('Tiket kendala teknis dan fasilitas'),
              onTap: () {
                Navigator.pop(ctx);
                context.push('/maintenance');
              },
            ),
            ListTile(
              leading: const Icon(Icons.fact_check_rounded, color: Colors.orange),
              title: const Text('Inspeksi Kamar (Check-in / Check-out)'),
              subtitle: const Text('Pemeriksaan kondisi fisik kamar'),
              onTap: () {
                Navigator.pop(ctx);
                context.push('/inspections');
              },
            ),
          ],
        ),
      ),
    );
  }
}
