import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/api_client.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/auth/models/user_model.dart';
import 'package:mobile/features/auth/providers/auth_provider.dart';
import 'package:mobile/features/invoices/models/invoice_model.dart';
import 'package:mobile/features/utilities/models/invoice_item_model.dart';
import 'package:mobile/features/utilities/models/utility_meter_model.dart';
import 'package:mobile/features/utilities/models/utility_reading_model.dart';
import 'package:mobile/features/utilities/providers/utility_provider.dart';
import 'package:mobile/features/utilities/screens/record_meter_screen.dart';
import 'package:mobile/features/utilities/screens/utility_meters_screen.dart';

class MockAuthNotifier extends AuthNotifier {
  MockAuthNotifier(UserModel user) : super(ApiClient()) {
    state = AuthState(user: user, token: 'mock_token');
  }
}

void main() {
  group('Utility Models Tests', () {
    test('UtilityMeterModel parses json correctly with properties and units', () {
      final json = {
        'id': 10,
        'property_id': 1,
        'room_id': 5,
        'utility_type': 'electricity',
        'meter_number': 'PLN-101-TEST',
        'meter_type': 'individual',
        'unit': 'kWh',
        'initial_reading': '1000.00',
        'current_reading': '1150.50',
        'is_active': true,
        'property': {'name': 'Kos Permata Harmoni'},
        'room': {'room_number': '101'},
      };

      final meter = UtilityMeterModel.fromJson(json);

      expect(meter.id, 10);
      expect(meter.meterNumber, 'PLN-101-TEST');
      expect(meter.utilityType, 'electricity');
      expect(meter.isElectricity, true);
      expect(meter.isWater, false);
      expect(meter.unit, 'kWh');
      expect(meter.initialReading, 1000.00);
      expect(meter.currentReading, 1150.50);
      expect(meter.propertyName, 'Kos Permata Harmoni');
      expect(meter.roomNumber, '101');
    });

    test('UtilityReadingModel parses json correctly with usage and status labels', () {
      final json = {
        'id': 55,
        'meter_id': 10,
        'property_id': 1,
        'room_id': 5,
        'utility_type': 'electricity',
        'previous_meter': '1000.00',
        'current_meter': '1075.50',
        'usage': '75.50',
        'rate_per_unit': '1500.00',
        'total_cost': '113250.00',
        'reading_date': '2026-10-15',
        'status': 'approved',
        'is_billed': true,
        'meter': {'meter_number': 'PLN-101-TEST', 'unit': 'kWh'},
        'room': {'room_number': '101'},
      };

      final reading = UtilityReadingModel.fromJson(json);

      expect(reading.id, 55);
      expect(reading.previousMeter, 1000.00);
      expect(reading.currentMeter, 1075.50);
      expect(reading.usage, 75.50);
      expect(reading.ratePerUnit, 1500.00);
      expect(reading.totalCost, 113250.00);
      expect(reading.status, 'approved');
      expect(reading.isApproved, true);
      expect(reading.isBilled, true);
      expect(reading.statusLabel, 'Disetujui');
    });

    test('InvoiceItemModel parses item breakdown and metadata correctly', () {
      final json = {
        'id': 99,
        'invoice_id': 12,
        'item_type': 'electricity',
        'name': 'Tagihan Listrik (Kamar 101)',
        'quantity': '80.00',
        'unit': 'kWh',
        'rate': '1500.00',
        'amount': '120000.00',
        'metadata': {
          'meter_number': 'PLN-101-TEST',
          'previous_meter': 1000.0,
          'current_meter': 1080.0,
        },
      };

      final item = InvoiceItemModel.fromJson(json);

      expect(item.id, 99);
      expect(item.itemType, 'electricity');
      expect(item.name, 'Tagihan Listrik (Kamar 101)');
      expect(item.quantity, 80.0);
      expect(item.unit, 'kWh');
      expect(item.rate, 1500.0);
      expect(item.amount, 120000.0);
      expect(item.metadata?['meter_number'], 'PLN-101-TEST');
    });

    test('InvoiceModel parses nested items correctly', () {
      final json = {
        'id': 12,
        'invoice_number': 'INV-UTL-20261015-001',
        'invoice_type': 'electricity',
        'subtotal': '120000.00',
        'total_amount': '120000.00',
        'paid_amount': '0.00',
        'remaining_balance': '120000.00',
        'status': 'unpaid',
        'items': [
          {
            'id': 99,
            'invoice_id': 12,
            'item_type': 'electricity',
            'name': 'Tagihan Listrik (Kamar 101)',
            'quantity': '80.00',
            'unit': 'kWh',
            'rate': '1500.00',
            'amount': '120000.00',
          }
        ],
      };

      final invoice = InvoiceModel.fromJson(json);

      expect(invoice.id, 12);
      expect(invoice.items.length, 1);
      expect(invoice.items.first.itemType, 'electricity');
      expect(invoice.items.first.quantity, 80.0);
    });
  });

  group('Utility Widgets Tests', () {
    testWidgets('UtilityMetersScreen renders staff tabs and meter cards', (tester) async {
      final staffUser = UserModel(
        id: 2,
        name: 'Staf Lapangan',
        email: 'staff@kos.com',
        role: 'staff',
      );

      final demoMeters = [
        UtilityMeterModel(
          id: 1,
          propertyId: 1,
          roomId: 1,
          utilityType: 'electricity',
          meterNumber: 'PLN-WIDGET-01',
          meterType: 'individual',
          unit: 'kWh',
          initialReading: 500.0,
          currentReading: 580.0,
          propertyName: 'Kos Permata',
          roomNumber: '101',
        ),
      ];

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            authProvider.overrideWith((ref) => MockAuthNotifier(staffUser)),
            utilityMetersProvider.overrideWith((ref) => Future.value(demoMeters)),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const UtilityMetersScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Utilitas & Meteran'), findsOneWidget);
      expect(find.text('Semua Meter'), findsOneWidget);
      expect(find.text('Listrik (kWh)'), findsOneWidget);
      expect(find.text('Air (m³)'), findsOneWidget);
      expect(find.text('PLN-WIDGET-01'), findsOneWidget);
      expect(find.text('Catat Nilai Meter'), findsOneWidget);
    });

    testWidgets('RecordMeterScreen enforces non-negative calculation & prevents backward reading', (tester) async {
      final meter = UtilityMeterModel(
        id: 1,
        propertyId: 1,
        roomId: 1,
        utilityType: 'electricity',
        meterNumber: 'PLN-TEST-RECORD',
        meterType: 'individual',
        unit: 'kWh',
        initialReading: 500.0,
        currentReading: 580.0,
        propertyName: 'Kos Permata',
        roomNumber: '101',
      );

      final staffUser = UserModel(
        id: 2,
        name: 'Staf Lapangan',
        email: 'staff@kos.com',
        role: 'staff',
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            authProvider.overrideWith((ref) => MockAuthNotifier(staffUser)),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: RecordMeterScreen(meter: meter),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Catat Nilai Meter'), findsOneWidget);
      expect(find.text('PLN-TEST-RECORD'), findsOneWidget);
      expect(find.text('580.00 kWh'), findsOneWidget);

      // Input nilai yang mundur / lebih kecil dari 580 (misal 550)
      final inputFinder = find.byType(TextFormField).first;
      await tester.enterText(inputFinder, '550');
      await tester.pumpAndSettle();

      // Harus muncul teks peringatan nilai lebih kecil
      expect(find.textContaining('lebih kecil dari pembacaan sebelumnya'), findsOneWidget);

      // Sekarang masukkan nilai yang benar (misal 620)
      await tester.enterText(inputFinder, '620');
      await tester.pumpAndSettle();

      // Harus menampilkan pemakaian terhitung 40.00 kWh (620 - 580)
      expect(find.textContaining('Pemakaian Terhitung: 40.00 kWh'), findsOneWidget);
    });
  });
}
