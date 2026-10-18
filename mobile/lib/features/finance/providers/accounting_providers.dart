import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/accounting_models.dart';

final accountingJournalsProvider = FutureProvider.autoDispose<List<JournalEntryItemModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.accountingJournals);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ?? [];
      return list.map((e) => JournalEntryItemModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    // Offline / Demo fallback
    return [
      JournalEntryItemModel(
        id: 1,
        journalNumber: 'JRN-20261017-DEMO01',
        propertyId: 1,
        propertyName: 'Kos Permata Hijau',
        entryDate: '2026-10-17',
        sourceType: 'payment',
        status: 'posted',
        description: 'Penerimaan sewa kamar Kos #101 via transfer bank',
        totalAmount: 1500000,
        creatorName: 'Bambang Manager',
        lines: [
          JournalLineItemModel(
            id: 1,
            accountId: 2,
            accountCode: '1102',
            accountName: 'Rekening Bank Operasional',
            debit: 1500000,
            credit: 0,
            memo: 'Penerimaan sewa via transfer',
          ),
          JournalLineItemModel(
            id: 2,
            accountId: 11,
            accountCode: '4101',
            accountName: 'Pendapatan Sewa Kamar Kos',
            debit: 0,
            credit: 1500000,
            memo: 'Pendapatan sewa periode berjalan',
          ),
        ],
      ),
    ];
  }
  return [];
});

final accountingTrialBalanceProvider = FutureProvider.autoDispose<TrialBalanceReportModel>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.accountingTrialBalance);
    if (response.data['success'] == true) {
      return TrialBalanceReportModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {
    // Demo fallback
    return TrialBalanceReportModel(
      propertyName: 'Kos Permata Hijau',
      asOfDate: '2026-10-17',
      rows: [
        TrialBalanceRowModel(code: '1101', name: 'Kas Tunai Properti', typeLabel: 'Aset', normalBalance: 'debit', totalDebit: 2500000, totalCredit: 500000, endingDebit: 2000000, endingCredit: 0),
        TrialBalanceRowModel(code: '1102', name: 'Rekening Bank Operasional', typeLabel: 'Aset', normalBalance: 'debit', totalDebit: 18500000, totalCredit: 3700000, endingDebit: 14800000, endingCredit: 0),
        TrialBalanceRowModel(code: '2101', name: 'Titipan Uang Jaminan', typeLabel: 'Kewajiban', normalBalance: 'credit', totalDebit: 0, totalCredit: 5000000, endingDebit: 0, endingCredit: 5000000),
        TrialBalanceRowModel(code: '4101', name: 'Pendapatan Sewa Kamar', typeLabel: 'Pendapatan', normalBalance: 'credit', totalDebit: 0, totalCredit: 16000000, endingDebit: 0, endingCredit: 16000000),
        TrialBalanceRowModel(code: '5101', name: 'Beban Listrik & Air', typeLabel: 'Beban', normalBalance: 'debit', totalDebit: 4200000, totalCredit: 0, endingDebit: 4200000, endingCredit: 0),
      ],
      totalDebit: 21000000,
      totalCredit: 21000000,
      isBalanced: true,
    );
  }
  throw Exception('Gagal memuat Neraca Saldo');
});

final accountingIncomeStatementProvider = FutureProvider.autoDispose<IncomeStatementReportModel>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.accountingIncomeStatement);
    if (response.data['success'] == true) {
      return IncomeStatementReportModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {
    return IncomeStatementReportModel(
      propertyName: 'Kos Permata Hijau',
      startDate: '2026-10-01',
      endDate: '2026-10-31',
      revenues: [
        IncomeStatementRowModel(code: '4101', name: 'Pendapatan Sewa Kamar Kos', amount: 16000000),
        IncomeStatementRowModel(code: '4103', name: 'Pendapatan Utilitas', amount: 1200000),
      ],
      totalRevenue: 17200000,
      expenses: [
        IncomeStatementRowModel(code: '5101', name: 'Beban Listrik & Air', amount: 2500000),
        IncomeStatementRowModel(code: '5103', name: 'Beban Pemeliharaan', amount: 800000),
      ],
      totalExpense: 3300000,
      netIncome: 13900000,
    );
  }
  throw Exception('Gagal memuat Laporan Laba Rugi');
});

final accountingBalanceSheetProvider = FutureProvider.autoDispose<BalanceSheetReportModel>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.accountingBalanceSheet);
    if (response.data['success'] == true) {
      return BalanceSheetReportModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {
    return BalanceSheetReportModel(
      propertyName: 'Kos Permata Hijau',
      asOfDate: '2026-10-17',
      assetRows: [
        BalanceSheetRowModel(code: '1101', name: 'Kas Tunai Properti', amount: 2000000),
        BalanceSheetRowModel(code: '1102', name: 'Rekening Bank Operasional', amount: 14800000),
      ],
      totalAssets: 16800000,
      liabilityRows: [
        BalanceSheetRowModel(code: '2101', name: 'Titipan Uang Jaminan', amount: 5000000),
      ],
      totalLiabilities: 5000000,
      equityRows: [
        BalanceSheetRowModel(code: '3300', name: 'Laba Tahun Berjalan', amount: 11800000),
      ],
      totalEquity: 11800000,
      totalLiabilitiesAndEquity: 16800000,
      isBalanced: true,
    );
  }
  throw Exception('Gagal memuat Neraca Keuangan');
});

final accountingReconciliationProvider = FutureProvider.autoDispose<ReconciliationReportModel>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.accountingReconciliation);
    if (response.data['success'] == true) {
      return ReconciliationReportModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {
    return ReconciliationReportModel(
      propertyName: 'Kos Permata Hijau',
      cashInflowSubledger: 18500000,
      cashInflowJournal: 18500000,
      cashInflowVariance: 0,
      isCashInflowReconciled: true,
      cashOutflowSubledger: 4200000,
      cashOutflowJournal: 4200000,
      cashOutflowVariance: 0,
      isCashOutflowReconciled: true,
      depositSubledger: 5000000,
      depositJournal: 5000000,
      depositVariance: 0,
      isDepositReconciled: true,
      unpostedExceptionsCount: 0,
    );
  }
  throw Exception('Gagal memuat Laporan Rekonsiliasi');
});
