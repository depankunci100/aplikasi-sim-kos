import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/utility_meter_model.dart';
import '../models/utility_reading_model.dart';

/// Provider daftar meter fisik untuk Staff / Owner / Manager
final utilityMetersProvider = FutureProvider<List<UtilityMeterModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.utilityMeters);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => UtilityMeterModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (e) {
    // Fallback data demo untuk preview / testing offline
    return [
      UtilityMeterModel(
        id: 1,
        propertyId: 1,
        roomId: 1,
        utilityType: 'electricity',
        meterNumber: 'PLN-101-DEMO',
        meterType: 'individual',
        unit: 'kWh',
        initialReading: 1200.0,
        currentReading: 1350.5,
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '101',
      ),
      UtilityMeterModel(
        id: 2,
        propertyId: 1,
        roomId: 1,
        utilityType: 'water',
        meterNumber: 'PDAM-101-DEMO',
        meterType: 'individual',
        unit: 'm3',
        initialReading: 20.0,
        currentReading: 32.4,
        propertyName: 'Kos Melati Harmoni',
        roomNumber: '101',
      ),
    ];
  }
  return [];
});

/// Provider riwayat pemakaian utilitas khusus kamar penyewa
final tenantConsumptionProvider = FutureProvider<List<UtilityReadingModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.tenantConsumption);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => UtilityReadingModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (e) {
    // Fallback data demo untuk penyewa offline
    return [
      UtilityReadingModel(
        id: 1,
        meterId: 1,
        utilityType: 'electricity',
        previousMeter: 1200.0,
        currentMeter: 1275.0,
        usage: 75.0,
        ratePerUnit: 1500.0,
        totalCost: 112500.0,
        readingDate: DateTime.now().subtract(const Duration(days: 30)),
        status: 'approved',
        isBilled: true,
        unit: 'kWh',
        meterNumber: 'PLN-101-DEMO',
        roomNumber: '101',
      ),
      UtilityReadingModel(
        id: 2,
        meterId: 1,
        utilityType: 'electricity',
        previousMeter: 1275.0,
        currentMeter: 1350.5,
        usage: 75.5,
        ratePerUnit: 1500.0,
        totalCost: 113250.0,
        readingDate: DateTime.now(),
        status: 'approved',
        isBilled: false,
        unit: 'kWh',
        meterNumber: 'PLN-101-DEMO',
        roomNumber: '101',
      ),
    ];
  }
  return [];
});

/// State untuk pencatatan meter
class UtilityActionState {
  final bool isLoading;
  final String? errorMessage;
  final String? successMessage;

  const UtilityActionState({
    this.isLoading = false,
    this.errorMessage,
    this.successMessage,
  });

  UtilityActionState copyWith({
    bool? isLoading,
    String? errorMessage,
    String? successMessage,
  }) {
    return UtilityActionState(
      isLoading: isLoading ?? this.isLoading,
      errorMessage: errorMessage,
      successMessage: successMessage,
    );
  }
}

class UtilityNotifier extends StateNotifier<UtilityActionState> {
  final Ref _ref;

  UtilityNotifier(this._ref) : super(const UtilityActionState());

  Future<bool> recordReading({
    required int meterId,
    required double currentMeter,
    required DateTime readingDate,
    DateTime? periodStart,
    DateTime? periodEnd,
    List<int>? photoBytes,
    String? photoFilename,
    String? notes,
    String status = 'pending_review',
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null, successMessage: null);
    final apiClient = _ref.read(apiClientProvider);

    try {
      final formData = FormData.fromMap({
        'meter_id': meterId,
        'current_meter': currentMeter,
        'reading_date': readingDate.toIso8601String().substring(0, 10),
        if (periodStart != null) 'period_start': periodStart.toIso8601String().substring(0, 10),
        if (periodEnd != null) 'period_end': periodEnd.toIso8601String().substring(0, 10),
        if (notes != null && notes.isNotEmpty) 'notes': notes,
        'status': status,
        if (photoBytes != null && photoBytes.isNotEmpty)
          'proof_photo': MultipartFile.fromBytes(
            photoBytes,
            filename: photoFilename ?? 'meter_proof.png',
          ),
      });

      final response = await apiClient.dio.post(
        ApiEndpoints.utilityReadings,
        data: formData,
      );

      if (response.data['success'] == true) {
        state = state.copyWith(
          isLoading: false,
          successMessage: 'Pembacaan meter berhasil dicatat.',
        );
        _ref.invalidate(utilityMetersProvider);
        _ref.invalidate(tenantConsumptionProvider);
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: response.data['message']?.toString() ?? 'Gagal mencatat pembacaan meter.',
        );
        return false;
      }
    } on DioException catch (e) {
      final msg = e.response?.data?['message']?.toString() ?? 'Terjadi kesalahan koneksi server.';
      state = state.copyWith(isLoading: false, errorMessage: msg);
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: e.toString());
      return false;
    }
  }

  Future<bool> reviewReading({
    required int readingId,
    required String status,
    String? rejectionReason,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null, successMessage: null);
    final apiClient = _ref.read(apiClientProvider);

    try {
      final response = await apiClient.dio.post(
        '${ApiEndpoints.utilityReadings}/$readingId/review',
        data: {
          'status': status,
          if (rejectionReason != null) 'rejection_reason': rejectionReason,
        },
      );

      if (response.data['success'] == true) {
        state = state.copyWith(
          isLoading: false,
          successMessage: 'Tinjauan pembacaan berhasil disimpan ($status).',
        );
        _ref.invalidate(utilityMetersProvider);
        _ref.invalidate(tenantConsumptionProvider);
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: response.data['message']?.toString() ?? 'Gagal meninjau pembacaan.',
        );
        return false;
      }
    } on DioException catch (e) {
      final msg = e.response?.data?['message']?.toString() ?? 'Terjadi kesalahan review.';
      state = state.copyWith(isLoading: false, errorMessage: msg);
      return false;
    }
  }
}

final utilityNotifierProvider = StateNotifierProvider<UtilityNotifier, UtilityActionState>((ref) {
  return UtilityNotifier(ref);
});
