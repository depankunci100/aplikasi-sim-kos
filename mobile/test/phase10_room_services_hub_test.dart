import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile/features/auth/models/user_model.dart';
import 'package:mobile/features/auth/providers/auth_provider.dart';
import 'package:mobile/features/housekeeping/models/housekeeping_model.dart';
import 'package:mobile/features/housekeeping/providers/housekeeping_provider.dart';
import 'package:mobile/features/housekeeping/screens/housekeeping_detail_screen.dart';
import 'package:mobile/features/inspections/providers/inspection_provider.dart';
import 'package:mobile/features/profile/screens/room_services_hub_screen.dart';

void main() {
  group('Room Services Hub & Housekeeping Tenant Decisions Tests', () {
    testWidgets('RoomServicesHubScreen renders title, service cards, and tabs', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            housekeepingListProvider.overrideWith((ref) async => []),
            inspectionsProvider.overrideWith((ref) async => []),
          ],
          child: const MaterialApp(
            home: RoomServicesHubScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Check title and banner
      expect(find.text('Layanan Kamar'), findsOneWidget);
      expect(find.text('Pilih Layanan Yang Dibutuhkan'), findsOneWidget);

      // Check service cards
      expect(find.text('Pembersihan Kamar'), findsOneWidget);
      expect(find.text('Inspeksi & Cek Fisik Kamar'), findsOneWidget);
      expect(find.text('Ajukan Pembersihan'), findsOneWidget);
      expect(find.text('Ajukan Inspeksi'), findsOneWidget);

      // Check tabs
      expect(find.text('Kebersihan'), findsOneWidget);
      expect(find.text('Inspeksi'), findsOneWidget);
    });

    testWidgets('HousekeepingDetailScreen renders tenant approval & revision buttons in pending_verification', (tester) async {
      final task = HousekeepingTaskModel(
        id: 99,
        taskNumber: 'HSK-20261018-099',
        propertyId: 1,
        roomId: 101,
        type: 'room_cleaning',
        priority: 'normal',
        status: 'pending_verification',
        scheduledDate: DateTime(2026, 10, 18),
        scheduledSlot: 'Pagi (08:00 - 11:00)',
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '101',
        assigneeName: 'Staf Agus',
        checklist: [
          HousekeepingChecklistItem(id: 1, task: 'Sapu dan pel lantai', isDone: true),
          HousekeepingChecklistItem(id: 2, task: 'Ganti sprei kasur', isDone: true),
        ],
        createdAt: DateTime(2026, 10, 18, 8),
      );

      final tenantUser = UserModel(
        id: 7,
        name: 'Rizky Penyewa',
        email: 'tenant@test.com',
        role: 'tenant',
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            authProvider.overrideWith((ref) => AuthNotifier(ref.watch(apiClientProvider))
              ..state = AuthState(user: tenantUser, token: 'fake_token')),
            housekeepingDetailProvider(99).overrideWith((ref) async => task),
          ],
          child: MaterialApp(
            home: HousekeepingDetailScreen(
              taskId: 99,
              initialTask: task,
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Should display information notice for tenant
      expect(find.textContaining('Staf telah menyelesaikan pembersihan'), findsOneWidget);

      // Should display both tenant decision action buttons
      expect(find.text('Minta Perbaikan'), findsOneWidget);
      expect(find.text('Konfirmasi Selesai'), findsOneWidget);
    });
  });
}
