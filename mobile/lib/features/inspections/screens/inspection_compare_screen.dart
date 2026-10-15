import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/app_colors.dart';
import '../providers/inspection_provider.dart';

class InspectionCompareScreen extends ConsumerWidget {
  final int currentId;
  final int baselineId;

  const InspectionCompareScreen({
    super.key,
    required this.currentId,
    required this.baselineId,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final comparisonAsync = ref.watch(
      inspectionComparisonProvider((currentId: currentId, baselineId: baselineId)),
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Perbandingan Kondisi Kamar'),
      ),
      body: comparisonAsync.when(
        data: (data) {
          if (data == null) {
            return const Center(child: Text('Data perbandingan tidak ditemukan.'));
          }

          final current = data['current_inspection'] as Map<String, dynamic>? ?? {};
          final baseline = data['baseline_inspection'] as Map<String, dynamic>? ?? {};
          final differences = (data['differences'] as List<dynamic>?) ?? [];
          final bool hasDegradation = data['has_degradation'] == true;
          final num totalDamageCost = num.tryParse(data['total_damage_cost']?.toString() ?? '0') ?? 0;

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(
                inspectionComparisonProvider((currentId: currentId, baselineId: baselineId)),
              );
            },
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Overview Comparison Header
                  Card(
                    elevation: 1,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Perbandingan Riwayat Serah Terima',
                            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.blue.shade50,
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(color: Colors.blue.shade200),
                                  ),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('Kondisi Awal (Baseline)',
                                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.blue)),
                                      const SizedBox(height: 4),
                                      Text(
                                        baseline['inspection_code']?.toString() ?? 'Check-in',
                                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                                      ),
                                      Text(
                                        baseline['type']?.toString().toUpperCase() ?? 'CHECK_IN',
                                        style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              const Icon(Icons.arrow_forward_rounded, color: AppColors.primary),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: Colors.purple.shade50,
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(color: Colors.purple.shade200),
                                  ),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('Kondisi Akhir',
                                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.purple)),
                                      const SizedBox(height: 4),
                                      Text(
                                        current['inspection_code']?.toString() ?? 'Check-out',
                                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                                      ),
                                      Text(
                                        current['type']?.toString().toUpperCase() ?? 'CHECK_OUT',
                                        style: const TextStyle(fontSize: 11, color: AppColors.textSecondary),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Degradation Alert
                  if (hasDegradation)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.red.shade200),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Row(
                            children: [
                              Icon(Icons.warning_amber_rounded, color: Colors.red, size: 24),
                              SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Terdeteksi Penurunan Kondisi / Kerusakan',
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.red,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Terdapat fasilitas yang kondisinya memburuk atau rusak dibandingkan saat awal check-in.',
                            style: TextStyle(fontSize: 12),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Total Estimasi Biaya Kerusakan:',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              Text(
                                'Rp ${totalDamageCost.toStringAsFixed(0)}',
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.red,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            '*Estimasi biaya ini dapat diperhitungkan dalam pemotongan deposit sewa.',
                            style: TextStyle(fontSize: 11, fontStyle: FontStyle.italic, color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                    )
                  else
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.green.shade50,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.green.shade200),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.check_circle_outline_rounded, color: Colors.green, size: 24),
                          SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Kondisi Kamar Terjaga Baik',
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.green,
                                  ),
                                ),
                                SizedBox(height: 2),
                                Text(
                                  'Tidak ada penurunan kondisi signifikan atau kerusakan baru pada fasilitas.',
                                  style: TextStyle(fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                  const SizedBox(height: 20),

                  // Differences List Section
                  Text(
                    'Daftar Perubahan Kondisi Fasilitas (${differences.length})',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),

                  if (differences.isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(
                          child: Text('Semua item fasilitas berada dalam kondisi yang sama persis.'),
                        ),
                      ),
                    )
                  else
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: differences.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        final diff = differences[index] as Map<String, dynamic>;
                        final isDegraded = diff['is_degraded'] == true;
                        final baselineCond = diff['baseline_condition']?.toString() ?? '-';
                        final currentCond = diff['current_condition']?.toString() ?? '-';
                        final estCost = num.tryParse(diff['estimated_cost']?.toString() ?? '0') ?? 0;

                        return Card(
                          elevation: 1,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                            side: BorderSide(
                              color: isDegraded ? Colors.red.shade200 : Colors.grey.shade200,
                            ),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Expanded(
                                      child: Text(
                                        diff['item_name']?.toString() ?? '-',
                                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                                      ),
                                    ),
                                    if (isDegraded)
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: Colors.red.shade50,
                                          borderRadius: BorderRadius.circular(4),
                                        ),
                                        child: const Text(
                                          'Menurun',
                                          style: TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.bold,
                                            color: Colors.red,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    Expanded(
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                        decoration: BoxDecoration(
                                          color: Colors.grey.shade100,
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Text('Awal', style: TextStyle(fontSize: 10, color: AppColors.textSecondary)),
                                            Text(
                                              baselineCond.toUpperCase(),
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                    const Padding(
                                      padding: EdgeInsets.symmetric(horizontal: 8),
                                      child: Icon(Icons.arrow_forward_rounded, size: 18, color: AppColors.textSecondary),
                                    ),
                                    Expanded(
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                        decoration: BoxDecoration(
                                          color: isDegraded ? Colors.red.shade50 : Colors.grey.shade100,
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Text('Sekarang', style: TextStyle(fontSize: 10, color: AppColors.textSecondary)),
                                            Text(
                                              currentCond.toUpperCase(),
                                              style: TextStyle(
                                                fontWeight: FontWeight.bold,
                                                fontSize: 12,
                                                color: isDegraded ? Colors.red : Colors.black,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                if (diff['current_notes'] != null &&
                                    diff['current_notes'].toString().isNotEmpty) ...[
                                  const SizedBox(height: 8),
                                  Text(
                                    'Catatan: ${diff['current_notes']}',
                                    style: const TextStyle(fontSize: 12, fontStyle: FontStyle.italic),
                                  ),
                                ],
                                if (estCost > 0) ...[
                                  const SizedBox(height: 8),
                                  Text(
                                    'Estimasi Biaya: Rp ${estCost.toStringAsFixed(0)}',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                      color: Colors.red,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                ],
              ),
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              Text('Gagal memuat komparasi: $err'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => ref.invalidate(
                  inspectionComparisonProvider((currentId: currentId, baselineId: baselineId)),
                ),
                child: const Text('Coba Lagi'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
