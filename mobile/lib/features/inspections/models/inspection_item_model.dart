import 'package:flutter/material.dart';

class InspectionItemModel {
  final int id;
  final int inspectionId;
  final int? roomAssetId;
  final String itemName;
  final String category;
  final String condition; // good, fair, poor, damaged, missing
  final String? notes;
  final bool repairRequired;
  final num estimatedCost;

  InspectionItemModel({
    required this.id,
    required this.inspectionId,
    this.roomAssetId,
    required this.itemName,
    required this.category,
    required this.condition,
    this.notes,
    this.repairRequired = false,
    this.estimatedCost = 0,
  });

  factory InspectionItemModel.fromJson(Map<String, dynamic> json) {
    return InspectionItemModel(
      id: json['id'] as int? ?? 0,
      inspectionId: json['inspection_id'] as int? ?? 0,
      roomAssetId: json['room_asset_id'] as int?,
      itemName: json['item_name'] as String? ?? '-',
      category: json['category'] as String? ?? 'general',
      condition: json['condition'] as String? ?? 'good',
      notes: json['notes'] as String?,
      repairRequired: json['repair_required'] == true ||
          json['repair_required'] == 1 ||
          json['repair_required'] == '1',
      estimatedCost: num.tryParse(json['estimated_cost']?.toString() ?? '0') ?? 0,
    );
  }

  String get conditionLabel {
    switch (condition) {
      case 'good':
        return 'Baik';
      case 'fair':
        return 'Cukup';
      case 'poor':
        return 'Kurang';
      case 'damaged':
        return 'Rusak';
      case 'missing':
        return 'Hilang';
      default:
        return condition;
    }
  }

  Color get conditionColor {
    switch (condition) {
      case 'good':
        return Colors.green;
      case 'fair':
        return Colors.blue;
      case 'poor':
        return Colors.orange;
      case 'damaged':
      case 'missing':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  IconData get conditionIcon {
    switch (condition) {
      case 'good':
        return Icons.check_circle_rounded;
      case 'fair':
        return Icons.info_rounded;
      case 'poor':
        return Icons.warning_rounded;
      case 'damaged':
        return Icons.error_rounded;
      case 'missing':
        return Icons.cancel_rounded;
      default:
        return Icons.help_outline_rounded;
    }
  }
}
