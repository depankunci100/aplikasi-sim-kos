class InvoiceItemModel {
  final int id;
  final int invoiceId;
  final String itemType;
  final String name;
  final num quantity;
  final String? unit;
  final num rate;
  final num amount;
  final Map<String, dynamic>? metadata;

  InvoiceItemModel({
    required this.id,
    required this.invoiceId,
    required this.itemType,
    required this.name,
    required this.quantity,
    this.unit,
    required this.rate,
    required this.amount,
    this.metadata,
  });

  factory InvoiceItemModel.fromJson(Map<String, dynamic> json) {
    return InvoiceItemModel(
      id: json['id'] as int? ?? 0,
      invoiceId: json['invoice_id'] as int? ?? 0,
      itemType: json['item_type'] as String? ?? 'rent',
      name: json['name'] as String? ?? '-',
      quantity: num.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
      unit: json['unit'] as String?,
      rate: num.tryParse(json['rate']?.toString() ?? '0') ?? 0,
      amount: num.tryParse(json['amount']?.toString() ?? '0') ?? 0,
      metadata: json['metadata'] as Map<String, dynamic>?,
    );
  }
}
