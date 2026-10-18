class DepositLedgerModel {
  final int id;
  final String depositCode;
  final int bookingId;
  final String? bookingCode;
  final int propertyId;
  final String propertyName;
  final int? tenantId;
  final String tenantName;
  final String roomNumber;
  final double initialDepositAmount;
  final double totalReceived;
  final double totalDeductions;
  final double totalRefunded;
  final double currentBalance;
  final String status;
  final String? notes;
  final List<DepositMutationModel> mutations;

  DepositLedgerModel({
    required this.id,
    required this.depositCode,
    required this.bookingId,
    this.bookingCode,
    required this.propertyId,
    required this.propertyName,
    this.tenantId,
    required this.tenantName,
    required this.roomNumber,
    required this.initialDepositAmount,
    required this.totalReceived,
    required this.totalDeductions,
    required this.totalRefunded,
    required this.currentBalance,
    required this.status,
    this.notes,
    this.mutations = const [],
  });

  factory DepositLedgerModel.fromJson(Map<String, dynamic> json) {
    final booking = json['booking'] as Map<String, dynamic>?;
    final property = json['property'] as Map<String, dynamic>?;
    final tenant = json['tenant'] as Map<String, dynamic>?;
    final room = booking?['room'] as Map<String, dynamic>?;

    final mutationsList = (json['mutations'] as List<dynamic>?)
            ?.map((m) => DepositMutationModel.fromJson(m as Map<String, dynamic>))
            .toList() ??
        [];

    return DepositLedgerModel(
      id: json['id'] as int? ?? 0,
      depositCode: json['deposit_code'] as String? ?? '',
      bookingId: json['booking_id'] as int? ?? 0,
      bookingCode: booking?['booking_code'] as String?,
      propertyId: json['property_id'] as int? ?? 0,
      propertyName: property?['name'] as String? ?? 'Properti',
      tenantId: json['tenant_id'] as int?,
      tenantName: tenant?['name'] as String? ?? booking?['guest_name'] as String? ?? 'Penyewa',
      roomNumber: room?['room_number'] as String? ?? '-',
      initialDepositAmount: (json['initial_deposit_amount'] as num?)?.toDouble() ?? 0.0,
      totalReceived: (json['total_received'] as num?)?.toDouble() ?? 0.0,
      totalDeductions: (json['total_deductions'] as num?)?.toDouble() ?? 0.0,
      totalRefunded: (json['total_refunded'] as num?)?.toDouble() ?? 0.0,
      currentBalance: (json['current_balance'] as num?)?.toDouble() ?? 0.0,
      status: json['status'] as String? ?? 'pending',
      notes: json['notes'] as String?,
      mutations: mutationsList,
    );
  }

  String get statusLabel {
    switch (status) {
      case 'held':
        return 'Ditahan (Aktif)';
      case 'partially_refunded':
        return 'Dikembalikan Sebagian';
      case 'refunded':
        return 'Dikembalikan Penuh';
      case 'settled':
        return 'Selesai (Settled)';
      case 'forfeited':
        return 'Hangus / Disita';
      case 'pending':
      default:
        return 'Menunggu Pembayaran';
    }
  }
}

class DepositMutationModel {
  final int id;
  final String mutationCode;
  final String transactionType;
  final String direction;
  final double amount;
  final double balanceBefore;
  final double balanceAfter;
  final String? paymentMethod;
  final String? paymentReference;
  final String? notes;
  final String performerName;
  final DateTime createdAt;

  DepositMutationModel({
    required this.id,
    required this.mutationCode,
    required this.transactionType,
    required this.direction,
    required this.amount,
    required this.balanceBefore,
    required this.balanceAfter,
    this.paymentMethod,
    this.paymentReference,
    this.notes,
    required this.performerName,
    required this.createdAt,
  });

  factory DepositMutationModel.fromJson(Map<String, dynamic> json) {
    final performer = json['performer'] as Map<String, dynamic>?;

    return DepositMutationModel(
      id: json['id'] as int? ?? 0,
      mutationCode: json['mutation_code'] as String? ?? '',
      transactionType: json['transaction_type'] as String? ?? '',
      direction: json['direction'] as String? ?? 'in',
      amount: (json['amount'] as num?)?.toDouble() ?? 0.0,
      balanceBefore: (json['balance_before'] as num?)?.toDouble() ?? 0.0,
      balanceAfter: (json['balance_after'] as num?)?.toDouble() ?? 0.0,
      paymentMethod: json['payment_method'] as String?,
      paymentReference: json['payment_reference'] as String?,
      notes: json['notes'] as String?,
      performerName: performer?['name'] as String? ?? 'Sistem',
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at'] as String) : DateTime.now(),
    );
  }

