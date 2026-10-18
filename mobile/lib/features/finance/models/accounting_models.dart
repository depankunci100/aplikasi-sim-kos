class AccountItemModel {
  final int id;
  final int? propertyId;
  final String code;
  final String name;
  final String type;
  final String normalBalance;
  final String? category;
  final String? description;
  final bool isActive;
  final bool isSystem;

  AccountItemModel({
    required this.id,
    this.propertyId,
    required this.code,
    required this.name,
    required this.type,
    required this.normalBalance,
    this.category,
    this.description,
    required this.isActive,
    required this.isSystem,
  });

  factory AccountItemModel.fromJson(Map<String, dynamic> json) {
    return AccountItemModel(
      id: json['id'] as int? ?? 0,
      propertyId: json['property_id'] as int?,
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      type: json['type'] as String? ?? 'asset',
      normalBalance: json['normal_balance'] as String? ?? 'debit',
      category: json['category'] as String?,
      description: json['description'] as String?,
      isActive: json['is_active'] as bool? ?? true,
      isSystem: json['is_system'] as bool? ?? false,
    );
  }
}

class JournalLineItemModel {
  final int id;
  final int accountId;
  final String accountCode;
  final String accountName;
  final num debit;
  final num credit;
  final String? memo;

  JournalLineItemModel({
    required this.id,
    required this.accountId,
    required this.accountCode,
    required this.accountName,
    required this.debit,
    required this.credit,
    this.memo,
  });

  factory JournalLineItemModel.fromJson(Map<String, dynamic> json) {
    final acc = json['account'] as Map<String, dynamic>?;
    return JournalLineItemModel(
      id: json['id'] as int? ?? 0,
      accountId: json['account_id'] as int? ?? 0,
      accountCode: acc?['code'] as String? ?? '',
      accountName: acc?['name'] as String? ?? 'Akun #${json['account_id']}',
      debit: num.tryParse(json['debit']?.toString() ?? '0') ?? 0,
      credit: num.tryParse(json['credit']?.toString() ?? '0') ?? 0,
      memo: json['memo'] as String?,
    );
  }
}

class JournalEntryItemModel {
  final int id;
  final String journalNumber;
  final int propertyId;
  final String propertyName;
  final String entryDate;
  final String sourceType;
  final String status;
  final String description;
  final num totalAmount;
  final String creatorName;
  final List<JournalLineItemModel> lines;

  JournalEntryItemModel({
    required this.id,
    required this.journalNumber,
    required this.propertyId,
    required this.propertyName,
    required this.entryDate,
    required this.sourceType,
    required this.status,
    required this.description,
    required this.totalAmount,
    required this.creatorName,
    required this.lines,
  });

