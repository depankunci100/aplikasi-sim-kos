import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/maintenance_model.dart';

final maintenanceListProvider = FutureProvider.autoDispose<List<MaintenanceRequestModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.maintenance);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => MaintenanceRequestModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    // Fallback data demo untuk preview / testing offline
    return [
      MaintenanceRequestModel(
        id: 1,
        ticketNumber: 'MNT-20261015-00001',
        propertyId: 1,
        roomId: 1,
        reporterId: 1,
        category: 'ac',
        priority: 'high',
        status: 'in_progress',
        title: 'AC Bocor dan Kurang Dingin',
        description: 'Tetesan air membasahi lantai kamar tidur.',
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '101',
        reporterName: 'Siti Rahma',
        assigneeName: 'Budi Santoso',
        laborCost: 50000,
        materialCost: 35000,
        totalCost: 85000,
        slaHours: 24,
        isOverdue: false,
        createdAt: DateTime.now().subtract(const Duration(hours: 3)),
      ),
      MaintenanceRequestModel(
        id: 2,
        ticketNumber: 'MNT-20261015-00002',
        propertyId: 1,
        roomId: 2,
        reporterId: 2,
        category: 'plumbing',
        priority: 'normal',
        status: 'reported',
        title: 'Keran Wastafel Longgar',
        description: 'Perlu diganti seal atau dipasang ulang.',
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '102',
        reporterName: 'Doni Pratama',
        slaHours: 48,
        createdAt: DateTime.now().subtract(const Duration(hours: 1)),
      ),
    ];
  }
  return [];
});

final maintenanceDetailProvider = FutureProvider.family<MaintenanceRequestModel, int>((ref, id) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get('${ApiEndpoints.maintenance}/$id');
    if (response.data['success'] == true) {
      return MaintenanceRequestModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {}

  // Fallback
  return MaintenanceRequestModel(
    id: id,
    ticketNumber: 'MNT-20261015-00001',
    propertyId: 1,
    roomId: 1,
    reporterId: 1,
    category: 'ac',
    priority: 'high',
    status: 'in_progress',
    title: 'AC Bocor dan Kurang Dingin',
    description: 'Tetesan air membasahi lantai kamar tidur.',
    propertyName: 'Kos Melati Harmoni',
    roomNumber: '101',
    reporterName: 'Siti Rahma',
    assigneeName: 'Budi Santoso',
    laborCost: 50000,
    materialCost: 35000,
    totalCost: 85000,
    slaHours: 24,
    isOverdue: false,
    createdAt: DateTime.now().subtract(const Duration(hours: 3)),
  );
});

class MaintenanceServiceNotifier {
  final ApiClient apiClient;
  MaintenanceServiceNotifier(this.apiClient);

  Future<bool> createRequest({
    required int propertyId,
    int? roomId,
    required String category,
    required String priority,
    required String title,
    required String description,
  }) async {
    try {
      final response = await apiClient.dio.post(
        ApiEndpoints.maintenance,
        data: {
          'property_id': propertyId,
          if (roomId != null) 'room_id': roomId,
          'category': category,
          'priority': priority,
          'title': title,
          'description': description,
        },
      );
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> startWork(int id) async {
    try {
      final response = await apiClient.dio.post('${ApiEndpoints.maintenance}/$id/start');
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> submitVerification({
    required int id,
    required String actionTaken,
    String? diagnosisNotes,
    double? laborCost,
    double? materialCost,
  }) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.maintenance}/$id/submit-verification',
        data: {
          'action_taken': actionTaken,
          if (diagnosisNotes != null) 'diagnosis_notes': diagnosisNotes,
          if (laborCost != null) 'labor_cost': laborCost,
          if (materialCost != null) 'material_cost': materialCost,
        },
      );
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> verifyAndComplete(int id, String notes) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.maintenance}/$id/verify',
        data: {'verification_notes': notes},
      );
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }
}

final maintenanceServiceProvider = Provider<MaintenanceServiceNotifier>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return MaintenanceServiceNotifier(apiClient);
});
