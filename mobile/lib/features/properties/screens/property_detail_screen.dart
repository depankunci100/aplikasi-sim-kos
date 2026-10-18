import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../models/property_model.dart';

class PropertyDetailScreen extends StatelessWidget {
  final PropertyModel property;

  const PropertyDetailScreen({super.key, required this.property});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(property.name),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header Image Placeholder / Gradient
            Container(
              height: 160,
              width: double.infinity,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppColors.primary, AppColors.secondary],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Center(
                child: Icon(Icons.hotel_rounded, size: 64, color: Colors.white),
              ),
            ),
            const SizedBox(height: 18),

            // Badge & Name
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withAlpha(25),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    property.typeLabel,
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const Spacer(),
                Text(
                  'Kode: ${property.code}',
                  style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              property.name,
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                const Icon(Icons.location_on_outlined, size: 16, color: AppColors.textSecondary),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    '${property.address}, ${property.city}',
                    style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Fasilitas Properti
            Text(
              'Fasilitas Bersama',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: property.facilities.map((fac) {
                return Chip(
                  label: Text(fac, style: const TextStyle(fontSize: 12)),
                  backgroundColor: AppColors.surfaceMuted,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  side: BorderSide.none,
                  avatar: const Icon(Icons.check_circle_outline, size: 16, color: AppColors.primary),
                );
              }).toList(),
            ),
            const SizedBox(height: 20),

            // Aturan Rumah
            if (property.rules != null && property.rules!.isNotEmpty) ...[
              Text(
                'Tata Tertib Properti',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.surfaceMuted,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.border),
                ),
                child: Text(
                  property.rules!,
                  style: const TextStyle(fontSize: 13, color: AppColors.textSecondary, height: 1.5),
                ),
              ),
              const SizedBox(height: 20),
            ],

            // Pilihan Kamar
            Text(
              'Ketersediaan Kamar',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            _RoomTypeSampleCard(
              title: 'Deluxe AC (Kamar Mandi Dalam)',
              priceDaily: 180000,
              priceMonthly: 1500000,
              availableCount: 3,
              propertyId: property.id,
              propertyName: property.name,
            ),
            const SizedBox(height: 12),
            _RoomTypeSampleCard(
              title: 'Standard Fan (Ventilasi Alami)',
              priceDaily: 100000,
              priceMonthly: 850000,
              availableCount: 3,
              propertyId: property.id,
              propertyName: property.name,
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

class _RoomTypeSampleCard extends StatelessWidget {
  final String title;
  final num priceDaily;
  final num priceMonthly;
  final int availableCount;
  final int? propertyId;
  final String? propertyName;

  const _RoomTypeSampleCard({
    required this.title,
    required this.priceDaily,
    required this.priceMonthly,
    required this.availableCount,
    this.propertyId,
    this.propertyName,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppColors.success.withAlpha(25),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    '$availableCount Kamar Tersedia',
                    style: const TextStyle(color: AppColors.success, fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Sewa Bulanan (Kos)', style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                      Text(
                        '${Formatters.toRupiah(priceMonthly)} / bln',
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.primary),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Sewa Harian (Guesthouse)', style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                      Text(
                        '${Formatters.toRupiah(priceDaily)} / malam',
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.secondary),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  context.push(
                    '/guest/book',
                    extra: {
                      'room_id': propertyId ?? 1,
                      'property_name': propertyName ?? 'Kos Permata Hijau',
                      'room_number': '101',
                      'room_type_name': title,
                    },
                  );
                },
                child: const Text('Pesan Kamar Sekarang'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
