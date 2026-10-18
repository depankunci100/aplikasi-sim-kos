import 'package:flutter/material.dart';

class PricingEstimateModel {
  final int days;
  final String rentalType;
  final num rateAmount;
  final num depositAmount;
  final num downPaymentAmount;
  final num totalAmount;
  final bool dpRequired;
  final String dpType;
  final int paymentDeadlineHours;

  PricingEstimateModel({
    required this.days,
    required this.rentalType,
    required this.rateAmount,
    required this.depositAmount,
    required this.downPaymentAmount,
    required this.totalAmount,
    this.dpRequired = false,
    this.dpType = 'fixed',
    this.paymentDeadlineHours = 24,
  });

  factory PricingEstimateModel.fromJson(Map<String, dynamic> json) {
    return PricingEstimateModel(
      days: json['days'] as int? ?? 1,
      rentalType: json['rental_type'] as String? ?? 'monthly',
      rateAmount: num.tryParse(json['rate_amount']?.toString() ?? '0') ?? 0,
      depositAmount: num.tryParse(json['deposit_amount']?.toString() ?? '0') ?? 0,
      downPaymentAmount: num.tryParse(json['down_payment_amount']?.toString() ?? '0') ?? 0,
      totalAmount: num.tryParse(json['total_amount']?.toString() ?? '0') ?? 0,
      dpRequired: json['dp_required'] == true || json['dp_required'] == 1,
      dpType: json['dp_type'] as String? ?? 'fixed',
      paymentDeadlineHours: json['payment_deadline_hours'] as int? ?? 24,
    );
  }
}

class BookingInvoiceModel {
  final int id;
  final String invoiceNumber;
  final num totalAmount;
  final num paidAmount;
  final String status;
  final DateTime? dueDate;

  BookingInvoiceModel({
    required this.id,
    required this.invoiceNumber,
    required this.totalAmount,
    required this.paidAmount,
    required this.status,
    this.dueDate,
  });

  factory BookingInvoiceModel.fromJson(Map<String, dynamic> json) {
    return BookingInvoiceModel(
      id: json['id'] as int? ?? 0,
      invoiceNumber: json['invoice_number'] as String? ?? '',
      totalAmount: num.tryParse(json['total_amount']?.toString() ?? '0') ?? 0,
      paidAmount: num.tryParse(json['paid_amount']?.toString() ?? '0') ?? 0,
      status: json['status'] as String? ?? 'unpaid',
      dueDate: json['due_date'] != null ? DateTime.tryParse(json['due_date'].toString()) : null,
    );
  }
}

class BookingModel {
  final int id;
  final String bookingCode;
  final int propertyId;
  final int roomId;
  final int? userId;
  final String? guestName;
  final String? guestEmail;
  final String? guestPhone;
  final String? guestIdCard;
  final String? accessToken;
  final String rentalType;
  final DateTime? startDate;
  final DateTime? endDate;
  final DateTime? actualCheckIn;
  final DateTime? actualCheckOut;
  final DateTime? expiresAt;
  final num rateAmount;
  final num depositAmount;
  final num downPaymentAmount;
  final num totalAmount;
  final String status;
  final String? notes;
  final String? rejectionReason;
  final String? cancellationReason;
  final String propertyName;
  final String? propertyAddress;
  final String roomNumber;
  final String roomTypeName;
  final String customerName;
  final List<BookingInvoiceModel> invoices;

  BookingModel({
    required this.id,
    required this.bookingCode,
    required this.propertyId,
    required this.roomId,
    this.userId,
    this.guestName,
    this.guestEmail,
    this.guestPhone,
    this.guestIdCard,
    this.accessToken,
    required this.rentalType,
    this.startDate,
    this.endDate,
    this.actualCheckIn,
    this.actualCheckOut,
    this.expiresAt,
    required this.rateAmount,
    required this.depositAmount,
    required this.downPaymentAmount,
    required this.totalAmount,
    required this.status,
    this.notes,
    this.rejectionReason,
    this.cancellationReason,
    this.propertyName = '',
    this.propertyAddress,
    this.roomNumber = '',
    this.roomTypeName = '',
    this.customerName = '',
    this.invoices = const [],
  });

