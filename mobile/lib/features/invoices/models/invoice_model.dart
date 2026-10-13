import 'payment_model.dart';

class InvoiceModel {
  final int id;
  final String invoiceNumber;
  final int? propertyId;
  final int? bookingId;
  final String invoiceType;
  final num subtotal;
  final num discount;
  final num penalty;
  final num totalAmount;
  final num paidAmount;
  final num remainingBalance;
  final String status; // unpaid, partially_paid, paid, overdue, cancelled
  final DateTime? dueDate;
  final DateTime? paidAt;
  final DateTime? createdAt;
  final List<PaymentModel> payments;

  InvoiceModel({
    required this.id,
    required this.invoiceNumber,
    this.propertyId,
    this.bookingId,
    required this.invoiceType,
    required this.subtotal,
    this.discount = 0,
    this.penalty = 0,
    required this.totalAmount,
    required this.paidAmount,
    required this.remainingBalance,
    required this.status,
    this.dueDate,
    this.paidAt,
    this.createdAt,
    this.payments = const [],
  });

  factory InvoiceModel.fromJson(Map<String, dynamic> json) {
    final paymentsList = (json['payments'] as List<dynamic>?)
            ?.map((e) => PaymentModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final total = num.tryParse(json['total_amount']?.toString() ?? '0') ?? 0;
    final paid = num.tryParse(json['paid_amount']?.toString() ?? '0') ?? 0;
    final remaining = json['remaining_balance'] != null
        ? num.tryParse(json['remaining_balance'].toString()) ?? 0
        : (total - paid > 0 ? total - paid : 0);

    return InvoiceModel(
      id: json['id'] as int,
      invoiceNumber: json['invoice_number'] as String? ?? '-',
      propertyId: json['property_id'] as int?,
      bookingId: json['booking_id'] as int?,
      invoiceType: json['invoice_type'] as String? ?? 'rent',
      subtotal: num.tryParse(json['subtotal']?.toString() ?? '0') ?? 0,
      discount: num.tryParse(json['discount']?.toString() ?? '0') ?? 0,
      penalty: num.tryParse(json['penalty']?.toString() ?? '0') ?? 0,
      totalAmount: total,
      paidAmount: paid,
      remainingBalance: remaining,
      status: json['status'] as String? ?? 'unpaid',
      dueDate: json['due_date'] != null ? DateTime.tryParse(json['due_date'].toString()) : null,
      paidAt: json['paid_at'] != null ? DateTime.tryParse(json['paid_at'].toString()) : null,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
      payments: paymentsList,
    );
  }

  bool get isPaid => status == 'paid';

  String get statusLabel {
    switch (status) {
      case 'paid':
        return 'Lunas';
      case 'partially_paid':
        return 'Dibayar Sebagian';
      case 'overdue':
        return 'Jatuh Tempo';
      case 'unpaid':
      default:
        return 'Belum Bayar';
    }
  }
}
