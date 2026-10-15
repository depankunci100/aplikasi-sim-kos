import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/maintenance/models/maintenance_model.dart';
import 'package:mobile/features/maintenance/providers/maintenance_provider.dart';
import 'package:mobile/features/maintenance/screens/maintenance_detail_screen.dart';
import 'package:mobile/features/maintenance/screens/maintenance_list_screen.dart';
import 'package:mobile/features/complaints/models/complaint_model.dart';
import 'package:mobile/features/complaints/providers/complaint_provider.dart';
import 'package:mobile/features/complaints/screens/complaint_detail_screen.dart';
import 'package:mobile/features/complaints/screens/complaints_list_screen.dart';
import 'package:mobile/features/housekeeping/models/housekeeping_model.dart';
import 'package:mobile/features/housekeeping/providers/housekeeping_provider.dart';
import 'package:mobile/features/housekeeping/screens/housekeeping_detail_screen.dart';
import 'package:mobile/features/housekeeping/screens/housekeeping_list_screen.dart';

void main() {
  group('Phase 5 Models Unit Tests', () {
    test('MaintenanceRequestModel parses JSON and calculates costs and labels correctly', () {
      final json = {
        'id': 10,
        'ticket_number': 'MNT-20261015-00010',
        'property_id': 1,
        'room_id': 101,
        'reporter_id': 5,
        'category': 'ac',
        'priority': 'urgent',
        'status': 'in_progress',
        'title': 'AC Meledak dan Keluar Asap',
        'description': 'Tiba-tiba berbunyi keras dan mati total.',
        'labor_cost': 75000,
        'material_cost': 125000,
        'total_cost': 200000,
        'sla_hours': 6,
        'is_overdue': false,
        'property': {'name': 'Kos Melati'},
        'room': {'room_number': '101'},
        'reporter': {'name': 'Bambang'},
        'assignee': {'name': 'Teknisi Joko'},
        'spare_parts': ['Kapasitor AC 25uF', 'Kabel Power'],
      };

      final model = MaintenanceRequestModel.fromJson(json);

      expect(model.id, 10);
      expect(model.ticketNumber, 'MNT-20261015-00010');
      expect(model.categoryLabel, 'AC & Pendingin');
      expect(model.statusLabel, 'Dikerjakan');
      expect(model.laborCost, 75000.0);
      expect(model.materialCost, 125000.0);
      expect(model.totalCost, 200000.0);
      expect(model.slaHours, 6);
      expect(model.spareParts.length, 2);
      expect(model.spareParts.first, 'Kapasitor AC 25uF');
    });

    test('ComplaintModel parses JSON, comments, rating, and status labels correctly', () {
      final json = {
        'id': 20,
        'ticket_number': 'CMP-20261015-00020',
        'property_id': 1,
        'room_id': 202,
        'user_id': 8,
        'category': 'facility',
        'priority': 'normal',
        'status': 'closed',
        'title': 'Mesin Cuci Bersama Rusak',
        'description': 'Tabung tidak berputar saat mode spin.',
        'rating': 5,
        'tenant_feedback': 'Respons cepat sekali, mantap!',
        'property': {'name': 'Kos Alamanda'},
        'room': {'room_number': '202'},
        'user': {'name': 'Rina'},
        'comments': [
          {
            'id': 1,
            'comment': 'Teknisi servis akan datang besok pagi jam 9.',
            'is_internal': false,
            'user': {'name': 'Admin'},
          }
        ],
      };

      final model = ComplaintModel.fromJson(json);

      expect(model.id, 20);
      expect(model.ticketNumber, 'CMP-20261015-00020');
      expect(model.categoryLabel, 'Fasilitas');
      expect(model.statusLabel, 'Ditutup');
      expect(model.rating, 5);
      expect(model.tenantFeedback, 'Respons cepat sekali, mantap!');
      expect(model.comments.length, 1);
      expect(model.comments.first.comment, 'Teknisi servis akan datang besok pagi jam 9.');
    });

    test('HousekeepingTaskModel parses JSON and checklist items correctly', () {
      final json = {
        'id': 30,
        'task_number': 'HSK-20261015-00030',
        'property_id': 1,
        'room_id': 303,
        'type': 'room_cleaning',
        'priority': 'normal',
        'status': 'pending_verification',
        'scheduled_slot': 'Pagi (08:00 - 11:00)',
        'damaged_or_missing_items': 'Gelas mug retak 1 buah.',
        'property': {'name': 'Kos Cempaka'},
        'room': {'room_number': '303'},
        'assignee': {'name': 'Mbak Sri'},
        'checklist': [
          {'id': 'item_1', 'task': 'Sapu lantai', 'category': 'kamar', 'is_done': true},
          {'id': 'item_2', 'task': 'Pel lantai', 'category': 'kamar', 'is_done': true},
          {'id': 'item_3', 'task': 'Ganti sprei', 'category': 'tempat tidur', 'is_done': false},
        ],
      };

      final model = HousekeepingTaskModel.fromJson(json);

      expect(model.id, 30);
      expect(model.taskNumber, 'HSK-20261015-00030');
      expect(model.typeLabel, 'Pembersihan Kamar Rutin');
      expect(model.statusLabel, 'Menunggu Verifikasi');
      expect(model.checklist.length, 3);
      expect(model.checklist[0].isDone, true);
      expect(model.checklist[2].isDone, false);
      expect(model.damagedOrMissingItems, 'Gelas mug retak 1 buah.');

      final serializedItem = model.checklist[0].toJson();
      expect(serializedItem['task'], 'Sapu lantai');
      expect(serializedItem['is_done'], true);
    });
  });

  group('Phase 5 Widget Tests', () {
    testWidgets('MaintenanceListScreen renders tabs and maintenance cards', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            maintenanceListProvider.overrideWith((ref) async => [
                  MaintenanceRequestModel(
                    id: 1,
                    ticketNumber: 'MNT-20261015-00001',
                    propertyId: 1,
                    reporterId: 1,
                    category: 'ac',
                    priority: 'high',
                    status: 'in_progress',
                    title: 'AC Bocor dan Kurang Dingin',
                    description: 'Tetesan air membasahi lantai kamar tidur.',
                    roomNumber: '101',
                  ),
                ]),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const MaintenanceListScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Perbaikan & Maintenance'), findsOneWidget);
      expect(find.text('Diproses'), findsOneWidget);
      expect(find.text('Selesai'), findsOneWidget);
      expect(find.text('AC Bocor dan Kurang Dingin'), findsOneWidget);
      expect(find.text('MNT-20261015-00001'), findsOneWidget);
      expect(find.text('Lapor Kerusakan'), findsOneWidget);
    });

    testWidgets('ComplaintsListScreen renders tabs and complaint cards', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            complaintsListProvider.overrideWith((ref) async => [
                  ComplaintModel(
                    id: 1,
                    ticketNumber: 'CMP-20261015-00001',
                    propertyId: 1,
                    userId: 1,
                    category: 'room',
                    priority: 'high',
                    status: 'in_progress',
                    title: 'Kebocoran Plafon Kamar',
                    description: 'Tetesan air saat hujan lebat di atas meja belajar.',
                  ),
                ]),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const ComplaintsListScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Keluhan & Layanan'), findsOneWidget);
      expect(find.text('Aktif'), findsOneWidget);
      expect(find.text('Selesai'), findsOneWidget);
      expect(find.text('Kebocoran Plafon Kamar'), findsOneWidget);
      expect(find.text('CMP-20261015-00001'), findsOneWidget);
      expect(find.text('Buat Keluhan'), findsOneWidget);
    });

    testWidgets('HousekeepingListScreen renders tabs and housekeeping cards', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            housekeepingListProvider.overrideWith((ref) async => [
                  HousekeepingTaskModel(
                    id: 1,
                    taskNumber: 'HSK-20261015-00001',
                    propertyId: 1,
                    type: 'room_cleaning',
                    priority: 'normal',
                    status: 'in_progress',
                    roomNumber: '101',
                    checklist: [
                      HousekeepingChecklistItem(id: 1, task: 'Sapu lantai', isDone: true),
                      HousekeepingChecklistItem(id: 2, task: 'Pel lantai', isDone: false),
                    ],
                  ),
                ]),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const HousekeepingListScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Jadwal Kebersihan (Housekeeping)'), findsOneWidget);
      expect(find.text('Hari Ini'), findsOneWidget);
      expect(find.text('Selesai'), findsOneWidget);
      expect(find.text('Pembersihan Kamar Rutin'), findsOneWidget);
      expect(find.text('HSK-20261015-00001'), findsOneWidget);
      expect(find.text('1/2 Checklist'), findsOneWidget);
    });

    testWidgets('MaintenanceDetailScreen renders ticket details and status correctly', (tester) async {
      final mockItem = MaintenanceRequestModel(
        id: 99,
        ticketNumber: 'MNT-TEST-DETAIL',
        propertyId: 1,
        reporterId: 1,
        category: 'electricity',
        priority: 'urgent',
        status: 'pending_verification',
        title: 'Saklar Rusak',
        description: 'Saklar terbakar',
        laborCost: 40000,
        materialCost: 20000,
        totalCost: 60000,
        actionTaken: 'Diganti saklar baru',
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            maintenanceDetailProvider(99).overrideWith((ref) async => mockItem),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: MaintenanceDetailScreen(
              maintenanceId: 99,
              initialMaintenance: mockItem,
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('MNT-TEST-DETAIL'), findsWidgets);
      expect(find.text('Saklar Rusak'), findsOneWidget);
      expect(find.text('Total Biaya'), findsOneWidget);
      expect(find.text('Rp 60000'), findsOneWidget);
      expect(find.text('Tindakan Perbaikan'), findsOneWidget);
      expect(find.text('Verifikasi & Selesaikan'), findsOneWidget);
    });

    testWidgets('ComplaintDetailScreen renders complaint details correctly', (tester) async {
      final mockComplaint = ComplaintModel(
        id: 88,
        ticketNumber: 'CMP-TEST-DETAIL',
        propertyId: 1,
        userId: 1,
        category: 'room',
        priority: 'normal',
        status: 'resolved',
        title: 'Lampu Redup',
        description: 'Bohlam hampir putus',
        resolutionNotes: 'Bohlam telah diganti baru 15W',
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            complaintDetailProvider(88).overrideWith((ref) async => mockComplaint),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: ComplaintDetailScreen(
              complaintId: 88,
              initialComplaint: mockComplaint,
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('CMP-TEST-DETAIL'), findsWidgets);
      expect(find.text('Lampu Redup'), findsOneWidget);
      expect(find.text('Catatan Penyelesaian Petugas'), findsOneWidget);
      expect(find.text('Bohlam telah diganti baru 15W'), findsOneWidget);
      expect(find.text('Konfirmasi Selesai'), findsOneWidget);
      expect(find.text('Buka Kembali'), findsOneWidget);
    });

    testWidgets('HousekeepingDetailScreen renders checklist items correctly', (tester) async {
      final mockTask = HousekeepingTaskModel(
        id: 77,
        taskNumber: 'HSK-TEST-DETAIL',
        propertyId: 1,
        type: 'deep_cleaning',
        priority: 'normal',
        status: 'in_progress',
        checklist: [
          HousekeepingChecklistItem(id: 1, task: 'Sapu langit-langit', isDone: true),
          HousekeepingChecklistItem(id: 2, task: 'Cuci tirai jendela', isDone: false),
        ],
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            housekeepingDetailProvider(77).overrideWith((ref) async => mockTask),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: HousekeepingDetailScreen(
              taskId: 77,
              initialTask: mockTask,
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('HSK-TEST-DETAIL'), findsWidgets);
      expect(find.text('Pembersihan Menyeluruh (Deep)'), findsOneWidget);
      expect(find.text('Sapu langit-langit'), findsOneWidget);
      expect(find.text('Cuci tirai jendela'), findsOneWidget);
      expect(find.text('Serahkan untuk Verifikasi'), findsOneWidget);
    });
  });
}
