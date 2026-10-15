import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/finance/models/expense_model.dart';
import 'package:mobile/features/finance/models/financial_dashboard_model.dart';
import 'package:mobile/features/finance/models/financial_report_models.dart';
import 'package:mobile/features/finance/providers/expense_provider.dart';
import 'package:mobile/features/finance/providers/financial_dashboard_provider.dart';
import 'package:mobile/features/finance/screens/expense_list_screen.dart';
import 'package:mobile/features/finance/screens/financial_dashboard_screen.dart';
import 'package:mobile/features/finance/screens/financial_reports_screen.dart';

void main() {
  group('Phase 6 Financial Models Unit Tests', () {
    test('ExpenseModel parses JSON and calculates remaining balance correctly', () {
      final json = {
        'id': 100,
        'expense_number': 'EXP-20261016-0001',
        'property_id': 1,
        'title': 'Beli Lampu LED dan Kabel',
        'category': 'maintenance',
        'vendor_name': 'Toko Listrik Terang',
        'amount': 500000,
        'paid_amount': 200000,
        'remaining_balance': 300000,
        'expense_date': '2026-10-16',
        'approval_status': 'approved',
        'payment_status': 'partially_paid',
        'payment_method': 'cash',
        'property': {'name': 'Kos Permata'},
        'expense_category': {'name': 'Pemeliharaan'},
        'user': {'name': 'Agus Staff'},
        'approver': {'name': 'Budi Manager'},
        'payments': [
          {
            'id': 1,
            'payment_number': 'EXPPAY-001',
            'amount': 200000,
            'payment_date': '2026-10-16',
            'payment_method': 'cash',
          }
        ]
      };

      final expense = ExpenseModel.fromJson(json);

      expect(expense.id, 100);
      expect(expense.expenseNumber, 'EXP-20261016-0001');
      expect(expense.title, 'Beli Lampu LED dan Kabel');
      expect(expense.amount, 500000);
      expect(expense.paidAmount, 200000);
      expect(expense.remainingBalance, 300000);
      expect(expense.isApproved, true);
      expect(expense.isPartiallyPaid, true);
      expect(expense.payments.length, 1);
      expect(expense.payments.first.paymentNumber, 'EXPPAY-001');
    });

    test('FinancialDashboardModel parses KPIs, monthly trends, and categories correctly', () {
      final json = {
        'kpis': {
          'total_verified_receipts': 25000000,
          'total_paid_expenses': 6000000,
          'net_cash_flow': 19000000,
          'total_receivables': 4000000,
          'pending_expenses_count': 3,
          'pending_expenses_amount': 950000,
          'unpaid_invoices_count': 2,
          'active_bookings_count': 14,
          'total_rooms': 16,
          'occupied_rooms': 14,
          'occupancy_rate': 87.5,
          'incomplete_maintenance': 2,
        },
        'chart_trend': [
          {'label': 'Mei', 'income': 20000000, 'expense': 5000000},
          {'label': 'Jun', 'income': 25000000, 'expense': 6000000},
        ],
        'expense_categories': [
          {'category': 'electricity_water', 'total': 2500000},
          {'category': 'maintenance', 'total': 1500000},
        ],
      };

      final dash = FinancialDashboardModel.fromJson(json);

      expect(dash.totalVerifiedReceipts, 25000000);
      expect(dash.totalPaidExpenses, 6000000);
      expect(dash.netCashFlow, 19000000);
      expect(dash.totalReceivables, 4000000);
      expect(dash.occupancyRate, 87.5);
      expect(dash.chartTrend.length, 2);
      expect(dash.chartTrend.first.label, 'Mei');
      expect(dash.expenseCategories.length, 2);
    });

    test('CashFlowTransactionModel and ReceivableInvoiceModel parse JSON accurately', () {
      final cashFlowJson = {
        'type': 'in',
        'date': '2026-10-16 09:00:00',
        'reference': 'PAY-001',
        'description': 'Pembayaran Sewa Kamar',
        'property_name': 'Kos Permata',
        'method': 'qris',
        'amount': 1500000,
      };
      final cashFlow = CashFlowTransactionModel.fromJson(cashFlowJson);
      expect(cashFlow.type, 'in');
      expect(cashFlow.reference, 'PAY-001');
      expect(cashFlow.amount, 1500000);

      final recJson = {
        'id': 50,
        'invoice_number': 'INV-005',
        'property_name': 'Kos Permata',
        'tenant_name': 'Siti',
        'room_number': '101',
        'due_date': '2026-10-10',
        'days_overdue': 6,
        'aging_bucket': '0 - 30 Hari',
        'total_amount': 2000000,
        'paid_amount': 500000,
        'remaining_balance': 1500000,
      };
      final rec = ReceivableInvoiceModel.fromJson(recJson);
      expect(rec.invoiceNumber, 'INV-005');
      expect(rec.daysOverdue, 6);
      expect(rec.remainingBalance, 1500000);
    });
  });

  group('Phase 6 Flutter Widget Tests', () {
    testWidgets('FinancialDashboardScreen renders KPI cards and trend sections', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            financialDashboardProvider.overrideWith((ref) async {
              return FinancialDashboardModel(
                totalVerifiedReceipts: 20000000,
                totalPaidExpenses: 5000000,
                netCashFlow: 15000000,
                totalReceivables: 2000000,
                pendingExpensesCount: 1,
                pendingExpensesAmount: 300000,
                unpaidInvoicesCount: 1,
                activeBookingsCount: 10,
                totalRooms: 12,
                occupiedRooms: 10,
                occupancyRate: 83.3,
                incompleteMaintenance: 0,
              );
            }),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const FinancialDashboardScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Keuangan & Analitik'), findsOneWidget);
      expect(find.text('Kas Masuk'), findsOneWidget);
      expect(find.text('Kas Keluar'), findsOneWidget);
      expect(find.text('Arus Kas Bersih'), findsOneWidget);
      expect(find.text('Sisa Piutang'), findsOneWidget);
      expect(find.text('Pengeluaran'), findsOneWidget);
      expect(find.text('Laporan Kas'), findsOneWidget);
    });

    testWidgets('ExpenseListScreen renders tabs, cards, and floating action button', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            expenseListProvider.overrideWith((ref) async {
              return [
                ExpenseModel(
                  id: 1,
                  expenseNumber: 'EXP-TEST-01',
                  propertyId: 1,
                  propertyName: 'Kos Permata',
                  categoryName: 'Listrik & Air',
                  title: 'Token Listrik Kamar 101',
                  amount: 250000,
                  paidAmount: 250000,
                  remainingBalance: 0,
                  approvalStatus: 'approved',
                  paymentStatus: 'paid',
                ),
              ];
            }),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const ExpenseListScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Pengeluaran Operasional'), findsOneWidget);
      expect(find.text('Token Listrik Kamar 101'), findsOneWidget);
      expect(find.text('EXP-TEST-01'), findsOneWidget);
      expect(find.text('Catat Biaya'), findsOneWidget);
    });

    testWidgets('FinancialReportsScreen renders Cash Flow and Receivables tabs', (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            cashFlowTransactionsProvider.overrideWith((ref) async {
              return [
                CashFlowTransactionModel(
                  type: 'in',
                  date: '2026-10-16',
                  reference: 'PAY-001',
                  description: 'Pembayaran Sewa',
                  propertyName: 'Kos Permata',
                  method: 'bank_transfer',
                  amount: 2000000,
                ),
              ];
            }),
            receivablesListProvider.overrideWith((ref) async {
              return [
                ReceivableInvoiceModel(
                  id: 1,
                  invoiceNumber: 'INV-001',
                  propertyName: 'Kos Permata',
                  tenantName: 'Siti Rahma',
                  roomNumber: '101',
                  dueDate: '2026-10-10',
                  daysOverdue: 6,
                  agingBucket: '0 - 30 Hari',
                  totalAmount: 2000000,
                  paidAmount: 0,
                  remainingBalance: 2000000,
                ),
              ];
            }),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const FinancialReportsScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Laporan Finansial'), findsOneWidget);
      expect(find.text('Mutasi Arus Kas'), findsOneWidget);
      expect(find.text('Piutang & Aging'), findsOneWidget);
      expect(find.text('Pembayaran Sewa'), findsOneWidget);
    });
  });
}
