import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../auth/providers/auth_provider.dart';
import '../../housekeeping/models/housekeeping_model.dart';
import '../../housekeeping/providers/housekeeping_provider.dart';
import '../../inspections/models/inspection_model.dart';
import '../../inspections/providers/inspection_provider.dart';

class RoomServicesHubScreen extends ConsumerStatefulWidget {
  const RoomServicesHubScreen({super.key});

  @override
  ConsumerState<RoomServicesHubScreen> createState() => _RoomServicesHubScreenState();
}

class _RoomServicesHubScreenState extends ConsumerState<RoomServicesHubScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  Map<String, dynamic>? _activeRoom;
  bool _isLoadingRoom = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _fetchActiveRoom();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchActiveRoom() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final response = await apiClient.dio.get('/tenant/active-room');
      if (mounted && response.statusCode == 200 && response.data['success'] == true) {
        setState(() {
          _activeRoom = response.data['data'] as Map<String, dynamic>?;
          _isLoadingRoom = false;
        });
        return;
      }
    } catch (_) {}

    if (mounted) {
      setState(() => _isLoadingRoom = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final housekeepingAsync = ref.watch(housekeepingListProvider);
    final inspectionsAsync = ref.watch(inspectionsProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Layanan Kamar',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Segarkan',
            onPressed: () {
              ref.invalidate(housekeepingListProvider);
              ref.invalidate(inspectionsProvider);
              _fetchActiveRoom();
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Active Room Info Banner
            _buildRoomBanner(),

            // Service Action Cards
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Pilih Layanan Yang Dibutuhkan',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildServiceCard(
                    title: 'Pembersihan Kamar',
                    subtitle: 'Housekeeping & Sanitasi Ruangan',
                    description: 'Sapu & pel lantai, penggantian sprei, pembersihan kamar mandi, dan pewangi ruangan.',
                    badge: 'Rutin / Permintaan',
                    badgeColor: const Color(0xFF0D9488),
                    icon: Icons.cleaning_services_rounded,
                    actionText: 'Ajukan Pembersihan',
                    onAction: () => context.push('/housekeeping/request'),
                    onHistory: () => context.push('/housekeeping'),
                  ),
                  const SizedBox(height: 12),
                  _buildServiceCard(
                    title: 'Inspeksi & Cek Fisik Kamar',
                    subtitle: 'Room Inspection & Aset Bersama',
                    description: 'Pemeriksaan kelayakan fasilitas, inventaris AC/kasur/kunci, atau persiapan check-in/out.',
                    badge: 'Fisik & Inventaris',
                    badgeColor: const Color(0xFF2563EB),
                    icon: Icons.fact_check_rounded,
                    actionText: 'Ajukan Inspeksi',
                    onAction: () => context.push('/inspections/request'),
                    onHistory: () => context.push('/inspections'),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Live Requests Section
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Status Layanan Terkini',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: Colors.grey.shade300),
                        ),
                        child: TabBar(
                          controller: _tabController,
                          isScrollable: true,
                          tabAlignment: TabAlignment.start,
                          indicator: BoxDecoration(
                            color: AppColors.primary,
                            borderRadius: BorderRadius.circular(18),
                          ),
                          labelColor: Colors.white,
                          unselectedLabelColor: AppColors.textSecondary,
                          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                          padding: const EdgeInsets.all(2),
                          tabs: const [
                            Tab(text: 'Kebersihan'),
                            Tab(text: 'Inspeksi'),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    height: 280,
                    child: TabBarView(
                      controller: _tabController,
                      children: [
                        _buildHousekeepingLiveList(housekeepingAsync),
                        _buildInspectionsLiveList(inspectionsAsync),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _buildRoomBanner() {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0F766E), Color(0xFF14B8A6)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0D9488).withOpacity(0.25),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.meeting_room_rounded, color: Colors.white, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Unit Hunian Anda',
                  style: TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 2),
                if (_isLoadingRoom)
                  const Text('Memuat kamar aktif...', style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold))
                else if (_activeRoom != null)
                  Text(
                    'Kamar ${_activeRoom!['room_number'] ?? '-'} • ${_activeRoom!['property_name'] ?? 'Kos Anda'}',
                    style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                  )
                else
                  const Text(
                    'Kamar Aktif Penyewa',
                    style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                const SizedBox(height: 2),
                const Text(
                  'Layanan berlaku eksklusif untuk unit terdaftar',
                  style: TextStyle(color: Colors.white70, fontSize: 11),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildServiceCard({
    required String title,
    required String subtitle,
    required String description,
    required String badge,
    required Color badgeColor,
    required IconData icon,
    required String actionText,
    required VoidCallback onAction,
    required VoidCallback onHistory,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: badgeColor.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: badgeColor, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Flexible(
                          child: Text(
                            title,
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: badgeColor.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            badge,
                            style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: badgeColor),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: const TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            description,
            style: const TextStyle(fontSize: 12, color: AppColors.textSecondary, height: 1.4),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: onAction,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: badgeColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 11),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    elevation: 0,
                  ),
                  icon: const Icon(Icons.add_rounded, size: 18),
                  label: Text(actionText, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                ),
              ),
              const SizedBox(width: 8),
              OutlinedButton(
                onPressed: onHistory,
                style: OutlinedButton.styleFrom(
                  foregroundColor: badgeColor,
                  side: BorderSide(color: badgeColor.withOpacity(0.4)),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Riwayat', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildHousekeepingLiveList(AsyncValue<List<HousekeepingTaskModel>> housekeepingAsync) {
    return housekeepingAsync.when(
      data: (items) {
        if (items.isEmpty) {
          return _buildEmptyState('Belum ada permintaan kebersihan aktif.');
        }
        return ListView.separated(
          padding: EdgeInsets.zero,
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final item = items[index];
            final isPendingDecision = item.status == 'pending_verification';

            return InkWell(
              onTap: () => context.push('/housekeeping/${item.id}', extra: item),
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isPendingDecision ? const Color(0xFFF59E0B) : Colors.grey.shade200,
                    width: isPendingDecision ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: isPendingDecision ? const Color(0xFFFEF3C7) : const Color(0xFFCCFBF1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(
                        isPendingDecision ? Icons.notification_important_rounded : Icons.cleaning_services_rounded,
                        color: isPendingDecision ? const Color(0xFFD97706) : AppColors.primary,
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(item.taskNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                              _buildStatusBadge(item.status, item.statusLabel),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            item.scheduledDate != null
                                ? 'Jadwal: ${DateFormat('dd MMM yyyy').format(item.scheduledDate!)} ${item.scheduledSlot ?? ''}'
                                : item.typeLabel,
                            style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                          ),
                          if (isPendingDecision) ...[
                            const SizedBox(height: 4),
                            const Text(
                              '👉 Selesai dikerjakan — Perlu konfirmasi Anda',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFD97706)),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted, size: 20),
                  ],
                ),
              ),
            );
          },
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (_, __) => _buildEmptyState('Gagal memuat status kebersihan.'),
    );
  }

  Widget _buildInspectionsLiveList(AsyncValue<List<InspectionModel>> inspectionsAsync) {
    return inspectionsAsync.when(
      data: (items) {
        if (items.isEmpty) {
          return _buildEmptyState('Belum ada inspeksi kamar aktif.');
        }
        return ListView.separated(
          padding: EdgeInsets.zero,
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final item = items[index];
            final isWaitingApproval = item.status == 'completed';

            return InkWell(
              onTap: () => context.push('/inspection/${item.id}', extra: item),
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isWaitingApproval ? const Color(0xFF3B82F6) : Colors.grey.shade200,
                    width: isWaitingApproval ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: isWaitingApproval ? const Color(0xFFDBEAFE) : const Color(0xFFE2E8F0),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(
                        isWaitingApproval ? Icons.assignment_turned_in_rounded : Icons.fact_check_rounded,
                        color: isWaitingApproval ? const Color(0xFF2563EB) : const Color(0xFF475569),
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(item.inspectionCode, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                              _buildStatusBadge(item.status, item.statusLabel),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            item.scheduledDate != null
                                ? 'Tanggal: ${DateFormat('dd MMM yyyy').format(item.scheduledDate!)}'
                                : item.typeLabel,
                            style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                          ),
                          if (isWaitingApproval) ...[
                            const SizedBox(height: 4),
                            const Text(
                              '👉 Hasil inspeksi siap ditinjau & disetujui',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted, size: 20),
                  ],
                ),
              ),
            );
          },
        );
      },
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (_, __) => _buildEmptyState('Gagal memuat status inspeksi.'),
    );
  }

  Widget _buildStatusBadge(String status, String label) {
    Color bg = Colors.grey.shade100;
    Color fg = Colors.grey.shade800;

    switch (status) {
      case 'scheduled':
        bg = Colors.blue.shade50;
        fg = Colors.blue.shade700;
        break;
      case 'assigned':
      case 'in_progress':
        bg = Colors.amber.shade50;
        fg = Colors.amber.shade800;
        break;
      case 'pending_verification':
      case 'completed':
        bg = Colors.purple.shade50;
        fg = Colors.purple.shade700;
        break;
      case 'approved_by_tenant':
        bg = Colors.green.shade50;
        fg = Colors.green.shade700;
        break;
      case 'objected_by_tenant':
      case 'cancelled':
        bg = Colors.red.shade50;
        fg = Colors.red.shade700;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)),
      child: Text(label, style: TextStyle(color: fg, fontSize: 10, fontWeight: FontWeight.bold)),
    );
  }

  Widget _buildEmptyState(String message) {
    return Container(
      padding: const EdgeInsets.all(24),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.inbox_rounded, color: Colors.grey.shade400, size: 36),
          const SizedBox(height: 8),
          Text(message, style: const TextStyle(fontSize: 13, color: AppColors.textSecondary)),
        ],
      ),
    );
  }
}
