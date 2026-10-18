import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/finance/models/accounting_models.dart';
import 'package:mobile/features/finance/providers/accounting_providers.dart';
import 'package:mobile/features/finance/screens/accounting_hub_screen.dart';

void main() {
  group('Phase 8 Accounting Models Unit Tests', () {
    test('JournalEntryItemModel parses JSON and journal lines correctly', () {
      final json = {
        'id': 1,
        'journal_number': 'JRN-20261017-0001',
        'property_id': 1,
        'property': {'id': 1, 'name': 'Kos Permata'},
        'entry_date': '2026-10-17',
        'source_type': 'payment',
        'status': 'posted',
        'description': 'Penerimaan sewa kamar A101',
        'total_amount': 1500000.0,
        'creator': {'name': 'Manager Kos'},
        'lines': [
          {
            'id': 1,
            'account_id': 1,
            'debit': 1500000.0,
            'credit': 0.0,
            'memo': 'Kas Masuk',
            'account': {
              'id': 1,
              'code': '1101',
              'name': 'Kas Operasional',
            }
          },
          {
            'id': 2,
            'account_id': 10,
            'debit': 0.0,
            'credit': 1500000.0,
            'memo': 'Pendapatan Sewa',
            'account': {
              'id': 10,
              'code': '4101',
              'name': 'Pendapatan Sewa Kamar Kos',
            }
          }
        ]
      };

      final entry = JournalEntryItemModel.fromJson(json);

      expect(entry.id, 1);
      expect(entry.journalNumber, 'JRN-20261017-0001');
      expect(entry.totalAmount, 1500000.0);
      expect(entry.propertyName, 'Kos Permata');
      expect(entry.creatorName, 'Manager Kos');
      expect(entry.status, 'posted');
      expect(entry.lines.length, 2);
      expect(entry.lines.first.accountCode, '1101');
      expect(entry.lines.first.debit, 1500000.0);
      expect(entry.lines.last.accountCode, '4101');
      expect(entry.lines.last.credit, 1500000.0);
    });

    test('TrialBalanceReportModel parses debits, credits, and balance status correctly', () {
      final json = {
        'property': {'id': 1, 'name': 'Kos Permata'},
        'period': {'start_date': null, 'as_of_date': '2026-10-17'},
        'rows': [
          {
            'code': '1101',
            'name': 'Kas Operasional',
            'type_label': 'Aset',
            'normal_balance': 'debit',
            'total_debit': 2500000.0,
            'total_credit': 500000.0,
            'ending_debit': 2000000.0,
            'ending_credit': 0.0,
          },
          {
            'code': '4101',
            'name': 'Pendapatan Sewa Kamar Kos',
            'type_label': 'Pendapatan',
            'normal_balance': 'credit',
            'total_debit': 0.0,
            'total_credit': 2000000.0,
            'ending_debit': 0.0,
            'ending_credit': 2000000.0,
          }
        ],
        'summary': {
          'total_debit': 2000000.0,
          'total_credit': 2000000.0,
          'is_balanced': true,
          'difference': 0.0,
        }
      };

      final tb = TrialBalanceReportModel.fromJson(json);

      expect(tb.propertyName, 'Kos Permata');
      expect(tb.totalDebit, 2000000.0);
      expect(tb.totalCredit, 2000000.0);
      expect(tb.isBalanced, true);
      expect(tb.rows.length, 2);
      expect(tb.rows.first.code, '1101');
      expect(tb.rows.first.endingDebit, 2000000.0);
    });

    test('IncomeStatementReportModel calculates revenues, expenses, and net income', () {
      final json = {
        'property': {'id': 1, 'name': 'Kos Permata'},
        'period': {'start_date': '2026-10-01', 'end_date': '2026-10-31'},
        'revenues': [
          {'code': '4101', 'name': 'Sewa Kamar', 'amount': 10000000.0}
        ],
        'total_revenue': 10000000.0,
        'expenses': [
          {'code': '5101', 'name': 'Listrik & Air', 'amount': 2500000.0}
        ],
        'total_expense': 2500000.0,
        'net_income': 7500000.0,
      };

      final isModel = IncomeStatementReportModel.fromJson(json);

      expect(isModel.propertyName, 'Kos Permata');
      expect(isModel.totalRevenue, 10000000.0);
      expect(isModel.totalExpense, 2500000.0);
      expect(isModel.netIncome, 7500000.0);
      expect(isModel.revenues.length, 1);
      expect(isModel.expenses.length, 1);
      expect(isModel.revenues.first.code, '4101');
    });

    test('BalanceSheetReportModel parses assets, liabilities, and equity accurately', () {
      final json = {
        'property': {'id': 1, 'name': 'Kos Permata'},
        'as_of_date': '2026-10-17',
        'assets': {
          'rows': [
            {'code': '1101', 'name': 'Kas Tunai', 'amount': 5000000.0}
          ],
          'total': 5000000.0,
        },
        'liabilities': {
          'rows': [
            {'code': '2101', 'name': 'Uang Jaminan Penyewa', 'amount': 1000000.0}
          ],
          'total': 1000000.0,
        },
        'equity': {
          'rows': [
            {'code': '3101', 'name': 'Modal Pemilik', 'amount': 4000000.0}
          ],
          'total': 4000000.0,
        },
        'total_liabilities_and_equity': 5000000.0,
        'is_balanced': true,
        'difference': 0.0,
      };

      final bs = BalanceSheetReportModel.fromJson(json);

      expect(bs.propertyName, 'Kos Permata');
      expect(bs.totalAssets, 5000000.0);
      expect(bs.totalLiabilities, 1000000.0);
      expect(bs.totalEquity, 4000000.0);
      expect(bs.totalLiabilitiesAndEquity, 5000000.0);
      expect(bs.isBalanced, true);
    });

    test('ReconciliationReportModel parses 3 subledger pillars and variance correctly', () {
      final json = {
        'property': {'id': 1, 'name': 'Kos Permata'},
        'period': {'start_date': '2026-10-01', 'end_date': '2026-10-31'},
        'cash_inflow_reconciliation': {
          'subledger_verified_payments': 5000000.0,
          'journal_cash_debits': 5000000.0,
          'variance': 0.0,
          'is_reconciled': true,
        },
        'cash_outflow_reconciliation': {
          'subledger_expense_disbursements': 1200000.0,
          'journal_cash_credits': 1200000.0,
          'variance': 0.0,
          'is_reconciled': true,
        },
        'security_deposit_reconciliation': {
          'subledger_current_balance': 3000000.0,
          'journal_liability_balance': 3000000.0,
          'variance': 0.0,
          'is_reconciled': true,
        },
        'unposted_exceptions_count': 0,
      };

      final recon = ReconciliationReportModel.fromJson(json);

      expect(recon.isCashInflowReconciled, true);
      expect(recon.cashInflowSubledger, 5000000.0);
      expect(recon.isCashOutflowReconciled, true);
      expect(recon.isDepositReconciled, true);
      expect(recon.depositVariance, 0.0);
      expect(recon.unpostedExceptionsCount, 0);
    });
  });

  group('Phase 8 AccountingHubScreen Widget Tests', () {
    testWidgets('Renders AccountingHubScreen with TabBar and content',
        (WidgetTester tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            accountingJournalsProvider.overrideWith((ref) async => [
              JournalEntryItemModel(
                id: 1,
                journalNumber: 'JRN-20261017-0001',
                propertyId: 1,
                propertyName: 'Kos Permata',
                entryDate: '2026-10-17',
                sourceType: 'payment',
                status: 'posted',
                description: 'Penerimaan sewa kamar A101',
                totalAmount: 1500000,
                creatorName: 'Manager Kos',
                lines: [
                  JournalLineItemModel(
                    id: 1,
                    accountId: 1,
                    accountCode: '1101',
                    accountName: 'Kas Tunai',
                    debit: 1500000,
                    credit: 0,
                  ),
                  JournalLineItemModel(
                    id: 2,
                    accountId: 10,
                    accountCode: '4101',
                    accountName: 'Pendapatan Sewa',
                    debit: 0,
                    credit: 1500000,
                  ),
                ],
              ),
            ]),
            accountingTrialBalanceProvider.overrideWith((ref) async => TrialBalanceReportModel(
              propertyName: 'Kos Permata',
              asOfDate: '2026-10-17',
              rows: [],
              totalDebit: 1500000,
              totalCredit: 1500000,
              isBalanced: true,
            )),
            accountingIncomeStatementProvider.overrideWith((ref) async => IncomeStatementReportModel(
              propertyName: 'Kos Permata',
              startDate: '2026-10-01',
              endDate: '2026-10-31',
              revenues: [],
              totalRevenue: 1500000,
              expenses: [],
              totalExpense: 500000,
              netIncome: 1000000,
            )),
            accountingBalanceSheetProvider.overrideWith((ref) async => BalanceSheetReportModel(
              propertyName: 'Kos Permata',
              asOfDate: '2026-10-17',
              assetRows: [],
              totalAssets: 2000000,
              liabilityRows: [],
              totalLiabilities: 500000,
              equityRows: [],
              totalEquity: 1500000,
              totalLiabilitiesAndEquity: 2000000,
              isBalanced: true,
            )),
            accountingReconciliationProvider.overrideWith((ref) async => ReconciliationReportModel(
              propertyName: 'Kos Permata',
              cashInflowSubledger: 1500000,
              cashInflowJournal: 1500000,
              cashInflowVariance: 0,
              isCashInflowReconciled: true,
              cashOutflowSubledger: 500000,
              cashOutflowJournal: 500000,
              cashOutflowVariance: 0,
              isCashOutflowReconciled: true,
              depositSubledger: 500000,
              depositJournal: 500000,
              depositVariance: 0,
              isDepositReconciled: true,
              unpostedExceptionsCount: 0,
            )),
          ],
          child: MaterialApp(
            theme: AppTheme.lightTheme,
            home: const AccountingHubScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Check app bar title
      expect(find.text('Akuntansi & Jurnal'), findsOneWidget);

      // Check TabBar elements
      expect(find.text('Jurnal Umum'), findsOneWidget);
      expect(find.text('Laporan'), findsOneWidget);
      expect(find.text('Rekonsiliasi'), findsOneWidget);

      // In Jurnal Umum tab: journal rendered
      expect(find.text('JRN-20261017-0001'), findsOneWidget);
      expect(find.text('Penerimaan sewa kamar A101'), findsOneWidget);

      // Switch to Laporan tab
      await tester.tap(find.text('Laporan'));
      await tester.pumpAndSettle();

      // Check report tabs (Neraca Saldo, Laba Rugi, Neraca)
      expect(find.text('Neraca Saldo'), findsOneWidget);
      expect(find.text('Laba Rugi'), findsOneWidget);
      expect(find.text('Neraca'), findsOneWidget);

      // Switch to Rekonsiliasi tab
      await tester.tap(find.text('Rekonsiliasi'));
      await tester.pumpAndSettle();

      // Check 3 pillars cards rendered
      expect(find.text('Penerimaan Kas & Bank'), findsOneWidget);
      expect(find.text('Pengeluaran Kas & Bank'), findsOneWidget);
      expect(find.text('Kewajiban Uang Jaminan (Deposit)'), findsOneWidget);
    });
  });
}