  factory JournalEntryItemModel.fromJson(Map<String, dynamic> json) {
    final prop = json['property'] as Map<String, dynamic>?;
    final creator = json['creator'] as Map<String, dynamic>?;
    final rawLines = json['lines'] as List<dynamic>? ?? [];

    return JournalEntryItemModel(
      id: json['id'] as int? ?? 0,
      journalNumber: json['journal_number'] as String? ?? '',
      propertyId: json['property_id'] as int? ?? 0,
      propertyName: prop?['name'] as String? ?? 'Properti #${json['property_id']}',
      entryDate: json['entry_date'] as String? ?? '',
      sourceType: json['source_type'] as String? ?? '',
      status: json['status'] as String? ?? 'posted',
      description: json['description'] as String? ?? '',
      totalAmount: num.tryParse(json['total_amount']?.toString() ?? '0') ?? 0,
      creatorName: creator?['name'] as String? ?? 'Sistem Otomatis',
      lines: rawLines.map((e) => JournalLineItemModel.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }
}

class TrialBalanceRowModel {
  final String code;
  final String name;
  final String typeLabel;
  final String normalBalance;
  final num totalDebit;
  final num totalCredit;
  final num endingDebit;
  final num endingCredit;

  TrialBalanceRowModel({
    required this.code,
    required this.name,
    required this.typeLabel,
    required this.normalBalance,
    required this.totalDebit,
    required this.totalCredit,
    required this.endingDebit,
    required this.endingCredit,
  });

  factory TrialBalanceRowModel.fromJson(Map<String, dynamic> json) {
    return TrialBalanceRowModel(
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      typeLabel: json['type_label'] as String? ?? '',
      normalBalance: json['normal_balance'] as String? ?? 'debit',
      totalDebit: num.tryParse(json['total_debit']?.toString() ?? '0') ?? 0,
      totalCredit: num.tryParse(json['total_credit']?.toString() ?? '0') ?? 0,
      endingDebit: num.tryParse(json['ending_debit']?.toString() ?? '0') ?? 0,
      endingCredit: num.tryParse(json['ending_credit']?.toString() ?? '0') ?? 0,
    );
  }
}

class TrialBalanceReportModel {
  final String propertyName;
  final String asOfDate;
  final List<TrialBalanceRowModel> rows;
  final num totalDebit;
  final num totalCredit;
  final bool isBalanced;

  TrialBalanceReportModel({
    required this.propertyName,
    required this.asOfDate,
    required this.rows,
    required this.totalDebit,
    required this.totalCredit,
    required this.isBalanced,
  });

  factory TrialBalanceReportModel.fromJson(Map<String, dynamic> json) {
    final prop = json['property'] as Map<String, dynamic>?;
    final period = json['period'] as Map<String, dynamic>?;
    final summary = json['summary'] as Map<String, dynamic>?;
    final rawRows = json['rows'] as List<dynamic>? ?? [];

    return TrialBalanceReportModel(
      propertyName: prop?['name'] as String? ?? '-',
      asOfDate: period?['as_of_date'] as String? ?? '',
      rows: rawRows.map((e) => TrialBalanceRowModel.fromJson(e as Map<String, dynamic>)).toList(),
      totalDebit: num.tryParse(summary?['total_debit']?.toString() ?? '0') ?? 0,
      totalCredit: num.tryParse(summary?['total_credit']?.toString() ?? '0') ?? 0,
      isBalanced: summary?['is_balanced'] as bool? ?? false,
    );
  }
}

class IncomeStatementRowModel {
  final String code;
  final String name;
  final num amount;

  IncomeStatementRowModel({
    required this.code,
    required this.name,
    required this.amount,
  });

  factory IncomeStatementRowModel.fromJson(Map<String, dynamic> json) {
    return IncomeStatementRowModel(
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      amount: num.tryParse(json['amount']?.toString() ?? '0') ?? 0,
    );
  }
}

class IncomeStatementReportModel {
  final String propertyName;
  final String startDate;
  final String endDate;
  final List<IncomeStatementRowModel> revenues;
  final num totalRevenue;
  final List<IncomeStatementRowModel> expenses;
  final num totalExpense;
  final num netIncome;

  IncomeStatementReportModel({
    required this.propertyName,
    required this.startDate,
    required this.endDate,
    required this.revenues,
    required this.totalRevenue,
    required this.expenses,
    required this.totalExpense,
    required this.netIncome,
  });

  factory IncomeStatementReportModel.fromJson(Map<String, dynamic> json) {
    final prop = json['property'] as Map<String, dynamic>?;
    final period = json['period'] as Map<String, dynamic>?;
    final rawRevs = json['revenues'] as List<dynamic>? ?? [];
    final rawExps = json['expenses'] as List<dynamic>? ?? [];

    return IncomeStatementReportModel(
      propertyName: prop?['name'] as String? ?? '-',
      startDate: period?['start_date'] as String? ?? '',
      endDate: period?['end_date'] as String? ?? '',
      revenues: rawRevs.map((e) => IncomeStatementRowModel.fromJson(e as Map<String, dynamic>)).toList(),
      totalRevenue: num.tryParse(json['total_revenue']?.toString() ?? '0') ?? 0,
      expenses: rawExps.map((e) => IncomeStatementRowModel.fromJson(e as Map<String, dynamic>)).toList(),
      totalExpense: num.tryParse(json['total_expense']?.toString() ?? '0') ?? 0,
      netIncome: num.tryParse(json['net_income']?.toString() ?? '0') ?? 0,
    );
  }
}

class BalanceSheetRowModel {
  final String code;
  final String name;
  final num amount;

  BalanceSheetRowModel({
    required this.code,
    required this.name,
    required this.amount,
  });

  factory BalanceSheetRowModel.fromJson(Map<String, dynamic> json) {
    return BalanceSheetRowModel(
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      amount: num.tryParse(json['amount']?.toString() ?? '0') ?? 0,
    );
  }
}

class BalanceSheetReportModel {
  final String propertyName;
  final String asOfDate;
  final List<BalanceSheetRowModel> assetRows;
  final num totalAssets;
  final List<BalanceSheetRowModel> liabilityRows;
  final num totalLiabilities;
  final List<BalanceSheetRowModel> equityRows;
  final num totalEquity;
  final num totalLiabilitiesAndEquity;
  final bool isBalanced;

  BalanceSheetReportModel({
    required this.propertyName,
    required this.asOfDate,
    required this.assetRows,
    required this.totalAssets,
    required this.liabilityRows,
    required this.totalLiabilities,
    required this.equityRows,
    required this.totalEquity,
    required this.totalLiabilitiesAndEquity,
    required this.isBalanced,
  });

  factory BalanceSheetReportModel.fromJson(Map<String, dynamic> json) {
    final prop = json['property'] as Map<String, dynamic>?;
    final assets = json['assets'] as Map<String, dynamic>?;
    final liabilities = json['liabilities'] as Map<String, dynamic>?;
    final equity = json['equity'] as Map<String, dynamic>?;

    final rawAssetRows = assets?['rows'] as List<dynamic>? ?? [];
    final rawLiabRows = liabilities?['rows'] as List<dynamic>? ?? [];
    final rawEqRows = equity?['rows'] as List<dynamic>? ?? [];

    return BalanceSheetReportModel(
      propertyName: prop?['name'] as String? ?? '-',
      asOfDate: json['as_of_date'] as String? ?? '',
      assetRows: rawAssetRows.map((e) => BalanceSheetRowModel.fromJson(e as Map<String, dynamic>)).toList(),
      totalAssets: num.tryParse(assets?['total']?.toString() ?? '0') ?? 0,
      liabilityRows: rawLiabRows.map((e) => BalanceSheetRowModel.fromJson(e as Map<String, dynamic>)).toList(),
      totalLiabilities: num.tryParse(liabilities?['total']?.toString() ?? '0') ?? 0,
      equityRows: rawEqRows.map((e) => BalanceSheetRowModel.fromJson(e as Map<String, dynamic>)).toList(),
      totalEquity: num.tryParse(equity?['total']?.toString() ?? '0') ?? 0,
      totalLiabilitiesAndEquity: num.tryParse(json['total_liabilities_and_equity']?.toString() ?? '0') ?? 0,
      isBalanced: json['is_balanced'] as bool? ?? false,
    );
  }
}

class ReconciliationReportModel {
  final String propertyName;
  final num cashInflowSubledger;
  final num cashInflowJournal;
  final num cashInflowVariance;
  final bool isCashInflowReconciled;
  final num cashOutflowSubledger;
  final num cashOutflowJournal;
  final num cashOutflowVariance;
  final bool isCashOutflowReconciled;
  final num depositSubledger;
  final num depositJournal;
  final num depositVariance;
  final bool isDepositReconciled;
  final int unpostedExceptionsCount;

  ReconciliationReportModel({
    required this.propertyName,
    required this.cashInflowSubledger,
    required this.cashInflowJournal,
    required this.cashInflowVariance,
    required this.isCashInflowReconciled,
    required this.cashOutflowSubledger,
    required this.cashOutflowJournal,
    required this.cashOutflowVariance,
    required this.isCashOutflowReconciled,
    required this.depositSubledger,
    required this.depositJournal,
    required this.depositVariance,
    required this.isDepositReconciled,
    required this.unpostedExceptionsCount,
  });

  factory ReconciliationReportModel.fromJson(Map<String, dynamic> json) {
    final prop = json['property'] as Map<String, dynamic>?;
    final inflow = json['cash_inflow_reconciliation'] as Map<String, dynamic>?;
    final outflow = json['cash_outflow_reconciliation'] as Map<String, dynamic>?;
    final deposit = json['security_deposit_reconciliation'] as Map<String, dynamic>?;

    return ReconciliationReportModel(
      propertyName: prop?['name'] as String? ?? '-',
      cashInflowSubledger: num.tryParse(inflow?['subledger_verified_payments']?.toString() ?? '0') ?? 0,
      cashInflowJournal: num.tryParse(inflow?['journal_cash_debits']?.toString() ?? '0') ?? 0,
      cashInflowVariance: num.tryParse(inflow?['variance']?.toString() ?? '0') ?? 0,
      isCashInflowReconciled: inflow?['is_reconciled'] as bool? ?? false,
      cashOutflowSubledger: num.tryParse(outflow?['subledger_expense_disbursements']?.toString() ?? '0') ?? 0,
      cashOutflowJournal: num.tryParse(outflow?['journal_cash_credits']?.toString() ?? '0') ?? 0,
      cashOutflowVariance: num.tryParse(outflow?['variance']?.toString() ?? '0') ?? 0,
      isCashOutflowReconciled: outflow?['is_reconciled'] as bool? ?? false,
      depositSubledger: num.tryParse(deposit?['subledger_current_balance']?.toString() ?? '0') ?? 0,
      depositJournal: num.tryParse(deposit?['journal_liability_balance']?.toString() ?? '0') ?? 0,
      depositVariance: num.tryParse(deposit?['variance']?.toString() ?? '0') ?? 0,
      isDepositReconciled: deposit?['is_reconciled'] as bool? ?? false,
      unpostedExceptionsCount: json['unposted_exceptions_count'] as int? ?? 0,
    );
  }
}
