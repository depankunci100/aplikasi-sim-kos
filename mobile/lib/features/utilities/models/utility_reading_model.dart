class UtilityReadingModel {
  final int id;
  final int meterId;
  final int? propertyId;
  final int? roomId;
  final int? bookingId;
  final int? invoiceId;
  final String utilityType; // electricity, water
  final num previousMeter;
  final num currentMeter;
  final num usage;
  final num ratePerUnit;
  final num totalCost;
  final DateTime readingDate;
  final DateTime? periodStart;
  final DateTime? periodEnd;
  final String status; // draft, pending_review, approved, rejected
  final String? rejectionReason;
  final String? proofPhoto;
  final bool isBilled;
  final String? notes;
  final String? meterNumber;
  final String? roomNumber;
  final String? propertyName;
  final String? unit;
  final String? recorderName;

  UtilityReadingModel({
    required this.id,
    required this.meterId,
    this.propertyId,
    this.roomId,
    this.bookingId,
    this.invoiceId,
    required this.utilityType,
    required this.previousMeter,
    required this.currentMeter,
    required this.usage,
    required this.ratePerUnit,
    required this.totalCost,
    required this.readingDate,
    this.periodStart,
    this.periodEnd,
    required this.status,
    this.rejectionReason,
    this.proofPhoto,
    this.isBilled = false,
    this.notes,
    this.meterNumber,
    this.roomNumber,
    this.propertyName,
    this.unit,
    this.recorderName,
  });

  factory UtilityReadingModel.fromJson(Map<String, dynamic> json) {
    return UtilityReadingModel(
      id: json['id'] as int? ?? 0,
      meterId: json['meter_id'] as int? ?? 0,
      propertyId: json['property_id'] as int?,
      roomId: json['room_id'] as int?,
      bookingId: json['booking_id'] as int?,
      invoiceId: json['invoice_id'] as int?,
      utilityType: json['utility_type'] as String? ?? 'electricity',
      previousMeter: num.tryParse(json['previous_meter']?.toString() ?? '0') ?? 0,
      currentMeter: num.tryParse(json['current_meter']?.toString() ?? '0') ?? 0,
      usage: num.tryParse(json['usage']?.toString() ?? '0') ?? 0,
      ratePerUnit: num.tryParse(json['rate_per_unit']?.toString() ?? '0') ?? 0,
      totalCost: num.tryParse(json['total_cost']?.toString() ?? '0') ?? 0,
      readingDate: json['reading_date'] != null
          ? DateTime.tryParse(json['reading_date'].toString()) ?? DateTime.now()
          : DateTime.now(),
      periodStart: json['period_start'] != null ? DateTime.tryParse(json['period_start'].toString()) : null,
      periodEnd: json['period_end'] != null ? DateTime.tryParse(json['period_end'].toString()) : null,
      status: json['status'] as String? ?? 'pending_review',
      rejectionReason: json['rejection_reason'] as String?,
      proofPhoto: json['proof_photo'] as String?,
      isBilled: json['is_billed'] == true || json['is_billed'] == 1,
      notes: json['notes'] as String?,
      meterNumber: json['meter']?['meter_number'] as String?,
      roomNumber: json['room']?['room_number'] as String?,
      propertyName: json['property']?['name'] as String?,
      unit: json['meter']?['unit'] as String? ?? (json['utility_type'] == 'water' ? 'm3' : 'kWh'),
      recorderName: json['recorder']?['name'] as String?,
    );
  }

  bool get isElectricity => utilityType == 'electricity';
  bool get isApproved => status == 'approved';
  bool get isPendingReview => status == 'pending_review';
  bool get isRejected => status == 'rejected';

  String get statusLabel {
    switch (status) {
      case 'approved':
        return 'Disetujui';
      case 'pending_review':
        return 'Menunggu Review';
      case 'rejected':
        return 'Ditolak';
      case 'draft':
      default:
        return 'Draf';
    }
  }
}
