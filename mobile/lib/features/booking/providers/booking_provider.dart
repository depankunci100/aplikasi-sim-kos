import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/booking_model.dart';

final bookingRepositoryProvider = Provider<BookingRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return BookingRepository(apiClient);
});

final bookingListProvider = FutureProvider.autoDispose<List<BookingModel>>((ref) async {
  final repo = ref.watch(bookingRepositoryProvider);
  return repo.fetchBookings();
});

class BookingRepository {
  final dynamic _apiClient;

  BookingRepository(this._apiClient);

  Future<List<BookingModel>> fetchBookings({String? status, int? propertyId, String? search}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (status != null && status.isNotEmpty) queryParams['status'] = status;
      if (propertyId != null) queryParams['property_id'] = propertyId;
      if (search != null && search.isNotEmpty) queryParams['search'] = search;

      final response = await _apiClient.dio.get(
        ApiEndpoints.bookings,
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final list = response.data['data']['data'] as List<dynamic>? ??
            response.data['data'] as List<dynamic>? ??
            [];
        return list.map((e) => BookingModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      // Fallback data untuk pengujian lokal / offline
      return [
        BookingModel(
          id: 1,
          bookingCode: 'BK-20261016-0001',
          propertyId: 1,
          roomId: 1,
          propertyName: 'Kos Permata Hijau',
          roomNumber: '101',
          roomTypeName: 'Deluxe Single',
          customerName: 'Ahmad Fauzi',
          guestEmail: 'fauzi@example.com',
          guestPhone: '081234567890',
          rentalType: 'monthly',
          startDate: DateTime.now().add(const Duration(days: 1)),
          endDate: DateTime.now().add(const Duration(days: 31)),
          rateAmount: 1500000,
          depositAmount: 500000,
          downPaymentAmount: 300000,
          totalAmount: 2000000,
          status: 'pending',
          accessToken: 'demo_token_123456789',
        ),
        BookingModel(
          id: 2,
          bookingCode: 'BK-20261016-0002',
          propertyId: 1,
          roomId: 2,
          propertyName: 'Kos Permata Hijau',
          roomNumber: '102',
          roomTypeName: 'Standard Room',
          customerName: 'Siti Aminah',
          guestEmail: 'siti@example.com',
          guestPhone: '081987654321',
          rentalType: 'daily',
          startDate: DateTime.now().add(const Duration(days: 2)),
          endDate: DateTime.now().add(const Duration(days: 5)),
          rateAmount: 450000,
          depositAmount: 0,
          downPaymentAmount: 0,
          totalAmount: 450000,
          status: 'confirmed',
          accessToken: 'demo_token_987654321',
        ),
      ];
    }
    return [];
  }

  Future<PricingEstimateModel> calculatePrice({
    required int roomId,
    required String rentalType,
    required String startDate,
    required String endDate,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        ApiEndpoints.publicCalculatePrice,
        data: {
          'room_id': roomId,
          'rental_type': rentalType,
          'start_date': startDate,
          'end_date': endDate,
        },
      );

      if (response.data['success'] == true) {
        return PricingEstimateModel.fromJson(response.data['data']['pricing']);
      }
    } catch (_) {
      // Mock calculation for offline
    }

    return PricingEstimateModel(
      days: 30,
      rentalType: rentalType,
      rateAmount: rentalType == 'daily' ? 150000 : 1500000,
      depositAmount: rentalType == 'daily' ? 0 : 500000,
      downPaymentAmount: 300000,
      totalAmount: rentalType == 'daily' ? 150000 : 2000000,
      dpRequired: true,
      paymentDeadlineHours: 24,
    );
  }

  Future<Map<String, dynamic>> submitGuestBooking(Map<String, dynamic> payload) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.publicBookings,
      data: payload,
    );
    if (response.data['success'] == true) {
      return response.data['data'] as Map<String, dynamic>;
    }
    throw Exception(response.data['message'] ?? 'Gagal membuat pemesanan');
  }

  Future<BookingModel?> trackBooking(String token) async {
    final response = await _apiClient.dio.get(
      ApiEndpoints.publicTrackBooking(token),
    );
    if (response.data['success'] == true) {
      return BookingModel.fromJson(response.data['data']);
    }
    return null;
  }

  Future<bool> confirmBooking(int id, {String? notes}) async {
    final response = await _apiClient.dio.post(
      '${ApiEndpoints.bookings}/$id/confirm',
      data: {'notes': notes},
    );
    return response.data['success'] == true;
  }

  Future<bool> rejectBooking(int id, {required String reason}) async {
    final response = await _apiClient.dio.post(
      '${ApiEndpoints.bookings}/$id/reject',
      data: {'rejection_reason': reason},
    );
    return response.data['success'] == true;
  }

  Future<bool> cancelBooking(int id, {required String reason}) async {
    final response = await _apiClient.dio.post(
      '${ApiEndpoints.bookings}/$id/cancel',
      data: {'cancellation_reason': reason},
    );
    return response.data['success'] == true;
  }

  Future<bool> checkInBooking(int id) async {
    final response = await _apiClient.dio.post(
      '${ApiEndpoints.bookings}/$id/check-in',
    );
    return response.data['success'] == true;
  }
}
