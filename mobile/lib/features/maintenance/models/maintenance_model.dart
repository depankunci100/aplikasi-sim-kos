class MaintenanceLogModel {
  final int id;
  final String action;
  final String? previousStatus;
  final String? newStatus;
  final String? notes;
  final String? userName;
  final DateTime? createdAt;

  MaintenanceLogModel({
    required this.id,
    required this.action,
    this.previousStatus,
    this.newStatus,
    this.notes,
    this.userName,
    this.createdAt,
  });

  factory MaintenanceLogModel.fromJson(Map<String, dynamic> json) {
    return MaintenanceLogModel(
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

class MaintenanceRequestModel {
  final int id;
  final String ticketNumber;
  final int propertyId;
  final int? roomId;
  final int reporterId;
  final String category;
  final String priority;
  final String status;
  final String title;
  final String description;
  final String? diagnosisNotes;
  final String? actionTaken;
  final List<String> spareParts;
  final double laborCost;
  final double materialCost;
  final double totalCost;
  final int slaHours;
  final DateTime? slaDueAt;
  final bool isOverdue;
  final int? assignedTo;
  final DateTime? scheduledDate;
  final List<String> photosBefore;
  final List<String> photosAfter;
  final String? propertyName;
  final String? roomNumber;
  final String? reporterName;
  final String? assigneeName;
  final String? verifierName;
  final DateTime? createdAt;
  final List<MaintenanceLogModel> logs;

  MaintenanceRequestModel({
    required this.id,
    required this.ticketNumber,
    required this.propertyId,
    this.roomId,
    required this.reporterId,
    required this.category,
    required this.priority,
    required this.status,
    required this.title,
    required this.description,
    this.diagnosisNotes,
    this.actionTaken,
    this.spareParts = const [],
    this.laborCost = 0.0,
    this.materialCost = 0.0,
    this.totalCost = 0.0,
    this.slaHours = 48,
    this.slaDueAt,
    this.isOverdue = false,
    this.assignedTo,
    this.scheduledDate,
    this.photosBefore = const [],
    this.photosAfter = const [],
    this.propertyName,
    this.roomNumber,
    this.reporterName,
    this.assigneeName,
    this.verifierName,
    this.createdAt,
    this.logs = const [],
  });

  factory MaintenanceRequestModel.fromJson(Map<String, dynamic> json) {
    return MaintenanceRequestModel(
      id: json['id'] as int? ?? 0,
      ticketNumber: json['ticket_number'] as String? ?? '-',
      propertyId: json['property_id'] as int? ?? 0,
      roomId: json['room_id'] as int?,
      reporterId: json['reporter_id'] as int? ?? 0,
      category: json['category'] as String? ?? 'other',
      priority: json['priority'] as String? ?? 'normal',
      status: json['status'] as String? ?? 'reported',
      title: json['title'] as String? ?? '-',
      description: json['description'] as String? ?? '',
      diagnosisNotes: json['diagnosis_notes'] as String?,
      actionTaken: json['action_taken'] as String?,
      spareParts: (json['spare_parts'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? const [],
      laborCost: double.tryParse(json['labor_cost']?.toString() ?? '0') ?? 0.0,
      materialCost: double.tryParse(json['material_cost']?.toString() ?? '0') ?? 0.0,
      totalCost: double.tryParse(json['total_cost']?.toString() ?? '0') ?? 0.0,
      slaHours: json['sla_hours'] as int? ?? 48,
      slaDueAt: json['sla_due_at'] != null ? DateTime.tryParse(json['sla_due_at'].toString()) : null,
      isOverdue: json['is_overdue'] as bool? ?? false,
      assignedTo: json['assigned_to'] as int?,
      scheduledDate: json['scheduled_date'] != null ? DateTime.tryParse(json['scheduled_date'].toString()) : null,
      photosBefore: (json['photos_before'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? const [],
      photosAfter: (json['photos_after'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? const [],
      propertyName: json['property']?['name'] as String?,
      roomNumber: json['room']?['room_number'] as String?,
      reporterName: json['reporter']?['name'] as String?,
      assigneeName: json['assignee']?['name'] as String?,
      verifierName: json['verifier']?['name'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
      logs: (json['logs'] as List<dynamic>?)
              ?.map((e) => MaintenanceLogModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  String get categoryLabel {
    switch (category) {
      case 'electricity':
        return 'Kelistrikan';
      case 'water':
        return 'Saluran Air';
      case 'plumbing':
        return 'Pipa & Sanitasi';
      case 'ac':
        return 'AC & Pendingin';
      case 'furniture':
        return 'Perabot & Furnitur';
      case 'door_lock':
        return 'Pintu & Kunci';
      case 'internet':
        return 'Internet & WiFi';
      case 'cleanliness':
        return 'Kebersihan Ekstra';
      default:
        return 'Lain-lain';
    }
  }

  String get statusLabel {
    switch (status) {
      case 'reported':
        return 'Dilaporkan';
      case 'triaged':
        return 'Ditriase';
      case 'assigned':
        return 'Ditugaskan';
      case 'in_progress':
        return 'Dikerjakan';
      case 'pending_verification':
        return 'Menunggu Verifikasi';
      case 'completed':
        return 'Selesai';
      case 'cancelled':
        return 'Dibatalkan';
      case 'rejected':
        return 'Ditolak';
      default:
        return status;
    }
  }
}
