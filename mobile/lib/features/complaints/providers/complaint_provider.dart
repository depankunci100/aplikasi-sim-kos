import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/complaint_model.dart';

final complaintsListProvider = FutureProvider.autoDispose<List<ComplaintModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.complaints);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => ComplaintModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (_) {
    // Fallback data demo untuk preview / testing offline
    return [
      ComplaintModel(
        id: 1,
        ticketNumber: 'CMP-20261015-00001',
        propertyId: 1,
        roomId: 1,
        userId: 1,
        category: 'room',
        priority: 'high',
        status: 'in_progress',
        title: 'Kebocoran Plafon Kamar',
        description: 'Tetesan air saat hujan lebat di atas meja belajar.',
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '101',
        userName: 'Siti Rahma',
        assigneeName: 'Staf Agus',
        createdAt: DateTime.now().subtract(const Duration(hours: 4)),
        comments: [
          ComplaintCommentModel(
            id: 1,
            comment: 'Petugas perbaikan atap sedang diarahkan ke lokasi.',
            isInternal: false,
            userName: 'Staf Agus',
            createdAt: DateTime.now().subtract(const Duration(hours: 2)),
          ),
        ],
      ),
      ComplaintModel(
        id: 2,
        ticketNumber: 'CMP-20261015-00002',
        propertyId: 1,
        roomId: 1,
        userId: 1,
        category: 'facility',
        priority: 'normal',
        status: 'resolved',
        title: 'Koneksi WiFi Lantai 1 Lambat',
        description: 'Sering terputus saat jam 8 malam ke atas.',
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '101',
        userName: 'Siti Rahma',
        resolutionNotes: 'Router telah di-restart dan bandwidth limit disesuaikan.',
        createdAt: DateTime.now().subtract(const Duration(days: 1)),
      ),
    ];
  }
  return [];
});

final complaintDetailProvider = FutureProvider.family<ComplaintModel, int>((ref, id) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get('${ApiEndpoints.complaints}/$id');
    if (response.data['success'] == true) {
      return ComplaintModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {}

  return ComplaintModel(
    id: id,
    ticketNumber: 'CMP-20261015-00001',
    propertyId: 1,
    roomId: 1,
    userId: 1,
    category: 'room',
    priority: 'high',
    status: 'in_progress',
    title: 'Kebocoran Plafon Kamar',
    description: 'Tetesan air saat hujan lebat di atas meja belajar.',
    propertyName: 'Kos Melati Harmoni',
    roomNumber: '101',
    userName: 'Siti Rahma',
    assigneeName: 'Staf Agus',
    createdAt: DateTime.now().subtract(const Duration(hours: 4)),
  );
});

class ComplaintServiceNotifier {
  final ApiClient apiClient;
  ComplaintServiceNotifier(this.apiClient);

  Future<bool> createComplaint({
    required int propertyId,
    int? roomId,
    required String category,
    required String priority,
    required String title,
    required String description,
  }) async {
    try {
      final response = await apiClient.dio.post(
        ApiEndpoints.complaints,
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

  Future<bool> addComment({
    required int complaintId,
    required String comment,
    bool isInternal = false,
  }) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.complaints}/$complaintId/comments',
        data: {
          'comment': comment,
          'is_internal': isInternal,
        },
      );
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> confirmOrReopen({
    required int complaintId,
    required String action, // 'confirm' or 'reopen'
    String? reason,
    int? rating,
    String? feedback,
  }) async {
    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.complaints}/$complaintId/confirm-reopen',
        data: {
          'action': action,
          if (reason != null) 'reason': reason,
          if (rating != null) 'rating': rating,
          if (feedback != null) 'feedback': feedback,
        },
      );
      return response.data['success'] == true;
    } catch (_) {
      return false;
    }
  }
}

final complaintServiceProvider = Provider<ComplaintServiceNotifier>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ComplaintServiceNotifier(apiClient);
});
