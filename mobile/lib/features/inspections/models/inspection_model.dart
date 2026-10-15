import 'package:flutter/material.dart';
import 'inspection_item_model.dart';
import 'inspection_media_model.dart';

class InspectionLogModel {
  final int id;
  final String action;
  final String? description;
  final String? userName;
  final DateTime? createdAt;

  InspectionLogModel({
    required this.id,
    required this.action,
    this.description,
    this.userName,
    this.createdAt,
  });

  factory InspectionLogModel.fromJson(Map<String, dynamic> json) {
    return InspectionLogModel(
      id: json['id'] as int? ?? 0,
      action: json['action'] as String? ?? '-',
      description: json['description'] as String?,
      userName: json['user']?['name'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
    );
  }
}

class InspectionModel {
  final int id;
  final String inspectionCode;
  final int propertyId;
  final int roomId;
  final int? bookingId;
  final int? inspectorId;
  final int? tenantId;
  final int? baselineInspectionId;
  final String type; // check_in, periodic, check_out, maintenance
  final String status; // scheduled, in_progress, completed, approved_by_tenant, objected_by_tenant, cancelled
  final DateTime? scheduledDate;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final String? overallCondition; // good, fair, poor, damaged
  final String? inspectorNotes;
  final String? tenantFeedback;
  final DateTime? tenantDecisionAt;
  final DateTime? tenantDueAt;
  final String? propertyName;
  final String? roomNumber;
  final String? inspectorName;
  final String? tenantName;
  final List<InspectionItemModel> items;
  final List<InspectionMediaModel> media;
  final List<InspectionLogModel> logs;

  InspectionModel({
    required this.id,
    required this.inspectionCode,
    required this.propertyId,
    required this.roomId,
    this.bookingId,
    this.inspectorId,
    this.tenantId,
    this.baselineInspectionId,
    required this.type,
    required this.status,
    this.scheduledDate,
    this.startedAt,
    this.completedAt,
    this.overallCondition,
    this.inspectorNotes,
    this.tenantFeedback,
    this.tenantDecisionAt,
    this.tenantDueAt,
    this.propertyName,
    this.roomNumber,
    this.inspectorName,
    this.tenantName,
    this.items = const [],
    this.media = const [],
    this.logs = const [],
  });

  factory InspectionModel.fromJson(Map<String, dynamic> json) {
    final itemsList = (json['items'] as List<dynamic>?)
            ?.map((e) => InspectionItemModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final mediaList = (json['media'] as List<dynamic>?)
            ?.map((e) => InspectionMediaModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final logsList = (json['logs'] as List<dynamic>?)
            ?.map((e) => InspectionLogModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    return InspectionModel(
      id: json['id'] as int? ?? 0,
      inspectionCode: json['inspection_code'] as String? ?? '-',
      propertyId: json['property_id'] as int? ?? 0,
      roomId: json['room_id'] as int? ?? 0,
      bookingId: json['booking_id'] as int?,
      inspectorId: json['inspector_id'] as int?,
      tenantId: json['tenant_id'] as int?,
      baselineInspectionId: json['baseline_inspection_id'] as int?,
      type: json['type'] as String? ?? 'check_in',
      status: json['status'] as String? ?? 'scheduled',
      scheduledDate: json['scheduled_date'] != null ? DateTime.tryParse(json['scheduled_date'].toString()) : null,
      startedAt: json['started_at'] != null ? DateTime.tryParse(json['started_at'].toString()) : null,
      completedAt: json['completed_at'] != null ? DateTime.tryParse(json['completed_at'].toString()) : null,
      overallCondition: json['overall_condition'] as String?,
      inspectorNotes: json['inspector_notes'] as String?,
      tenantFeedback: json['tenant_feedback'] as String?,
      tenantDecisionAt: json['tenant_decision_at'] != null ? DateTime.tryParse(json['tenant_decision_at'].toString()) : null,
      tenantDueAt: json['tenant_due_at'] != null ? DateTime.tryParse(json['tenant_due_at'].toString()) : null,
      propertyName: json['property']?['name'] as String?,
      roomNumber: json['room']?['room_number'] as String?,
      inspectorName: json['inspector']?['name'] as String?,
      tenantName: json['tenant']?['name'] as String?,
      items: itemsList,
      media: mediaList,
      logs: logsList,
    );
  }

  String get typeLabel {
    switch (type) {
      case 'check_in':
        return 'Check-in (Awal)';
      case 'periodic':
        return 'Berkala';
      case 'check_out':
        return 'Check-out (Akhir)';
      case 'maintenance':
        return 'Pemeliharaan';
      default:
        return type;
    }
  }

  String get statusLabel {
    switch (status) {
      case 'scheduled':
        return 'Terjadwal';
      case 'in_progress':
        return 'Sedang Berlangsung';
      case 'completed':
        return 'Menunggu Persetujuan Penyewa';
      case 'approved_by_tenant':
        return 'Disetujui Penyewa';
      case 'objected_by_tenant':
        return 'Ada Keberatan';
      case 'cancelled':
        return 'Dibatalkan';
      default:
        return status;
    }
  }

  Color get statusColor {
    switch (status) {
      case 'scheduled':
        return Colors.blue;
      case 'in_progress':
        return Colors.amber.shade800;
      case 'completed':
        return Colors.orange;
      case 'approved_by_tenant':
        return Colors.green;
      case 'objected_by_tenant':
        return Colors.red;
      case 'cancelled':
        return Colors.grey;
      default:
        return Colors.grey;
    }
  }

  bool get canTenantDecide => status == 'completed';

  String get overallConditionLabel {
    switch (overallCondition) {
      case 'good':
        return 'Sangat Baik / Layak';
      case 'fair':
        return 'Cukup Baik';
      case 'poor':
        return 'Kurang Memuaskan';
      case 'damaged':
        return 'Banyak Kerusakan';
      default:
        return overallCondition ?? '-';
    }
  }
}
