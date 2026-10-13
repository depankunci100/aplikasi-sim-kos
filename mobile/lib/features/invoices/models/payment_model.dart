class PaymentModel {
  final int id;
  final String paymentCode;
  final int invoiceId;
  final num amount;
  final String paymentMethod;
  final String? paymentProvider;
  final String? senderName;
  final String? senderBank;
  final String? proofPhoto;
  final String status; // pending, verified, rejected
  final String? rejectionReason;
  final String? verificationNotes;
  final DateTime? createdAt;

  PaymentModel({
    required this.id,
    required this.paymentCode,
    required this.invoiceId,
    required this.amount,
    required this.paymentMethod,
    this.paymentProvider,
    this.senderName,
    this.senderBank,
    this.proofPhoto,
    required this.status,
    this.rejectionReason,
    this.verificationNotes,
    this.createdAt,
  });

  factory PaymentModel.fromJson(Map<String, dynamic> json) {
    return PaymentModel(
      id: json['id'] as int,
      paymentCode: json['payment_code'] as String? ?? '-',
      invoiceId: json['invoice_id'] as int? ?? 0,
      amount: num.tryParse(json['amount']?.toString() ?? '0') ?? 0,
      paymentMethod: json['payment_method'] as String? ?? 'manual_transfer',
      paymentProvider: json['payment_provider'] as String?,
      senderName: json['sender_name'] as String?,
      senderBank: json['sender_bank'] as String?,
      proofPhoto: json['proof_photo'] as String?,
      status: json['status'] as String? ?? 'pending',
      rejectionReason: json['rejection_reason'] as String?,
      verificationNotes: json['verification_notes'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
    );
  }

  String get statusLabel {
    switch (status) {
      case 'verified':
        return 'Sah / Diterima';
      case 'rejected':
        return 'Ditolak';
      case 'pending':
      default:
        return 'Menunggu Verifikasi';
    }
  }
}
