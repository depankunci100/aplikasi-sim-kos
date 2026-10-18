import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/deposit/models/deposit_model.dart';
import 'package:mobile/features/deposit/providers/deposit_provider.dart';
import 'package:mobile/features/deposit/screens/deposit_detail_screen.dart';
import 'package:mobile/features/deposit/screens/deposit_list_screen.dart';
import 'package:mobile/features/deposit/screens/settlement_detail_screen.dart';

void main() {
  group('Deposit & Settlement Models Unit Tests', () {
    test('DepositLedgerModel parses JSON and computes balance and status labels correctly', () {
      final json = {
        'id': 10,
        'deposit_code': 'DEP-20261017-0010',
        'booking_id': 101,
        'property_id': 1,
        'tenant_id': 5,
        'initial_deposit_amount': 1500000,
        'total_received': 1500000,
        'total_deductions': 300000,
        'total_refunded': 1200000,
        'current_balance': 0,
        'status': 'settled',
        'property': {'id': 1, 'name': 'Kos Melati Harmoni'},
        'booking': {
          'id': 101,
          'booking_code': 'BKG-20261017-001',
          'room': {'room_number': '102'}
        },
        'tenant': {'id': 5, 'name': 'Ahmad Fauzi', 'phone': '08123456789'},
      };

      final ledger = DepositLedgerModel.fromJson(json);

      expect(ledger.id, 10);
      expect(ledger.depositCode, 'DEP-20261017-0010');
      expect(ledger.bookingId, 101);
      expect(ledger.propertyName, 'Kos Melati Harmoni');
      expect(ledger.tenantName, 'Ahmad Fauzi');
      expect(ledger.roomNumber, '102');
      expect(ledger.initialDepositAmount, 1500000);
      expect(ledger.totalReceived, 1500000);
      expect(ledger.totalDeductions, 300000);
      expect(ledger.totalRefunded, 1200000);
      expect(ledger.currentBalance, 0);
      expect(ledger.status, 'settled');
      expect(ledger.statusLabel, 'Selesai (Settled)');
    });

    test('DepositMutationModel parses JSON correctly', () {
      final json = {
        'id': 50,
        'mutation_code': 'MUT-20261017-0050',
        'transaction_type': 'damage_deduction',
        'direction': 'out',
        'amount': 250000,
        'balance_before': 1500000,
        'balance_after': 1250000,
        'payment_method': 'internal_deduction',
        'payment_reference': 'SETTLE-20261017-001',
        'notes': 'Biaya penggantian handle pintu kamar rusak',
        'performer': {'name': 'Budi Manager'},
        'created_at': '2026-10-17T10:00:00.000Z',
      };

      final mutation = DepositMutationModel.fromJson(json);

      expect(mutation.id, 50);
      expect(mutation.mutationCode, 'MUT-20261017-0050');
      expect(mutation.transactionType, 'damage_deduction');
      expect(mutation.typeLabel, 'Potongan Kerusakan');
      expect(mutation.direction, 'out');
      expect(mutation.amount, 250000);
      expect(mutation.balanceBefore, 1500000);
      expect(mutation.balanceAfter, 1250000);
      expect(mutation.performerName, 'Budi Manager');
    });

    test('CheckOutSettlementModel and SettlementDeductionModel parse JSON accurately', () {
      final json = {
        'id': 20,
        'settlement_code': 'SETTLE-20261017-001',
        'property_id': 1,
        'booking_id': 101,
        'tenant_id': 5,
        'check_out_date': '2026-10-17',
        'deposit_balance_before': 1500000,
        'total_deductions': 200000,
        'net_refund_amount': 1300000,
        'refund_method': 'bank_transfer',
        'status': 'settled',
        'property': {'name': 'Kos Melati Harmoni'},
        'tenant': {'name': 'Ahmad Fauzi'},
        'booking': {
          'id': 101,
          'room': {'room_number': '204'}
        },
        'deductions': [
          {
            'id': 1,
            'title': 'Kaca Jendela Retak',
            'proposed_amount': 200000,
            'approved_amount': 200000,
            'is_approved': true,
            'is_disputed': false,
          }
        ]
      };

      final settlement = CheckOutSettlementModel.fromJson(json);

      expect(settlement.id, 20);
      expect(settlement.settlementCode, 'SETTLE-20261017-001');
      expect(settlement.tenantName, 'Ahmad Fauzi');
      expect(settlement.roomNumber, '204');
      expect(settlement.depositBalanceBefore, 1500000);
      expect(settlement.totalDeductions, 200000);
      expect(settlement.netRefundAmount, 1300000);
      expect(settlement.status, 'settled');
      expect(settlement.statusLabel, 'Selesai (Refund Dibayar)');
      expect(settlement.deductions.length, 1);
      expect(settlement.deductions.first.title, 'Kaca Jendela Retak');
      expect(settlement.deductions.first.isApproved, true);
      expect(settlement.deductions.first.isDisputed, false);
    });
  });

  group('Phase 7 Deposit Widgets Tests', () {
    testWidgets('DepositListScreen renders summary KPI cards and ledger list', (tester) async {
      final mockLedger = DepositLedgerModel(
        id: 1,
        depositCode: 'DEP-001',
        bookingId: 101,
        propertyId: 1,
        tenantId: 5,
        propertyName: 'Kos Melati Harmoni',
        tenantName: 'Ahmad Fauzi',
        roomNumber: '101',
        initialDepositAmount: 1500000,
        totalReceived: 1500000,
        totalDeductions: 0,
        totalRefunded: 0,
        currentBalance: 1500000,
        status: 'held',
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            depositListProvider.overrideWith((ref) async => [mockLedger]),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const DepositListScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Uang Jaminan (Deposit)'), findsOneWidget);
      expect(find.text('Ahmad Fauzi'), findsOneWidget);
      expect(find.text('Kos Melati Harmoni • Kamar 101'), findsOneWidget);
      expect(find.text('Ditahan (Aktif)'), findsOneWidget);
    });

    testWidgets('DepositDetailScreen renders balance cards and mutation history', (tester) async {
      final mockLedger = DepositLedgerModel(
        id: 1,
        depositCode: 'DEP-20261017-0001',
        bookingId: 101,
        propertyId: 1,
        tenantId: 5,
        propertyName: 'Kos Melati Harmoni',
        tenantName: 'Ahmad Fauzi',
        bookingCode: 'BKG-001',
        roomNumber: '101',
        initialDepositAmount: 1500000,
        totalReceived: 1500000,
        totalDeductions: 0,
        totalRefunded: 0,
        currentBalance: 1500000,
        status: 'held',
        mutations: [
          DepositMutationModel(
            id: 1,
            mutationCode: 'MUT-001',
            transactionType: 'deposit_received',
            direction: 'in',
            amount: 1500000,
            balanceBefore: 0,
            balanceAfter: 1500000,
            paymentMethod: 'bank_transfer',
            notes: 'Penerimaan uang deposit sewa via transfer',
            performerName: 'Resepsionis',
            createdAt: DateTime(2026, 10, 17, 9, 0),
          ),
        ],
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            depositDetailProvider(1).overrideWith((ref) async => mockLedger),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const DepositDetailScreen(depositId: 1),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Deposit #1'), findsOneWidget);
      expect(find.text('Ahmad Fauzi'), findsOneWidget);
      expect(find.text('Riwayat Mutasi Uang Jaminan'), findsOneWidget);
      expect(find.text('Penerimaan Deposit'), findsOneWidget);
      expect(find.textContaining('Penerimaan uang deposit sewa via transfer'), findsOneWidget);
    });

    testWidgets('SettlementDetailScreen renders deductions and check-out settlement details', (tester) async {
      final mockSettlement = CheckOutSettlementModel(
        id: 1,
        settlementCode: 'SETTLE-20261017-001',
        propertyId: 1,
        bookingId: 101,
        tenantId: 5,
        propertyName: 'Kos Melati Harmoni',
        tenantName: 'Ahmad Fauzi',
        roomNumber: '102',
        checkOutDate: DateTime(2026, 10, 17),
        depositBalanceBefore: 1500000,
        totalDeductions: 250000,
        netRefundAmount: 1250000,
        status: 'pending_tenant_approval',
        deductions: [
          SettlementDeductionModel(
            id: 1,
            title: 'Kran Wastafel Patah',
            proposedAmount: 250000,
            approvedAmount: 250000,
            isApproved: true,
            isDisputed: false,
          ),
        ],
      );

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            settlementDetailProvider(1).overrideWith((ref) async => mockSettlement),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const SettlementDetailScreen(settlementId: 1),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Settlement #1'), findsOneWidget);
      expect(find.text('SETTLE-20261017-001'), findsOneWidget);
      expect(find.text('Rekapitulasi Hak Pengembalian'), findsOneWidget);
      expect(find.text('Kran Wastafel Patah'), findsOneWidget);
      expect(find.text('Menunggu Persetujuan Penyewa'), findsOneWidget);
    });
  });
}
