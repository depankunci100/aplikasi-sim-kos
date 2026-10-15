class ExpenseCategoryModel {
  final int id;
  final String name;
  final String code;
  final String? description;
  final bool isActive;

  ExpenseCategoryModel({
    required this.id,
    required this.name,
    required this.code,
    this.description,
    this.isActive = true,
  });

  factory ExpenseCategoryModel.fromJson(Map<String, dynamic> json) {
    return ExpenseCategoryModel(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      code: json['code'] as String? ?? '',
      description: json['description'] as String?,
      isActive: json['is_active'] == true || json['is_active'] == 1,
    );
  }
}

class ExpensePaymentModel {
  final int id;
  final String paymentNumber;
  final num amount;
  final DateTime? paymentDate;
  final String paymentMethod;
  final String? proofFile;
  final String? notes;

  ExpensePaymentModel({
    required this.id,
    required this.paymentNumber,
    required this.amount,
    this.paymentDate,
    required this.paymentMethod,
    this.proofFile,
    this.notes,
  });

  factory ExpensePaymentModel.fromJson(Map<String, dynamic> json) {
    return ExpensePaymentModel(
      id: json['id'] as int? ?? 0,
      paymentNumber: json['payment_number'] as String? ?? '',
      amount: num.tryParse(json['amount']?.toString() ?? '0') ?? 0,
      paymentDate: json['payment_date'] != null ? DateTime.tryParse(json['payment_date'].toString()) : null,
      paymentMethod: json['payment_method'] as String? ?? 'cash',
      proofFile: json['proof_file'] as String?,
      notes: json['notes'] as String?,
    );
  }
}

class ExpenseModel {
  final int id;
  final String expenseNumber;
  final int propertyId;
  final String propertyName;
  final int? categoryId;
  final String categoryName;
  final String title;
  final String? vendorName;
  final num amount;
  final num paidAmount;
  final num remainingBalance;
  final DateTime? expenseDate;
  final String approvalStatus; // draft, submitted, approved, rejected
  final String paymentStatus;  // unpaid, partially_paid, paid
  final String paymentMethod;
  final String? receiptPhoto;
  final String? notes;
  final String? rejectionReason;
  final String? submitterName;
  final String? approverName;
  final List<ExpensePaymentModel> payments;

  ExpenseModel({
    required this.id,
    required this.expenseNumber,
    required this.propertyId,
    required this.propertyName,
    this.categoryId,
    required this.categoryName,
    required this.title,
    this.vendorName,
    required this.amount,
    required this.paidAmount,
    required this.remainingBalance,
    this.expenseDate,
    required this.approvalStatus,
    required this.paymentStatus,
    this.paymentMethod = 'cash',
    this.receiptPhoto,
    this.notes,
    this.rejectionReason,
    this.submitterName,
    this.approverName,
    this.payments = const [],
  });

  factory ExpenseModel.fromJson(Map<String, dynamic> json) {
    final amt = num.tryParse(json['amount']?.toString() ?? '0') ?? 0;
    final paid = num.tryParse(json['paid_amount']?.toString() ?? '0') ?? 0;
    final rem = json['remaining_balance'] != null
        ? num.tryParse(json['remaining_balance'].toString()) ?? (amt - paid > 0 ? amt - paid : 0)
        : (amt - paid > 0 ? amt - paid : 0);

    final pmts = (json['payments'] as List<dynamic>?)
            ?.map((e) => ExpensePaymentModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final prop = json['property'] as Map<String, dynamic>?;
    final cat = json['expense_category'] as Map<String, dynamic>?;
    final user = json['user'] as Map<String, dynamic>?;
    final approver = json['approver'] as Map<String, dynamic>?;

    return ExpenseModel(
      id: json['id'] as int? ?? 0,
      expenseNumber: json['expense_number'] as String? ?? 'EXP-${json['id']}',
      propertyId: json['property_id'] as int? ?? 0,
      propertyName: prop?['name'] as String? ?? 'Properti #${json['property_id']}',
      categoryId: json['expense_category_id'] as int?,
      categoryName: cat?['name'] as String? ?? (json['category'] as String? ?? 'Operasional'),
      title: json['title'] as String? ?? '',
      vendorName: json['vendor_name'] as String?,
      amount: amt,
      paidAmount: paid,
      remainingBalance: rem,
      expenseDate: json['expense_date'] != null ? DateTime.tryParse(json['expense_date'].toString()) : null,
      approvalStatus: json['approval_status'] as String? ?? 'draft',
      paymentStatus: json['payment_status'] as String? ?? 'unpaid',
      paymentMethod: json['payment_method'] as String? ?? 'cash',
      receiptPhoto: json['receipt_photo'] as String?,
      notes: json['notes'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      submitterName: user?['name'] as String?,
      approverName: approver?['name'] as String?,
      payments: pmts,
    );
  }

  bool get isDraft => approvalStatus == 'draft';
  bool get isSubmitted => approvalStatus == 'submitted';
  bool get isApproved => approvalStatus == 'approved';
  bool get isRejected => approvalStatus == 'rejected';
  bool get isPaid => paymentStatus == 'paid';
  bool get isPartiallyPaid => paymentStatus == 'partially_paid';
  bool get isUnpaid => paymentStatus == 'unpaid';
}
