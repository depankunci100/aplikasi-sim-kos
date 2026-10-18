import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/housekeeping_model.dart';

final housekeepingListProvider = FutureProvider.autoDispose<List<HousekeepingTaskModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.housekeeping);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => HousekeepingTaskModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    // Fallback data demo untuk preview / testing offline
    return [
      HousekeepingTaskModel(
        id: 1,
        taskNumber: 'HSK-20261015-00001',
        propertyId: 1,
        roomId: 1,
        type: 'room_cleaning',
        priority: 'normal',
        status: 'in_progress',
        scheduledDate: DateTime.now(),
        scheduledSlot: 'Pagi (08:00 - 11:00)',
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '101',
        assigneeName: 'Staf Rini',
        checklist: [
          HousekeepingChecklistItem(id: 1, task: 'Sapu dan pel lantai', isDone: true),
          HousekeepingChecklistItem(id: 2, task: 'Ganti sprei & sarung bantal', isDone: true),
          HousekeepingChecklistItem(id: 3, task: 'Bersihkan kamar mandi & kloset', isDone: false),
          HousekeepingChecklistItem(id: 4, task: 'Buang sampah & semprot pewangi', isDone: false),
        ],
        createdAt: DateTime.now().subtract(const Duration(hours: 2)),
      ),
      HousekeepingTaskModel(
        id: 2,
        taskNumber: 'HSK-20261015-00002',
        propertyId: 1,
        areaName: 'Lobby & Ruang Tamu Lantai 1',
        type: 'common_area_cleaning',
        priority: 'normal',
        status: 'scheduled',
        scheduledDate: DateTime.now(),
        scheduledSlot: 'Siang (11:00 - 14:00)',
        propertyName: 'Kos Melati Harmoni',
        checklist: [
          HousekeepingChecklistItem(id: 1, task: 'Sapu dan pel area lobby', isDone: false),
          HousekeepingChecklistItem(id: 2, task: 'Lap meja & kaca jendela', isDone: false),
        ],
        createdAt: DateTime.now().subtract(const Duration(hours: 1)),
      ),
    ];
  }
  return [];
});

final housekeepingDetailProvider = FutureProvider.family<HousekeepingTaskModel, int>((ref, id) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get('${ApiEndpoints.housekeeping}/$id');
    if (response.data['success'] == true) {
      return HousekeepingTaskModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {}

  return HousekeepingTaskModel(
    id: id,
    taskNumber: 'HSK-20261015-00001',
    propertyId: 1,
    roomId: 1,
    type: 'room_cleaning',
    priority: 'normal',
    status: 'in_progress',
    scheduledDate: DateTime.now(),
    scheduledSlot: 'Pagi (08:00 - 11:00)',
    propertyName: 'Kos Melati Harmoni',
    roomNumber: '101',
    assigneeName: 'Staf Rini',
    checklist: [
      HousekeepingChecklistItem(id: 1, task: 'Sapu dan pel lantai', isDone: true),
      HousekeepingChecklistItem(id: 2, task: 'Ganti sprei & sarung bantal', isDone: true),
      HousekeepingChecklistItem(id: 3, task: 'Bersihkan kamar mandi & kloset', isDone: false),
      HousekeepingChecklistItem(id: 4, task: 'Buang sampah & semprot pewangi', isDone: false),
    ],
    createdAt: DateTime.now().subtract(const Duration(hours: 2)),
  );
});

class HousekeepingServiceNotifier {
  final ApiClient apiClient;
  final Ref? ref;
  HousekeepingServiceNotifier(this.apiClient, [this.ref]);

  Future<bool> startTask(int id) async {
    try {
      final response = await apiClient.dio.post('${ApiEndpoints.housekeeping}/$id/start');
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> submitVerification({
    required int id,
    required List<Map<String, dynamic>> checklist,
    String? damagedOrMissingItems,
    String? notes,
  }) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.housekeeping}/$id/submit-verification',
        data: {
          'checklist': checklist,
          if (damagedOrMissingItems != null) 'damaged_or_missing_items': damagedOrMissingItems,
          if (notes != null) 'notes': notes,
        },
      );
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> verifyTask(int id, String notes) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.housekeeping}/$id/verify',
        data: {'verification_notes': notes},
      );
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> approveTask(int id, {String? feedback}) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.housekeeping}/$id/approve',
        data: {
          if (feedback != null && feedback.isNotEmpty) 'feedback': feedback,
        },
      );
      if (response.data['success'] == true) {
        ref?.invalidate(housekeepingListProvider);
        ref?.invalidate(housekeepingDetailProvider(id));
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> requestRevision(int id, {required String reason, List<String>? problematicItems}) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.housekeeping}/$id/request-revision',
        data: {
          'reason': reason,
          if (problematicItems != null && problematicItems.isNotEmpty)
            'problematic_items': problematicItems,
        },
      );
      if (response.data['success'] == true) {
        ref?.invalidate(housekeepingListProvider);
        ref?.invalidate(housekeepingDetailProvider(id));
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }
}

final housekeepingServiceProvider = Provider<HousekeepingServiceNotifier>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return HousekeepingServiceNotifier(apiClient, ref);
});
