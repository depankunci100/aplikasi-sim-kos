import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/utility_meter_model.dart';
import '../models/utility_reading_model.dart';
import '../providers/utility_provider.dart';

class UtilityMetersScreen extends ConsumerStatefulWidget {
  const UtilityMetersScreen({super.key});

  @override
  ConsumerState<UtilityMetersScreen> createState() => _UtilityMetersScreenState();
}

class _UtilityMetersScreenState extends ConsumerState<UtilityMetersScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);
    final isTenant = authState.user?.isTenant ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Utilitas & Meteran'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () {
              ref.invalidate(utilityMetersProvider);
              ref.invalidate(tenantConsumptionProvider);
            },
          ),
        ],
      ),
      body: isTenant ? _buildTenantView() : _buildStaffView(),
    );
  }

  // ==========================================
  // VIEW UNTUK PENYEWA (TENANT)
  // ==========================================
  Widget _buildTenantView() {
    final consumptionAsync = ref.watch(tenantConsumptionProvider);

    return consumptionAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (err, stack) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: AppColors.danger),
              const SizedBox(height: 12),
              Text('Gagal memuat data: $err', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.invalidate(tenantConsumptionProvider),
                child: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
      ),
      data: (readings) {
        if (readings.isEmpty) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(32.0),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.speed_rounded, size: 64, color: Colors.grey.shade400),
                  const SizedBox(height: 16),
                  const Text(
                    'Belum Ada Catatan Meter',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Catatan pemakaian listrik dan air untuk kamar Anda akan tampil di sini setelah staf melakukan pencatatan resmi.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
                  ),
                ],
              ),
            ),
          );
        }

        // Ambil pembacaan terbaru
        final latest = readings.first;

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(tenantConsumptionProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Ringkasan Pemakaian Terakhir
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [AppColors.primary, AppColors.primaryDark],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primary.withAlpha(50),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.white.withAlpha(40),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            'Kamar ${latest.roomNumber ?? '101'}',
                            style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                          ),
                        ),
                        Text(
                          'Catatan: ${Formatters.toShortDate(latest.readingDate)}',
                          style: const TextStyle(color: Colors.white70, fontSize: 12),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Pemakaian Terakhir',
                      style: TextStyle(color: Colors.white70, fontSize: 13),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.baseline,
                      textBaseline: TextBaseline.alphabetic,
                      children: [
                        Text(
                          latest.usage.toStringAsFixed(1),
                          style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          latest.unit ?? 'kWh',
                          style: const TextStyle(color: Colors.white, fontSize: 16),
                        ),
                        const Spacer(),
                        Text(
                          Formatters.toRupiah(latest.totalCost),
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                    const Divider(color: Colors.white24, height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Meter: #${latest.meterNumber ?? '-'}',
                          style: const TextStyle(color: Colors.white70, fontSize: 12),
                        ),
                        Text(
                          latest.isBilled ? 'Sudah Masuk Invoice' : 'Siap Ditagihkan',
                          style: TextStyle(
                            color: latest.isBilled ? Colors.lightGreenAccent : Colors.amberAccent,
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),

              const Text(
                'Riwayat Pemakaian Kamar',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 12),

              ...readings.map((reading) => _buildReadingHistoryCard(reading)),
            ],
          ),
        );
      },
    );
  }

  Widget _buildReadingHistoryCard(UtilityReadingModel reading) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(8),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Icon(
                    reading.isElectricity ? Icons.bolt_rounded : Icons.water_drop_rounded,
                    color: reading.isElectricity ? Colors.amber.shade700 : Colors.blue.shade700,
                    size: 20,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    reading.isElectricity ? 'Listrik' : 'Air',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: reading.isBilled ? Colors.blue.withAlpha(25) : Colors.green.withAlpha(25),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  reading.isBilled ? 'Billed' : reading.statusLabel,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: reading.isBilled ? Colors.blue.shade800 : Colors.green.shade800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Sebelumnya &rarr; Terkini', style: TextStyle(color: AppColors.textSecondary, fontSize: 11)),
                  const SizedBox(height: 2),
                  Text(
                    '${reading.previousMeter.toStringAsFixed(1)} &rarr; ${reading.currentMeter.toStringAsFixed(1)} ${reading.unit ?? ''}',
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text('Total Biaya', style: TextStyle(color: AppColors.textSecondary, fontSize: 11)),
                  const SizedBox(height: 2),
                  Text(
                    Formatters.toRupiah(reading.totalCost),
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppColors.primary),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Periode / Tanggal: ${Formatters.toShortDate(reading.readingDate)}',
            style: const TextStyle(color: AppColors.textSecondary, fontSize: 11),
          ),
        ],
      ),
    );
  }

  // ==========================================
  // VIEW UNTUK STAFF / OWNER / MANAGER / KASIR
  // ==========================================
  Widget _buildStaffView() {
    final metersAsync = ref.watch(utilityMetersProvider);

    return Column(
      children: [
        // Tab Bar Jenis Utilitas
        Container(
          color: Colors.white,
          child: TabBar(
            controller: _tabController,
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textSecondary,
            indicatorColor: AppColors.primary,
            tabs: const [
              Tab(text: 'Semua Meter'),
              Tab(text: 'Listrik (kWh)'),
              Tab(text: 'Air (m³)'),
            ],
          ),
        ),

        // Search Bar
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: TextField(
            onChanged: (val) => setState(() => _searchQuery = val.toLowerCase()),
            decoration: InputDecoration(
              hintText: 'Cari No. Meter atau Kamar...',
              prefixIcon: const Icon(Icons.search_rounded, size: 20),
              filled: true,
              fillColor: Colors.grey.shade100,
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide.none,
              ),
            ),
          ),
        ),

        // List Meter
        Expanded(
          child: metersAsync.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (err, stack) => Center(
              child: Text('Gagal memuat meter: $err'),
            ),
            data: (meters) {
              return TabBarView(
                controller: _tabController,
                children: [
                  _buildMeterList(meters, null),
                  _buildMeterList(meters, 'electricity'),
                  _buildMeterList(meters, 'water'),
                ],
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildMeterList(List<UtilityMeterModel> allMeters, String? filterType) {
    var filtered = allMeters;
    if (filterType != null) {
      filtered = filtered.where((m) => m.utilityType == filterType).toList();
    }
    if (_searchQuery.isNotEmpty) {
      filtered = filtered.where((m) =>
          m.meterNumber.toLowerCase().contains(_searchQuery) ||
          (m.roomNumber?.toLowerCase().contains(_searchQuery) ?? false) ||
          (m.propertyName?.toLowerCase().contains(_searchQuery) ?? false)
      ).toList();
    }

    if (filtered.isEmpty) {
      return const Center(
        child: Text(
          'Tidak ada data meter yang sesuai filter.',
          style: TextStyle(color: AppColors.textSecondary),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(utilityMetersProvider),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        itemCount: filtered.length,
        itemBuilder: (context, index) {
          final meter = filtered[index];
          return _buildMeterCard(meter);
        },
      ),
    );
  }

  Widget _buildMeterCard(UtilityMeterModel meter) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(6),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 16,
                    backgroundColor: meter.isElectricity
                        ? Colors.amber.shade100
                        : Colors.blue.shade100,
                    child: Icon(
                      meter.isElectricity ? Icons.bolt_rounded : Icons.water_drop_rounded,
                      size: 18,
                      color: meter.isElectricity ? Colors.amber.shade900 : Colors.blue.shade900,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        meter.meterNumber,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      Text(
                        '${meter.propertyName ?? '-'} • ${meter.roomNumber != null ? 'Kamar ${meter.roomNumber}' : 'Komunal'}',
                        style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                      ),
                    ],
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: meter.isActive ? Colors.green.withAlpha(20) : Colors.grey.withAlpha(20),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  meter.isActive ? 'Aktif' : 'Nonaktif',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: meter.isActive ? Colors.green.shade800 : Colors.grey.shade700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Display Angka Pembacaan Terakhir
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Pembacaan Terakhir:', style: TextStyle(color: AppColors.textSecondary, fontSize: 12)),
                Text(
                  '${meter.currentReading.toStringAsFixed(2)} ${meter.unit}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppColors.primary),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // Tombol Aksi Cepat: Catat Nilai Meter
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () {
                context.push('/utilities/record', extra: meter);
              },
              icon: const Icon(Icons.edit_note_rounded, size: 18),
              label: const Text('Catat Nilai Meter'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                padding: const EdgeInsets.symmetric(vertical: 10),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
