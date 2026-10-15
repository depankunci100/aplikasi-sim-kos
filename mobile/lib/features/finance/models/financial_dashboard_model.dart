class MonthlyTrendItem {
  final String label;
  final num income;
  final num expense;

  MonthlyTrendItem({
    required this.label,
    required this.income,
    required this.expense,
  });

  factory MonthlyTrendItem.fromJson(Map<String, dynamic> json) {
    return MonthlyTrendItem(
      label: json['label'] as String? ?? json['month'] as String? ?? '',
      income: num.tryParse(json['income']?.toString() ?? json['cash_in']?.toString() ?? '0') ?? 0,
      expense: num.tryParse(json['expense']?.toString() ?? json['cash_out']?.toString() ?? '0') ?? 0,
    );
  }
}

class ExpenseCategoryShare {
  final String category;
  final num total;

  ExpenseCategoryShare({
    required this.category,
    required this.total,
  });

  factory ExpenseCategoryShare.fromJson(Map<String, dynamic> json) {
    return ExpenseCategoryShare(
      category: json['category'] as String? ?? 'other',
      total: num.tryParse(json['total']?.toString() ?? '0') ?? 0,
    );
  }
}

class FinancialDashboardModel {
  final num totalVerifiedReceipts;
  final num totalPaidExpenses;
  final num netCashFlow;
  final num totalReceivables;
  final int pendingExpensesCount;
  final num pendingExpensesAmount;
  final int unpaidInvoicesCount;
  final int activeBookingsCount;
  final int totalRooms;
  final int occupiedRooms;
  final num occupancyRate;
  final int incompleteMaintenance;
  final List<MonthlyTrendItem> chartTrend;
  final List<ExpenseCategoryShare> expenseCategories;

  FinancialDashboardModel({
    required this.totalVerifiedReceipts,
    required this.totalPaidExpenses,
    required this.netCashFlow,
    required this.totalReceivables,
    required this.pendingExpensesCount,
    required this.pendingExpensesAmount,
    required this.unpaidInvoicesCount,
    required this.activeBookingsCount,
    required this.totalRooms,
    required this.occupiedRooms,
    required this.occupancyRate,
    required this.incompleteMaintenance,
    this.chartTrend = const [],
    this.expenseCategories = const [],
  });

  factory FinancialDashboardModel.fromJson(Map<String, dynamic> json) {
    final kpis = json['kpis'] as Map<String, dynamic>? ?? {};

    final trendList = (json['chart_trend'] as List<dynamic>?)
            ?.map((e) => MonthlyTrendItem.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final catList = (json['expense_categories'] as List<dynamic>?)
            ?.map((e) => ExpenseCategoryShare.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    return FinancialDashboardModel(
      totalVerifiedReceipts: num.tryParse(kpis['total_verified_receipts']?.toString() ?? '0') ?? 0,
      totalPaidExpenses: num.tryParse(kpis['total_paid_expenses']?.toString() ?? '0') ?? 0,
      netCashFlow: num.tryParse(kpis['net_cash_flow']?.toString() ?? '0') ?? 0,
      totalReceivables: num.tryParse(kpis['total_receivables']?.toString() ?? '0') ?? 0,
      pendingExpensesCount: int.tryParse(kpis['pending_expenses_count']?.toString() ?? '0') ?? 0,
      pendingExpensesAmount: num.tryParse(kpis['pending_expenses_amount']?.toString() ?? '0') ?? 0,
      unpaidInvoicesCount: int.tryParse(kpis['unpaid_invoices_count']?.toString() ?? '0') ?? 0,
      activeBookingsCount: int.tryParse(kpis['active_bookings_count']?.toString() ?? '0') ?? 0,
      totalRooms: int.tryParse(kpis['total_rooms']?.toString() ?? '0') ?? 0,
      occupiedRooms: int.tryParse(kpis['occupied_rooms']?.toString() ?? '0') ?? 0,
      occupancyRate: num.tryParse(kpis['occupancy_rate']?.toString() ?? '0') ?? 0,
      incompleteMaintenance: int.tryParse(kpis['incomplete_maintenance']?.toString() ?? '0') ?? 0,
      chartTrend: trendList,
      expenseCategories: catList,
    );
  }
}
