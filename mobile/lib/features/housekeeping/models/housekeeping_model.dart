class HousekeepingChecklistItem {
  final dynamic id;
  final String task;
  final String? category;
  bool isDone;

  HousekeepingChecklistItem({
    required this.id,
    required this.task,
    this.category,
    this.isDone = false,
  });

  factory HousekeepingChecklistItem.fromJson(Map<String, dynamic> json) {
    return HousekeepingChecklistItem(
      id: json['id'] ?? 0,
      task: json['task'] as String? ?? '',
      category: json['category'] as String?,
      isDone: json['is_done'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'task': task,
        'category': category,
        'is_done': isDone,
      };
}

class HousekeepingLogModel {
  final int id;
  final String action;
  final String? previousStatus;
  final String? newStatus;
  final String? notes;
  final String? userName;
  final DateTime? createdAt;

  HousekeepingLogModel({
    required this.id,
    required this.action,
    this.previousStatus,
    this.newStatus,
    this.notes,
    this.userName,
    this.createdAt,
  });

  factory HousekeepingLogModel.fromJson(Map<String, dynamic> json) {
    return HousekeepingLogModel(
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

class HousekeepingTaskModel {
  final int id;
  final String taskNumber;
  final int propertyId;
  final int? roomId;
  final String? areaName;
  final String type;
  final String priority;
  final String status;
  final DateTime? scheduledDate;
  final String? scheduledSlot;
  final int? assignedTo;
  final String? notes;
  final String? damagedOrMissingItems;
  final String? verificationNotes;
  final String? cancellationReason;
  final List<HousekeepingChecklistItem> checklist;
  final List<String> photosBefore;
  final List<String> photosAfter;
  final String? propertyName;
  final String? roomNumber;
  final String? assigneeName;
  final DateTime? createdAt;
  final List<HousekeepingLogModel> logs;

  HousekeepingTaskModel({
    required this.id,
    required this.taskNumber,
    required this.propertyId,
    this.roomId,
    this.areaName,
    required this.type,
    required this.priority,
    required this.status,
    this.scheduledDate,
    this.scheduledSlot,
    this.assignedTo,
    this.notes,
    this.damagedOrMissingItems,
    this.verificationNotes,
    this.cancellationReason,
    this.checklist = const [],
    this.photosBefore = const [],
    this.photosAfter = const [],
    this.propertyName,
    this.roomNumber,
    this.assigneeName,
    this.createdAt,
    this.logs = const [],
  });

  factory HousekeepingTaskModel.fromJson(Map<String, dynamic> json) {
    return HousekeepingTaskModel(
      id: json['id'] as int? ?? 0,
      taskNumber: json['task_number'] as String? ?? '-',
      propertyId: json['property_id'] as int? ?? 0,
      roomId: json['room_id'] as int?,
      areaName: json['area_name'] as String?,
      type: json['type'] as String? ?? 'room_cleaning',
      priority: json['priority'] as String? ?? 'normal',
      status: json['status'] as String? ?? 'scheduled',
      scheduledDate: json['scheduled_date'] != null ? DateTime.tryParse(json['scheduled_date'].toString()) : null,
      scheduledSlot: json['scheduled_slot'] as String?,
      assignedTo: json['assigned_to'] as int?,
      notes: json['notes'] as String?,
      damagedOrMissingItems: json['damaged_or_missing_items'] as String?,
      verificationNotes: json['verification_notes'] as String?,
      cancellationReason: json['cancellation_reason'] as String?,
      checklist: (json['checklist'] as List<dynamic>?)
              ?.map((e) => HousekeepingChecklistItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      photosBefore: (json['photos_before'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? const [],
      photosAfter: (json['photos_after'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? const [],
      propertyName: json['property']?['name'] as String?,
      roomNumber: json['room']?['room_number'] as String?,
      assigneeName: json['assignee']?['name'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
      logs: (json['logs'] as List<dynamic>?)
              ?.map((e) => HousekeepingLogModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }

  String get typeLabel {
    switch (type) {
      case 'room_cleaning':
        return 'Pembersihan Kamar Rutin';
      case 'turnover_cleaning':
        return 'Turnover Tamu Baru';
      case 'deep_cleaning':
        return 'Pembersihan Menyeluruh (Deep)';
      case 'common_area_cleaning':
        return 'Area Bersama / Fasilitas';
      case 'on_demand':
        return 'Sesuai Permintaan';
      default:
        return type;
    }
  }

  String get statusLabel {
    switch (status) {
      case 'scheduled':
        return 'Terjadwal';
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
      default:
        return status;
    }
  }
}
