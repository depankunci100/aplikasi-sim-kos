class ComplaintCommentModel {
  final int id;
  final String comment;
  final bool isInternal;
  final List<String> attachments;
  final String? userName;
  final DateTime? createdAt;

  ComplaintCommentModel({
    required this.id,
    required this.comment,
    this.isInternal = false,
    this.attachments = const [],
    this.userName,
    this.createdAt,
  });

  factory ComplaintCommentModel.fromJson(Map<String, dynamic> json) {
    return ComplaintCommentModel(
      id: json['id'] as int? ?? 0,
      comment: json['comment'] as String? ?? '',
      isInternal: json['is_internal'] as bool? ?? false,
      attachments: (json['attachments'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? const [],
      userName: json['user']?['name'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
    );
  }
}

class ComplaintLogModel {
  final int id;
  final String action;
  final String? previousStatus;
  final String? newStatus;
  final String? notes;
  final String? userName;
  final DateTime? createdAt;

  ComplaintLogModel({
    required this.id,
    required this.action,
    this.previousStatus,
    this.newStatus,
    this.notes,
    this.userName,
    this.createdAt,
  });

  factory ComplaintLogModel.fromJson(Map<String, dynamic> json) {
    return ComplaintLogModel(
      id: json['id'] as int? ?? 0,
      action: json['action'] as String? ?? '-',
      previousStatus: json['previous_status'] as String?,
      newStatus: json['new_status'] as String?,
      notes: json['notes'] as String?,
      userName: json['user']?['name'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
    );
  }
}

class ComplaintModel {
  final int id;
  final String ticketNumber;
  final int propertyId;
  final int? roomId;
  final int userId;
  final String category;
  final String priority;
  final String status;
  final String title;
  final String description;
  final DateTime? firstRespondedAt;
  final DateTime? resolvedAt;
  final DateTime? closedAt;
  final String? resolutionNotes;
  final String? reopenReason;
  final String? rejectionReason;
  final int? rating;
  final String? tenantFeedback;
  final int? assignedTo;
  final int? maintenanceRequestId;
  final List<String> photos;
  final String? propertyName;
  final String? roomNumber;
  final String? userName;
  final String? assigneeName;
  final DateTime? createdAt;
  final List<ComplaintCommentModel> comments;
  final List<ComplaintLogModel> logs;

  ComplaintModel({
    required this.id,
    required this.ticketNumber,
    required this.propertyId,
    this.roomId,
    required this.userId,
    required this.category,
    required this.priority,
    required this.status,
    required this.title,
    required this.description,
    this.firstRespondedAt,
    this.resolvedAt,
    this.closedAt,
    this.resolutionNotes,
    this.reopenReason,
    this.rejectionReason,
    this.rating,
    this.tenantFeedback,
    this.assignedTo,
    this.maintenanceRequestId,
    this.photos = const [],
    this.propertyName,
    this.roomNumber,
    this.userName,
    this.assigneeName,
    this.createdAt,
    this.comments = const [],
    this.logs = const [],
  });

  factory ComplaintModel.fromJson(Map<String, dynamic> json) {
    return ComplaintModel(
      id: json['id'] as int? ?? 0,
      ticketNumber: json['ticket_number'] as String? ?? '-',
      propertyId: json['property_id'] as int? ?? 0,
      roomId: json['room_id'] as int?,
      userId: json['user_id'] as int? ?? 0,
      category: json['category'] as String? ?? 'other',
      priority: json['priority'] as String? ?? 'normal',
      status: json['status'] as String? ?? 'submitted',
      title: json['title'] as String? ?? '-',
      description: json['description'] as String? ?? '',
      firstRespondedAt: json['first_responded_at'] != null ? DateTime.tryParse(json['first_responded_at'].toString()) : null,
      resolvedAt: json['resolved_at'] != null ? DateTime.tryParse(json['resolved_at'].toString()) : null,
      closedAt: json['closed_at'] != null ? DateTime.tryParse(json['closed_at'].toString()) : null,
      resolutionNotes: json['resolution_notes'] as String?,
      reopenReason: json['reopen_reason'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      rating: json['rating'] as int?,
      tenantFeedback: json['tenant_feedback'] as String?,
      assignedTo: json['assigned_to'] as int?,
      maintenanceRequestId: json['maintenance_request_id'] as int?,
      photos: (json['photos'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? const [],
      propertyName: json['property']?['name'] as String?,
      roomNumber: json['room']?['room_number'] as String?,
      userName: json['user']?['name'] as String?,
      assigneeName: json['assignee']?['name'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
      comments: (json['comments'] as List<dynamic>?)
              ?.map((e) => ComplaintCommentModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      logs: (json['logs'] as List<dynamic>?)
              ?.map((e) => ComplaintLogModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  String get categoryLabel {
    switch (category) {
      case 'room':
        return 'Kamar';
      case 'facility':
        return 'Fasilitas';
      case 'service':
        return 'Pelayanan';
      case 'billing':
        return 'Tagihan / Keuangan';
      case 'environment':
        return 'Lingkungan / Kebisingan';
      default:
        return 'Lain-lain';
    }
  }

  String get statusLabel {
    switch (status) {
      case 'submitted':
        return 'Terkirim';
      case 'acknowledged':
        return 'Ditinjau Petugas';
      case 'in_progress':
        return 'Sedang Ditangani';
      case 'resolved':
        return 'Terselesaikan';
      case 'closed':
        return 'Ditutup';
      case 'reopened':
        return 'Dibuka Kembali';
      case 'rejected':
        return 'Ditolak';
      default:
        return status;
    }
  }
}
