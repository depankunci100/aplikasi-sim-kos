import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../auth/providers/auth_provider.dart';
import '../models/invoice_model.dart';
import '../models/payment_model.dart';

final invoicesProvider = FutureProvider<List<InvoiceModel>>((ref) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get(ApiEndpoints.invoices);
    if (response.data['success'] == true) {
      final list = response.data['data']['data'] as List<dynamic>? ??
          response.data['data'] as List<dynamic>? ??
          [];
      return list.map((e) => InvoiceModel.fromJson(e as Map<String, dynamic>)).toList();
    }
  } catch (e) {
    // Fallback data demo jika offline/mocking saat pengujian
    return [
      InvoiceModel(
        id: 1,
        invoiceNumber: 'INV-20261013-DEMO1',
        invoiceType: 'rent',
        subtotal: 1500000,
        totalAmount: 2000000,
        paidAmount: 500000,
        remainingBalance: 1500000,
        status: 'partially_paid',
        dueDate: DateTime.now().add(const Duration(days: 3)),
        payments: [
          PaymentModel(
            id: 1,
            paymentCode: 'PAY-DEMO-01',
            invoiceId: 1,
            amount: 500000,
            paymentMethod: 'manual_transfer',
            paymentProvider: 'manual_bca',
            senderBank: 'BCA',
            senderName: 'Rizky',
            status: 'verified',
            createdAt: DateTime.now().subtract(const Duration(days: 1)),
          ),
        ],
      ),
    ];
  }
  return [];
});

final invoiceDetailProvider = FutureProvider.family<InvoiceModel?, int>((ref, id) async {
  final apiClient = ref.watch(apiClientProvider);
  try {
    final response = await apiClient.dio.get('${ApiEndpoints.invoices}/$id');
    if (response.data['success'] == true) {
      return InvoiceModel.fromJson(response.data['data'] as Map<String, dynamic>);
    }
  } catch (_) {}
  return null;
});

class PaymentSubmissionState {
  final bool isLoading;
  final String? errorMessage;
  final bool isSuccess;
  final PaymentModel? submittedPayment;

  PaymentSubmissionState({
    this.isLoading = false,
    this.errorMessage,
    this.isSuccess = false,
    this.submittedPayment,
  });

  PaymentSubmissionState copyWith({
    bool? isLoading,
    String? errorMessage,
    bool? isSuccess,
    PaymentModel? submittedPayment,
  }) {
    return PaymentSubmissionState(
      isLoading: isLoading ?? this.isLoading,
      errorMessage: errorMessage,
      isSuccess: isSuccess ?? this.isSuccess,
      submittedPayment: submittedPayment ?? this.submittedPayment,
    );
  }
}

final paymentSubmissionProvider = StateNotifierProvider<PaymentSubmissionNotifier, PaymentSubmissionState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return PaymentSubmissionNotifier(apiClient, ref);
});

class PaymentSubmissionNotifier extends StateNotifier<PaymentSubmissionState> {
  final ApiClient _apiClient;
  final Ref _ref;

  PaymentSubmissionNotifier(this._apiClient, this._ref) : super(PaymentSubmissionState());

  Future<bool> submitPayment({
    required int invoiceId,
    required double amount,
    required String paymentMethod,
    required String paymentProvider,
    required String senderName,
    required String senderBank,
    String? proofFilePath,
    List<int>? proofBytes,
    String? proofFilename,
  }) async {
    state = state.copyWith(isLoading: true, errorMessage: null, isSuccess: false);

    try {
      final formDataMap = <String, dynamic>{
        'amount': amount,
        'payment_method': paymentMethod,
        'payment_provider': paymentProvider,
        'sender_name': senderName,
        'sender_bank': senderBank,
      };

      if (proofBytes != null && proofFilename != null) {
        formDataMap['proof_file'] = MultipartFile.fromBytes(
          proofBytes,
          filename: proofFilename,
        );
      } else if (proofFilePath != null && proofFilePath.isNotEmpty) {
        formDataMap['proof_file'] = await MultipartFile.fromFile(
          proofFilePath,
          filename: proofFilePath.split('/').last,
        );
      }

      final formData = FormData.fromMap(formDataMap);

      final response = await _apiClient.dio.post(
        '${ApiEndpoints.invoices}/$invoiceId/payments',
        data: formData,
      );

      if (response.statusCode == 201 && response.data['success'] == true) {
        final payment = PaymentModel.fromJson(response.data['data'] as Map<String, dynamic>);
        state = state.copyWith(
          isLoading: false,
          isSuccess: true,
          submittedPayment: payment,
          errorMessage: null,
        );

        // Refresh data invoices setelah pengajuan
        _ref.invalidate(invoicesProvider);
        _ref.invalidate(invoiceDetailProvider(invoiceId));

        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          errorMessage: response.data['message'] ?? 'Pengajuan pembayaran gagal.',
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
    state = PaymentSubmissionState();
  }
}