  factory BookingModel.fromJson(Map<String, dynamic> json) {
    final property = json['property'] as Map<String, dynamic>?;
    final room = json['room'] as Map<String, dynamic>?;
    final roomType = room?['room_type'] as Map<String, dynamic>?;
    final user = json['user'] as Map<String, dynamic>?;

    final invoicesList = (json['invoices'] as List<dynamic>?)
            ?.map((e) => BookingInvoiceModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final guestName = json['guest_name'] as String?;
    final userName = user?['name'] as String?;
    final resolvedCustomerName = userName ?? (guestName ?? 'Tamu Publik');

    return BookingModel(
      id: json['id'] as int? ?? 0,
      bookingCode: json['booking_code'] as String? ?? '',
      propertyId: json['property_id'] as int? ?? (property?['id'] as int? ?? 0),
      roomId: json['room_id'] as int? ?? (room?['id'] as int? ?? 0),
      userId: json['user_id'] as int?,
      guestName: guestName,
      guestEmail: json['guest_email'] as String?,
      guestPhone: json['guest_phone'] as String?,
      guestIdCard: json['guest_id_card'] as String?,
      accessToken: json['access_token'] as String?,
      rentalType: json['rental_type'] as String? ?? 'monthly',
      startDate: json['start_date'] != null ? DateTime.tryParse(json['start_date'].toString()) : null,
      endDate: json['end_date'] != null ? DateTime.tryParse(json['end_date'].toString()) : null,
      actualCheckIn: json['actual_check_in'] != null ? DateTime.tryParse(json['actual_check_in'].toString()) : null,
      actualCheckOut: json['actual_check_out'] != null ? DateTime.tryParse(json['actual_check_out'].toString()) : null,
      expiresAt: json['expires_at'] != null ? DateTime.tryParse(json['expires_at'].toString()) : null,
      rateAmount: num.tryParse(json['rate_amount']?.toString() ?? '0') ?? 0,
      depositAmount: num.tryParse(json['deposit_amount']?.toString() ?? '0') ?? 0,
      downPaymentAmount: num.tryParse(json['down_payment_amount']?.toString() ?? '0') ?? 0,
      totalAmount: num.tryParse(json['total_amount']?.toString() ?? '0') ?? 0,
      status: json['status'] as String? ?? 'pending',
      notes: json['notes'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      cancellationReason: json['cancellation_reason'] as String?,
      propertyName: property?['name'] as String? ?? '',
      propertyAddress: property?['address'] as String?,
      roomNumber: room?['room_number'] as String? ?? '',
      roomTypeName: roomType?['name'] as String? ?? '',
      customerName: resolvedCustomerName,
      invoices: invoicesList,
    );
  }

  bool get isGuest => userId == null;
  bool get isPending => status == 'pending';
  bool get isConfirmed => status == 'confirmed';
  bool get isActive => status == 'active';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';
  bool get isRejected => status == 'rejected';

  bool get canConfirm => isPending;
  bool get canCheckIn => isConfirmed;
  bool get canCancel => isPending || isConfirmed;

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'Menunggu Review / DP';
      case 'confirmed':
        return 'Terkonfirmasi (Siap Masuk)';
      case 'active':
        return 'Aktif (Ditempati)';
      case 'completed':
        return 'Selesai (Check-Out)';
      case 'cancelled':
        return 'Dibatalkan';
      case 'rejected':
        return 'Ditolak';
      case 'expired':
        return 'Kedaluwarsa';
      default:
        return status;
    }
  }

  Color get badgeColor {
    switch (status) {
      case 'pending':
        return Colors.orange;
      case 'confirmed':
        return Colors.blue;
      case 'active':
        return Colors.green;
      case 'completed':
        return Colors.grey;
      case 'cancelled':
      case 'rejected':
        return Colors.red;
      case 'expired':
        return Colors.blueGrey;
      default:
        return Colors.grey;
    }
  }
}
