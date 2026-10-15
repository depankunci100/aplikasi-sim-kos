import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/inspections/models/inspection_item_model.dart';
import 'package:mobile/features/inspections/models/inspection_model.dart';
import 'package:mobile/features/inspections/providers/inspection_provider.dart';
import 'package:mobile/features/inspections/screens/inspection_compare_screen.dart';
import 'package:mobile/features/inspections/screens/inspection_detail_screen.dart';
import 'package:mobile/features/inspections/screens/inspections_screen.dart';

void main() {
  group('Inspection Models Tests', () {
    test('InspectionItemModel parses json and derives labels & colors correctly', () {
      final json = {
        'id': 1,
        'inspection_id': 10,
        'item_name': 'AC Split 1 PK',
        'category': 'Elektronik',
        'condition': 'damaged',
        'notes': 'Tidak dingin dan bocor',
        'repair_required': true,
        'estimated_cost': 250000,
      };

      final item = InspectionItemModel.fromJson(json);

      expect(item.id, 1);
      expect(item.itemName, 'AC Split 1 PK');
      expect(item.category, 'Elektronik');
      expect(item.condition, 'damaged');
      expect(item.conditionLabel, 'Rusak');
      expect(item.conditionColor, Colors.red);
      expect(item.repairRequired, true);
      expect(item.estimatedCost, 250000);
    });

    test('InspectionModel parses json with items, media, and logs', () {
      final json = {
        'id': 100,
        'inspection_code': 'INSP-20261015-TEST',
        'property_id': 1,
        'room_id': 5,
        'booking_id': 12,
        'type': 'check_in',
        'status': 'completed',
        'overall_condition': 'good',
        'inspector_notes': 'Semua dalam kondisi prima',
        'property': {'name': 'Kos Elite Harmoni'},
        'room': {'room_number': 'Kamar 205'},
        'inspector': {'name': 'Agus Petugas'},
        'items': [
          {
            'id': 101,
            'inspection_id': 100,
            'item_name': 'Kunci Pintu',
            'category': 'Pintu & Jendela',
            'condition': 'good',
            'repair_required': false,
            'estimated_cost': 0,
          }
        ],
        'media': [
          {
            'id': 201,
            'inspection_id': 100,
            'file_path': 'inspections/100/kunci.jpg',
            'file_name': 'kunci.jpg',
            'caption': 'Foto anak kunci',
            'media_type': 'image/jpeg',
          }
        ],
        'logs': [
          {
            'id': 301,
            'action': 'completed',
            'description': 'Inspeksi telah diselesaikan oleh pemeriksa',
            'user': {'name': 'Agus Petugas'},
          }
        ],
      };

      final inspection = InspectionModel.fromJson(json);

      expect(inspection.id, 100);
      expect(inspection.inspectionCode, 'INSP-20261015-TEST');
      expect(inspection.typeLabel, 'Check-in (Awal)');
      expect(inspection.statusLabel, 'Menunggu Persetujuan Penyewa');
      expect(inspection.canTenantDecide, true);
      expect(inspection.overallConditionLabel, 'Sangat Baik / Layak');
      expect(inspection.propertyName, 'Kos Elite Harmoni');
      expect(inspection.roomNumber, 'Kamar 205');
      expect(inspection.items.length, 1);
      expect(inspection.items.first.itemName, 'Kunci Pintu');
      expect(inspection.media.length, 1);
      expect(inspection.media.first.caption, 'Foto anak kunci');
      expect(inspection.logs.length, 1);
      expect(inspection.logs.first.description, 'Inspeksi telah diselesaikan oleh pemeriksa');
    });
  });

  group('Inspection Screens Widget Tests', () {
    final sampleInspection = InspectionModel(
      id: 1,
      inspectionCode: 'INSP-20261015-001',
      propertyId: 1,
      roomId: 1,
      type: 'check_in',
      status: 'completed',
      scheduledDate: DateTime.now(),
      overallCondition: 'good',
      inspectorNotes: 'Kamar siap huni dan bersih',
      propertyName: 'Kos Melati Harmoni',
      roomNumber: 'Kamar 101',
      inspectorName: 'Budi Santoso',
      items: [
        InspectionItemModel(
          id: 1,
          inspectionId: 1,
          itemName: 'Kunci & Grendel Pintu',
          category: 'Pintu & Jendela',
          condition: 'good',
          notes: '2 anak kunci lengkap',
        ),
        InspectionItemModel(
          id: 2,
          inspectionId: 1,
          itemName: 'Pendingin Udara (AC)',
          category: 'Elektronik',
          condition: 'good',
          notes: 'Dingin dan normal',
        ),
      ],
    );

    testWidgets('InspectionsScreen renders tabs and inspection card', (WidgetTester tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inspectionsProvider.overrideWith((ref) async => [sampleInspection]),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const InspectionsScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Inspeksi & Checklist Kamar'), findsOneWidget);
      expect(find.text('Semua'), findsOneWidget);
      expect(find.text('Perlu Konfirmasi'), findsOneWidget);
      expect(find.text('INSP-20261015-001'), findsOneWidget);
      expect(find.text('Kos Melati Harmoni'), findsOneWidget);
      expect(find.text('Kamar 101'), findsOneWidget);
      expect(find.text('Menunggu konfirmasi persetujuan kondisi dari Anda.'), findsOneWidget);
    });

    testWidgets('InspectionDetailScreen displays checklist items and action buttons',
        (WidgetTester tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inspectionDetailProvider(1).overrideWith((ref) async => sampleInspection),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: InspectionDetailScreen(inspectionId: 1, initialInspection: sampleInspection),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('INSP-20261015-001'), findsOneWidget);
      expect(find.text('Checklist Kondisi Fasilitas'), findsOneWidget);
      expect(find.text('Kunci & Grendel Pintu'), findsOneWidget);
      expect(find.text('Pendingin Udara (AC)'), findsOneWidget);
      expect(find.text('Setujui Hasil'), findsOneWidget);
      expect(find.text('Ajukan Keberatan'), findsOneWidget);

      // Tap 'Setujui Hasil' to open dialog
      await tester.tap(find.text('Setujui Hasil'));
      await tester.pumpAndSettle();

      expect(find.text('Setujui Hasil Inspeksi'), findsOneWidget);
      expect(find.text('Setujui Sekarang'), findsOneWidget);
      expect(find.text('Batal'), findsOneWidget);

      // Dismiss dialog
      await tester.tap(find.text('Batal'));
      await tester.pumpAndSettle();
    });

    testWidgets('InspectionCompareScreen displays degradation warning and diff list',
        (WidgetTester tester) async {
      final mockComparisonData = {
        'current_inspection': {
          'id': 2,
          'inspection_code': 'INSP-CHECKOUT-001',
          'type': 'check_out',
        },
        'baseline_inspection': {
          'id': 1,
          'inspection_code': 'INSP-CHECKIN-001',
          'type': 'check_in',
        },
        'has_degradation': true,
        'total_damage_cost': 250000,
        'differences': [
          {
            'item_name': 'Pendingin Udara (AC)',
            'category': 'Elektronik',
            'baseline_condition': 'good',
            'current_condition': 'damaged',
            'is_degraded': true,
            'repair_required': true,
            'estimated_cost': 250000,
            'current_notes': 'Bocor dan tidak dingin',
          }
        ],
      };

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            inspectionComparisonProvider((currentId: 2, baselineId: 1))
                .overrideWith((ref) async => mockComparisonData),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const InspectionCompareScreen(currentId: 2, baselineId: 1),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Perbandingan Kondisi Kamar'), findsOneWidget);
      expect(find.text('Terdeteksi Penurunan Kondisi / Kerusakan'), findsOneWidget);
      expect(find.text('Rp 250000'), findsOneWidget);
      expect(find.text('Pendingin Udara (AC)'), findsOneWidget);
      expect(find.text('Menurun'), findsOneWidget);
      expect(find.text('Catatan: Bocor dan tidak dingin'), findsOneWidget);
    });
  });
}
