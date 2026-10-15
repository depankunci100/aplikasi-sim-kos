class CashFlowTransactionModel {
  final String type; // 'in' or 'out'
  final String date;
  final String reference;
  final String description;
  final String propertyName;
  final String method;
  final num amount;

  CashFlowTransactionModel({
    required this.type,
    required this.date,
    required this.reference,
    required this.description,
    required this.propertyName,
    required this.method,
    required this.amount,
  });

  factory CashFlowTransactionModel.fromJson(Map<String, dynamic> json) {
    return CashFlowTransactionModel(
      type: json['type'] as String? ?? 'in',
      date: json['date'] as String? ?? '',
      reference: json['reference'] as String? ?? '',
      description: json['description'] as String? ?? '',
      propertyName: json['property_name'] as String? ?? '-',
      method: json['method'] as String? ?? 'cash',
      amount: num.tryParse(json['amount']?.toString() ?? '0') ?? 0,
    );
  }
}

class ReceivableInvoiceModel {
  final int id;
  final String invoiceNumber;
  final String propertyName;
  final String tenantName;
  final String roomNumber;
  final String dueDate;
  final int daysOverdue;
  final String agingBucket;
  final num totalAmount;
  final num paidAmount;
  final num remainingBalance;

  ReceivableInvoiceModel({
    required this.id,
    required this.invoiceNumber,
    required this.propertyName,
    required this.tenantName,
    required this.roomNumber,
    required this.dueDate,
    required this.daysOverdue,
    required this.agingBucket,
    required this.totalAmount,
    required this.paidAmount,
    required this.remainingBalance,
  });

  factory ReceivableInvoiceModel.fromJson(Map<String, dynamic> json) {
    return ReceivableInvoiceModel(
      id: json['id'] as int? ?? 0,
      invoiceNumber: json['invoice_number'] as String? ?? '',
      propertyName: json['property_name'] as String? ?? '-',
      tenantName: json['tenant_name'] as String? ?? '-',
      roomNumber: json['room_number'] as String? ?? '-',
      dueDate: json['due_date'] as String? ?? '-',
      daysOverdue: int.tryParse(json['days_overdue']?.toString() ?? '0') ?? 0,
      agingBucket: json['aging_bucket'] as String? ?? '0 - 30 Hari',
      totalAmount: num.tryParse(json['total_amount']?.toString() ?? '0') ?? 0,
      paidAmount: num.tryParse(json['paid_amount']?.toString() ?? '0') ?? 0,
      remainingBalance: num.tryParse(json['remaining_balance']?.toString() ?? '0') ?? 0,
    );
  }
}
