import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/inspection_item_model.dart';
import '../models/inspection_model.dart';

final inspectionsProvider = FutureProvider<List<InspectionModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.inspections);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => InspectionModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (e) {
    // Fallback data demo untuk preview / testing offline
    return [
      InspectionModel(
        id: 1,
        inspectionCode: 'INSP-20261015-001',
        propertyId: 1,
        roomId: 1,
        type: 'check_in',
        status: 'completed',
        scheduledDate: DateTime.now(),
        startedAt: DateTime.now().subtract(const Duration(hours: 2)),
        completedAt: DateTime.now().subtract(const Duration(hours: 1)),
        overallCondition: 'good',
        inspectorNotes: 'Kamar siap huni, AC dingin, kunci berfungsi normal.',
        propertyName: 'Kos Melati Harmoni',
        roomNumber: 'Kamar 101',
        inspectorName: 'Budi Santoso',
        tenantName: 'Rizky Pratama',
        items: [
          InspectionItemModel(
            id: 1,
            inspectionId: 1,
            itemName: 'Kunci & Grendel Pintu',
            category: 'Pintu & Jendela',
            condition: 'good',
            notes: '2 anak kunci diserahkan',
          ),
          InspectionItemModel(
            id: 2,
            inspectionId: 1,
            itemName: 'Pendingin Udara (AC)',
            category: 'Elektronik',
            condition: 'good',
            notes: 'Dingin dan remote berfungsi normal',
          ),
          InspectionItemModel(
            id: 3,
            inspectionId: 1,
            itemName: 'Kasur & Matras',
            category: 'Furnitur Kamar',
            condition: 'fair',
            notes: 'Ada sedikit noda pemakaian wajar',
          ),
        ],
      ),
    ];
  }
  return [];
});

final inspectionDetailProvider = FutureProvider.family<InspectionModel?, int>((ref, id) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get('${ApiEndpoints.inspections}/$id');
    if (response.data['success'] == true) {
      return InspectionModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {}
  return null;
});

final inspectionComparisonProvider =
    FutureProvider.family<Map<String, dynamic>?, ({int currentId, int baselineId})>((ref, arg) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get('${ApiEndpoints.inspections}/${arg.currentId}/compare/${arg.baselineId}');
    if (response.data['success'] == true) {
      return response.data['data'] as Map<String, dynamic>;
    }
  } catch (_) {}
  return null;
});

class InspectionDecisionState {
  final bool isLoading;
  final String? errorMessage;
  final bool isSuccess;

  InspectionDecisionState({
    this.isLoading = false,
    this.errorMessage,
    this.isSuccess = false,
  });

  InspectionDecisionState copyWith({
    bool? isLoading,
    String? errorMessage,
    bool? isSuccess,
  }) {
    return InspectionDecisionState(
      isLoading: isLoading ?? this.isLoading,
      errorMessage: errorMessage,
      isSuccess: isSuccess ?? this.isSuccess,
    );
  }
}

final inspectionDecisionProvider =
    StateNotifierProvider<InspectionDecisionNotifier, InspectionDecisionState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return InspectionDecisionNotifier(apiClient, ref);
});

class InspectionDecisionNotifier extends StateNotifier<InspectionDecisionState> {
  final ApiClient _apiClient;
  final Ref _ref;

  InspectionDecisionNotifier(this._apiClient, this._ref) : super(InspectionDecisionState());

  Future<bool> approve(int inspectionId, {String? notes}) async {
    state = state.copyWith(isLoading: true, errorMessage: null, isSuccess: false);
    try {
      final response = await _apiClient.dio.post(
        '${ApiEndpoints.inspections}/$inspectionId/approve',
        data: {
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      );

      if (response.data['success'] == true) {
        state = state.copyWith(isLoading: false, isSuccess: true);
        _ref.invalidate(inspectionsProvider);
        _ref.invalidate(inspectionDetailProvider(inspectionId));
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: response.data['message'] ?? 'Persetujuan inspeksi gagal.',
        );
        return false;
      }
    } on DioException catch (e) {
      String msg = 'Gagal menghubungi server.';
      if (e.response?.data is Map && e.response?.data['message'] != null) {
        msg = e.response?.data['message'].toString() ?? msg;
      }
      state = state.copyWith(isLoading: false, errorMessage: msg);
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: 'Terjadi kesalahan: $e');
      return false;
    }
  }

  Future<bool> object(int inspectionId, {required String objectionNotes}) async {
    state = state.copyWith(isLoading: true, errorMessage: null, isSuccess: false);
    try {
      final response = await _apiClient.dio.post(
        '${ApiEndpoints.inspections}/$inspectionId/object',
        data: {
          'objection_notes': objectionNotes,
        },
      );

      if (response.data['success'] == true) {
        state = state.copyWith(isLoading: false, isSuccess: true);
        _ref.invalidate(inspectionsProvider);
        _ref.invalidate(inspectionDetailProvider(inspectionId));
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: response.data['message'] ?? 'Pengajuan keberatan gagal.',
        );
        return false;
      }
    } on DioException catch (e) {
      String msg = 'Gagal menghubungi server.';
      if (e.response?.data is Map && e.response?.data['message'] != null) {
        msg = e.response?.data['message'].toString() ?? msg;
      }
      state = state.copyWith(isLoading: false, errorMessage: msg);
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: 'Terjadi kesalahan: $e');
      return false;
    }
  }

  void reset() {
    state = InspectionDecisionState();
  }
}
