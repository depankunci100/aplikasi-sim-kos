import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/deposit_model.dart';

final depositRepositoryProvider = Provider<DepositRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return DepositRepository(apiClient);
});

final depositListProvider = FutureProvider.autoDispose<List<DepositLedgerModel>>((ref) async {
  final repo = ref.watch(depositRepositoryProvider);
  return repo.fetchDeposits();
});

final depositDetailProvider = FutureProvider.autoDispose.family<DepositLedgerModel, int>((ref, id) async {
  final repo = ref.watch(depositRepositoryProvider);
  return repo.getDepositDetail(id);
});

final settlementListProvider = FutureProvider.autoDispose<List<CheckOutSettlementModel>>((ref) async {
  final repo = ref.watch(depositRepositoryProvider);
  return repo.fetchSettlements();
});

final settlementDetailProvider = FutureProvider.autoDispose.family<CheckOutSettlementModel, int>((ref, id) async {
  final repo = ref.watch(depositRepositoryProvider);
  return repo.getSettlementDetail(id);
});

class DepositRepository {
  final dynamic _apiClient;

  DepositRepository(this._apiClient);

  Future<List<DepositLedgerModel>> fetchDeposits({String? status, int? propertyId, String? search}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (status != null && status.isNotEmpty) queryParams['status'] = status;
      if (propertyId != null) queryParams['property_id'] = propertyId;
      if (search != null && search.isNotEmpty) queryParams['search'] = search;

      final response = await _apiClient.dio.get(
        ApiEndpoints.deposits,
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final list = response.data['data']['data'] as List<dynamic>? ??
            response.data['data'] as List<dynamic>? ??
            [];
        return list.map((e) => DepositLedgerModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      // Fallback data representatif untuk pengujian lokal / offline
      return [
        DepositLedgerModel(
          id: 1,
          depositCode: 'DEP-20261017-0001',
          bookingId: 1,
          bookingCode: 'BK-20261016-LOQBBD',
          propertyId: 1,
          propertyName: 'Wisma Melati Harmoni',
          tenantName: 'Budi Santoso',
          roomNumber: '101',
          initialDepositAmount: 500000,
          totalReceived: 500000,
          totalDeductions: 0,
          totalRefunded: 0,
          currentBalance: 500000,
          status: 'held',
        ),
      ];
    }
    return [];
  }

  Future<DepositLedgerModel> getDepositDetail(int id) async {
    try {
      final response = await _apiClient.dio.get(ApiEndpoints.depositDetail(id));
      if (response.data['success'] == true) {
        return DepositLedgerModel.fromJson(response.data['data'] as Map<String, dynamic>);
      }
    } catch (_) {
      // Fallback
      return DepositLedgerModel(
        id: id,
        depositCode: 'DEP-20261017-0001',
        bookingId: 1,
        bookingCode: 'BK-20261016-LOQBBD',
        propertyId: 1,
        propertyName: 'Wisma Melati Harmoni',
        tenantName: 'Budi Santoso',
        roomNumber: '101',
        initialDepositAmount: 500000,
        totalReceived: 500000,
        totalDeductions: 0,
        totalRefunded: 0,
        currentBalance: 500000,
        status: 'held',
      );
    }
    throw Exception('Gagal mengambil data deposit.');
  }

  Future<List<CheckOutSettlementModel>> fetchSettlements({String? status, int? propertyId}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (status != null && status.isNotEmpty) queryParams['status'] = status;
      if (propertyId != null) queryParams['property_id'] = propertyId;

      final response = await _apiClient.dio.get(
        ApiEndpoints.settlements,
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final list = response.data['data']['data'] as List<dynamic>? ??
            response.data['data'] as List<dynamic>? ??
            [];
        return list.map((e) => CheckOutSettlementModel.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {
      return [
        CheckOutSettlementModel(
          id: 1,
          settlementCode: 'SET-20261017-0001',
          bookingId: 1,
          bookingCode: 'BK-20261016-LOQBBD',
          propertyId: 1,
          propertyName: 'Wisma Melati Harmoni',
          tenantName: 'Budi Santoso',
          roomNumber: '101',
          checkOutDate: DateTime.now(),
          status: 'pending_tenant_approval',
          depositBalanceBefore: 500000,
          totalDeductions: 100000,
          netRefundAmount: 400000,
          deductions: [
            SettlementDeductionModel(
              id: 1,
              title: 'Penggantian Kunci Kamar',
              proposedAmount: 100000,
              approvedAmount: 100000,
              isApproved: true,
              isDisputed: false,
            ),
          ],
        ),
      ];
    }
    return [];
  }

  Future<CheckOutSettlementModel> getSettlementDetail(int id) async {
    try {
      final response = await _apiClient.dio.get(ApiEndpoints.settlementDetail(id));
      if (response.data['success'] == true) {
        return CheckOutSettlementModel.fromJson(response.data['data'] as Map<String, dynamic>);
      }
    } catch (_) {
      return CheckOutSettlementModel(
        id: id,
        settlementCode: 'SET-20261017-0001',
        bookingId: 1,
        bookingCode: 'BK-20261016-LOQBBD',
        propertyId: 1,
        propertyName: 'Wisma Melati Harmoni',
        tenantName: 'Budi Santoso',
        roomNumber: '101',
        checkOutDate: DateTime.now(),
        status: 'pending_tenant_approval',
        depositBalanceBefore: 500000,
        totalDeductions: 100000,
        netRefundAmount: 400000,
      );
    }
    throw Exception('Gagal mengambil data settlement.');
  }

  Future<bool> submitTenantDecision({
    required int settlementId,
    required String decision,
    String? objectionNotes,
    List<int>? disputedDeductions,
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.settlementTenantDecision(settlementId),
      data: {
        'decision': decision,
        if (objectionNotes != null) 'objection_notes': objectionNotes,
        if (disputedDeductions != null) 'disputed_deductions': disputedDeductions,
      },
    );
    return response.data['success'] == true;
  }

  Future<bool> finalizeRefund({
    required int settlementId,
    required String refundMethod,
    required String refundDestination,
    String? paymentReference,
  }) async {
    final response = await _apiClient.dio.post(
      ApiEndpoints.settlementFinalize(settlementId),
      data: {
        'refund_method': refundMethod,
        'refund_destination': refundDestination,
        if (paymentReference != null) 'refund_payment_reference': paymentReference,
      },
    );
    return response.data['success'] == true;
  }
}