  String get typeLabel {
    switch (transactionType) {
      case 'deposit_received':
        return 'Penerimaan Deposit';
      case 'damage_deduction':
        return 'Potongan Kerusakan';
      case 'unpaid_bill_deduction':
        return 'Potongan Tunggakan';
      case 'refund_payout':
        return 'Pengembalian Deposit';
      case 'adjustment_credit':
        return 'Koreksi Kredit (+ Penambahan)';
      case 'adjustment_debit':
        return 'Koreksi Debit (- Pengurangan)';
      default:
        return transactionType;
    }
  }
}

class CheckOutSettlementModel {
  final int id;
  final String settlementCode;
  final int bookingId;
  final String? bookingCode;
  final int propertyId;
  final String propertyName;
  final int? tenantId;
  final String tenantName;
  final String roomNumber;
  final DateTime checkOutDate;
  final String status;
  final double depositBalanceBefore;
  final double totalDeductions;
  final double netRefundAmount;
  final String? tenantObjectionNotes;
  final String? resolutionNotes;
  final String? refundMethod;
  final String? refundDestination;
  final String? refundPaymentReference;
  final List<SettlementDeductionModel> deductions;

  CheckOutSettlementModel({
    required this.id,
    required this.settlementCode,
    required this.bookingId,
    this.bookingCode,
    required this.propertyId,
    required this.propertyName,
    this.tenantId,
    required this.tenantName,
    required this.roomNumber,
    required this.checkOutDate,
    required this.status,
    required this.depositBalanceBefore,
    required this.totalDeductions,
    required this.netRefundAmount,
    this.tenantObjectionNotes,
    this.resolutionNotes,
    this.refundMethod,
    this.refundDestination,
    this.refundPaymentReference,
    this.deductions = const [],
  });

  factory CheckOutSettlementModel.fromJson(Map<String, dynamic> json) {
    final booking = json['booking'] as Map<String, dynamic>?;
    final property = json['property'] as Map<String, dynamic>?;
    final tenant = json['tenant'] as Map<String, dynamic>?;
    final room = booking?['room'] as Map<String, dynamic>?;

    final deductionsList = (json['deductions'] as List<dynamic>?)
            ?.map((d) => SettlementDeductionModel.fromJson(d as Map<String, dynamic>))
            .toList() ??
        [];

    return CheckOutSettlementModel(
      id: json['id'] as int? ?? 0,
      settlementCode: json['settlement_code'] as String? ?? '',
      bookingId: json['booking_id'] as int? ?? 0,
      bookingCode: booking?['booking_code'] as String?,
      propertyId: json['property_id'] as int? ?? 0,
      propertyName: property?['name'] as String? ?? 'Properti',
      tenantId: json['tenant_id'] as int?,
      tenantName: tenant?['name'] as String? ?? booking?['guest_name'] as String? ?? 'Penyewa',
      roomNumber: room?['room_number'] as String? ?? '-',
      checkOutDate: json['check_out_date'] != null ? DateTime.parse(json['check_out_date'] as String) : DateTime.now(),
      status: json['status'] as String? ?? 'draft',
      depositBalanceBefore: (json['deposit_balance_before'] as num?)?.toDouble() ?? 0.0,
      totalDeductions: (json['total_deductions'] as num?)?.toDouble() ?? 0.0,
      netRefundAmount: (json['net_refund_amount'] as num?)?.toDouble() ?? 0.0,
      tenantObjectionNotes: json['tenant_objection_notes'] as String?,
      resolutionNotes: json['resolution_notes'] as String?,
      refundMethod: json['refund_method'] as String?,
      refundDestination: json['refund_destination'] as String?,
      refundPaymentReference: json['refund_payment_reference'] as String?,
      deductions: deductionsList,
    );
  }

  String get statusLabel {
    switch (status) {
      case 'pending_tenant_approval':
        return 'Menunggu Persetujuan Penyewa';
      case 'disputed':
        return 'Disengketakan (Dispute)';
      case 'approved':
        return 'Disetujui (Siap Refund)';
      case 'settled':
        return 'Selesai (Refund Dibayar)';
      case 'cancelled':
        return 'Dibatalkan';
      case 'draft':
      default:
        return 'Konsep (Draft)';
    }
  }
}

class SettlementDeductionModel {
  final int id;
  final String title;
  final String? description;
  final double proposedAmount;
  final double approvedAmount;
  final bool isApproved;
  final bool isDisputed;
  final String? disputeReason;

  SettlementDeductionModel({
    required this.id,
    required this.title,
    this.description,
    required this.proposedAmount,
    required this.approvedAmount,
    required this.isApproved,
    required this.isDisputed,
    this.disputeReason,
  });

  factory SettlementDeductionModel.fromJson(Map<String, dynamic> json) {
    return SettlementDeductionModel(
      id: json['id'] as int? ?? 0,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      proposedAmount: (json['proposed_amount'] as num?)?.toDouble() ?? 0.0,
      approvedAmount: (json['approved_amount'] as num?)?.toDouble() ?? 0.0,
      isApproved: json['is_approved'] as bool? ?? true,
      isDisputed: json['is_disputed'] as bool? ?? false,
      disputeReason: json['dispute_reason'] as String?,
    );
  }
}
