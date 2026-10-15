import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/financial_dashboard_model.dart';
import '../models/financial_report_models.dart';

final financialDashboardProvider = FutureProvider.autoDispose<FinancialDashboardModel>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.financeDashboard);
    if (response.data['success'] == true) {
      return FinancialDashboardModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {
    // Offline fallback / demo
    return FinancialDashboardModel(
      totalVerifiedReceipts: 18500000,
      totalPaidExpenses: 4200000,
      netCashFlow: 14300000,
      totalReceivables: 3500000,
      pendingExpensesCount: 2,
      pendingExpensesAmount: 750000,
      unpaidInvoicesCount: 3,
      activeBookingsCount: 12,
      totalRooms: 15,
      occupiedRooms: 12,
      occupancyRate: 80.0,
      incompleteMaintenance: 1,
      chartTrend: [
        MonthlyTrendItem(label: 'Mei', income: 15000000, expense: 3800000),
        MonthlyTrendItem(label: 'Jun', income: 16500000, expense: 4100000),
        MonthlyTrendItem(label: 'Jul', income: 18000000, expense: 4500000),
        MonthlyTrendItem(label: 'Agu', income: 17500000, expense: 3900000),
        MonthlyTrendItem(label: 'Sep', income: 19000000, expense: 4200000),
        MonthlyTrendItem(label: 'Okt', income: 18500000, expense: 4200000),
      ],
      expenseCategories: [
        ExpenseCategoryShare(category: 'electricity_water', total: 1800000),
        ExpenseCategoryShare(category: 'salaries', total: 1500000),
        ExpenseCategoryShare(category: 'maintenance', total: 600000),
        ExpenseCategoryShare(category: 'supplies', total: 300000),
      ],
    );
  }
  throw Exception('Gagal memuat data dashboard keuangan');
});

final cashFlowTransactionsProvider = FutureProvider.autoDispose<List<CashFlowTransactionModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.reportsCashFlow);
    if (response.data['success'] == true) {
      final list = response.data['data']['transactions'] as List<dynamic>? ?? [];
      return list.map((e) => CashFlowTransactionModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    return [
      CashFlowTransactionModel(
        type: 'in',
        date: '2026-10-15 10:30:00',
        reference: 'PAY-20261015-001',
        description: 'Pembayaran Sewa Kamar 101 (Siti Rahma)',
        propertyName: 'Kos Permata Hijau',
        method: 'bank_transfer',
        amount: 2000000,
      ),
      CashFlowTransactionModel(
        type: 'out',
        date: '2026-10-15 14:15:00',
        reference: 'EXPPAY-20261015-001',
        description: 'Beli Token Listrik Utama PLN',
        propertyName: 'Kos Permata Hijau',
        method: 'cash',
        amount: 500000,
      ),
    ];
  }
  return [];
});

final receivablesListProvider = FutureProvider.autoDispose<List<ReceivableInvoiceModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.reportsReceivables);
    if (response.data['success'] == true) {
      final list = response.data['data']['invoices'] as List<dynamic>? ?? [];
      return list.map((e) => ReceivableInvoiceModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    return [
      ReceivableInvoiceModel(
        id: 1,
        invoiceNumber: 'INV-20261001-0005',
        propertyName: 'Kos Permata Hijau',
        tenantName: 'Doni Pratama',
        roomNumber: '102',
        dueDate: '2026-10-10',
        daysOverdue: 5,
        agingBucket: '0 - 30 Hari',
        totalAmount: 1800000,
        paidAmount: 0,
        remainingBalance: 1800000,
      ),
    ];
  }
  return [];
});
